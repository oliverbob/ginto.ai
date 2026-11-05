<?php
// Admin Tier Plans Management View
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
$tiers = $tiers ?? [];
$csrf_token = $csrf_token ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>
<!DOCTYPE html>
<html lang="en"<?= $htmlDark ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>Tiers - Ginto Admin</title>
    <script>
        (function () {
            try {
                var saved = null;
                try { saved = localStorage.getItem('theme'); } catch (e) { saved = null; }
                if (!saved) {
                    var m = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
                    saved = m ? m[1] : null;
                }
                if (saved === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (err) {}
        })();
    </script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <style>
        #sidebar nav { max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen flex flex-col">
        <?php include __DIR__ . '/../parts/header.php'; ?>

        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <i class="fas fa-layer-group text-emerald-500"></i>
                            Membership Tiers
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">View and manage membership tier plans</p>
                    </div>
                    <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-500 to-green-500 text-white font-semibold rounded-lg shadow hover:from-emerald-600 hover:to-green-600 transition">
                        <i class="fas fa-plus mr-2"></i> New Tier
                    </button>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Name</th>

                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">One-time</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Recurring</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Commission</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">PayPal (Live)</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">PayPal (Sandbox)</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <?php if (empty($tiers)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-layer-group text-4xl mb-3 opacity-50"></i>
                                        <p>No tiers found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($tiers as $tier): ?>
                                <tr data-tier-id="<?= htmlspecialchars($tier['id']) ?>" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold"><?= htmlspecialchars($tier['name'] ?? '') ?></div>
                                        <?php if (!empty($tier['description'])): ?>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($tier['description']) ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-4 py-3">
                                        <?php $amount = $tier['cost_amount'] ?? $tier['amount'] ?? 0; $setup = $tier['setup_fee'] ?? 0; ?>
                                        <span class="font-semibold"><?= htmlspecialchars(number_format((float)$amount, 2)) ?></span>
                                        <div class="text-xs text-gray-500 mt-1">Setup: <?= htmlspecialchars(number_format((float)$setup, 2)) ?> <?= htmlspecialchars($tier['cost_currency'] ?? $tier['currency'] ?? 'PHP') ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($tier['recurring_amount'])): ?>
                                            <span class="font-semibold"><?= htmlspecialchars(number_format((float)$tier['recurring_amount'], 2)) ?></span>
                                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($tier['billing_interval'] ?? 'MONTH') ?></div>
                                        <?php else: ?>
                                            <div class="text-gray-400">No recurring</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if (!empty($tier['commission_rate_json'])):
                                            $c = @json_decode($tier['commission_rate_json'], true);
                                        ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php if (is_array($c) && !empty($c)): ?>
                                                <?php foreach ($c as $k => $v): ?>
                                                    <div><?= htmlspecialchars($k) ?>: <?= htmlspecialchars($v) ?></div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-gray-400">No commission rates</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                            <div class="text-gray-400">No commission rates</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <?= htmlspecialchars($tier['paypal_plan_id'] ?? '—') ?>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <?= htmlspecialchars($tier['paypal_plan_id_sandbox'] ?? '—') ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='editTier(<?= htmlspecialchars(json_encode($tier), ENT_QUOTES) ?>)' class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteTier(<?= $tier['id'] ?>, '<?= htmlspecialchars($tier['name']) ?>')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Page <?= $page ?> of <?= $totalPages ?></div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                            <a href="/admin/tier?page=<?= $page - 1 ?>" class="px-3 py-1 rounded bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500 transition"><i class="fas fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                            <a href="/admin/tier?page=<?= $page + 1 ?>" class="px-3 py-1 rounded bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500 transition"><i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div id="tierModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900 dark:text-white">New Tier</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form id="tierForm" onsubmit="saveTier(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <!-- Backwards-compatible field name used elsewhere in admin: include both -->
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" id="tierId" name="id" value="">
                <div class="space-y-4">
                        <div>
                            <input type="text" id="nameInput" name="name" required placeholder="Name" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="number" id="amountInput" name="cost_amount" placeholder="Price" step="0.01" min="0" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <input type="text" id="currencyInput" name="cost_currency" placeholder="Currency" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" value="PHP">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                                <input type="number" id="setupFeeInput" name="setup_fee" placeholder="Setup Fee" step="0.01" min="0" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <input type="number" id="recurringInput" name="recurring_amount" placeholder="Recurring Amount (optional)" step="0.01" min="0" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <select id="billingIntervalInput" name="billing_interval" aria-label="Billing Interval" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="MONTH">Monthly</option>
                            <option value="YEAR">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <textarea id="commissionInput" name="commission_rate_json" rows="4" placeholder='e.g., {"1":"0.05","2":"0.03"}' class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="text" id="paypalInput" name="paypal_plan_id" placeholder="PayPal Plan ID (optional)" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" disabled>
                        </div>
                        <div>
                            <input type="text" id="paypalSandboxInput" name="paypal_plan_id_sandbox" placeholder="Sandbox Plan ID (optional)" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" disabled>
                        </div>
                    </div>
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Leave PayPal Plan IDs empty to have them auto-created. <label class="ml-2 inline-flex items-center"><input id="manualPaypalToggle" type="checkbox" class="mr-2" /> Advanced: provide manual PayPal IDs</label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-gradient-to-r from-emerald-500 to-green-500 text-white font-semibold hover:from-emerald-600 hover:to-green-600 transition"><i class="fas fa-save mr-2"></i> Save</button>
                </div>
            </form>
        </div>
        <!-- Loader overlay inside modal (hidden by default) -->
        <div id="tierLoader" class="hidden absolute inset-0 flex items-center justify-center bg-black bg-opacity-40 rounded-2xl z-[100001]">
            <div class="flex flex-col items-center gap-3 bg-white dark:bg-gray-800 rounded-xl p-6">
                <svg class="animate-spin h-8 w-8 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <div class="text-sm font-medium text-gray-900 dark:text-white">Saving...</div>
            </div>
        </div>

    </div>

    <!-- Result Modal -->
    <div id="tierResultModal" class="fixed inset-0 z-[100000] hidden items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
            <div class="flex items-start gap-4">
                <div id="resultIcon" class="text-3xl"></div>
                <div class="flex-1">
                    <h4 id="resultTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Result</h4>
                    <p id="resultMessage" class="text-sm text-gray-600 dark:text-gray-300 mt-2">...</p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button id="resultClose" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Close</button>
                        <button id="resultReload" class="px-4 py-2 rounded-lg bg-emerald-500 text-white hidden">Reload</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php // Include the universal confirmation modal (provides showConfirmModal())
    include __DIR__ . '/../parts/confirm-modal.php';
    ?>

    <script>
        const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'New Tier';
            document.getElementById('tierId').value = '';
            document.getElementById('tierForm').reset();
            // Ensure manual PayPal toggle is off for new items
            const manualToggle = document.getElementById('manualPaypalToggle');
            if (manualToggle) { manualToggle.checked = false; toggleManualPaypal(false); }
            document.getElementById('tierModal').classList.remove('hidden');
            document.getElementById('tierModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('tierModal').classList.add('hidden');
            document.getElementById('tierModal').classList.remove('flex');
        }

        function editTier(tier) {
            document.getElementById('modalTitle').textContent = 'Edit Tier';
            document.getElementById('tierId').value = tier.id;
            document.getElementById('nameInput').value = tier.name || '';
            document.getElementById('amountInput').value = tier.cost_amount || tier.amount || '';
            document.getElementById('currencyInput').value = tier.cost_currency || tier.currency || 'PHP';
            document.getElementById('setupFeeInput').value = tier.setup_fee ?? 0;
            document.getElementById('recurringInput').value = tier.recurring_amount || '';
            document.getElementById('billingIntervalInput').value = tier.billing_interval || 'MONTH';
            document.getElementById('commissionInput').value = tier.commission_rate_json || '';
            document.getElementById('paypalInput').value = tier.paypal_plan_id || '';
            document.getElementById('paypalSandboxInput').value = tier.paypal_plan_id_sandbox || '';

            // If any PayPal ID exists, enable manual toggle so admin can edit
            const hasPaypal = !!(tier.paypal_plan_id || tier.paypal_plan_id_sandbox);
            const manualToggle = document.getElementById('manualPaypalToggle');
            if (manualToggle) { manualToggle.checked = !!hasPaypal; toggleManualPaypal(!!hasPaypal); }

            document.getElementById('tierModal').classList.remove('hidden');
            document.getElementById('tierModal').classList.add('flex');
        }

        async function saveTier(e) {
            e.preventDefault();
            const form = document.getElementById('tierForm');
            const formData = new FormData(form);
            const tierId = formData.get('id');
            const submitBtn = form.querySelector('button[type="submit"]');
            const cancelBtn = form.querySelector('button[type="button"]');
            const loader = document.getElementById('tierLoader');
            const resultModal = document.getElementById('tierResultModal');
            const resultTitle = document.getElementById('resultTitle');
            const resultMessage = document.getElementById('resultMessage');
            const resultIcon = document.getElementById('resultIcon');
            const resultReload = document.getElementById('resultReload');
            const resultClose = document.getElementById('resultClose');

            const url = tierId ? `/admin/tier/${tierId}/update` : '/admin/tier/create';

            // Show loader and disable buttons
            if (loader) loader.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData
                });
                const result = await response.json().catch(() => ({}));

                // Hide loader
                if (loader) loader.classList.add('hidden');
                if (submitBtn) submitBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;

                // Show result modal
                resultModal.classList.remove('hidden');
                resultModal.classList.add('flex');
                resultReload.classList.add('hidden');

                if (result && result.success) {
                    // Close create/edit modal immediately on success
                    try { closeModal(); } catch (e) {}

                    // Build a tier object from the form values and server response
                    const newId = result.id || tierId || null;
                    const tierObj = {
                        id: newId,
                        name: formData.get('name') || '',
                        cost_amount: formData.get('cost_amount') || formData.get('amount') || '',
                        setup_fee: formData.get('setup_fee') || '',
                        recurring_amount: formData.get('recurring_amount') || '',
                        billing_interval: formData.get('billing_interval') || 'MONTH',
                        commission_rate_json: formData.get('commission_rate_json') || '',
                        paypal_plan_id: formData.get('paypal_plan_id') || '',
                        paypal_plan_id_sandbox: formData.get('paypal_plan_id_sandbox') || '',
                        cost_currency: formData.get('cost_currency') || formData.get('currency') || 'PHP'
                    };

                    // If server created PayPal plans, merge returned IDs into the object
                    if (result && result.paypal_created && typeof result.paypal_created === 'object') {
                        if (result.paypal_created.paypal_plan_id) tierObj.paypal_plan_id = result.paypal_created.paypal_plan_id;
                        if (result.paypal_created.paypal_plan_id_sandbox) tierObj.paypal_plan_id_sandbox = result.paypal_created.paypal_plan_id_sandbox;
                    }

                    // Helper: escape HTML for safe insertion
                    function escapeHtml(s) { if (s === null || s === undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

                    function buildRow(t) {
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-tier-id', t.id);
                        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700/50 transition';

                        const amount = parseFloat(t.cost_amount) || 0;
                        const setup = parseFloat(t.setup_fee) || 0;
                        const recurring = t.recurring_amount ? parseFloat(t.recurring_amount) : null;

                        const commissionHtml = (() => {
                            try {
                                const c = JSON.parse(t.commission_rate_json || '{}');
                                if (c && typeof c === 'object' && Object.keys(c).length) {
                                    return Object.keys(c).map(k => `<div>${escapeHtml(k)}: ${escapeHtml(c[k])}</div>`).join('');
                                }
                            } catch (e) {}
                            return '<div class="text-gray-400">No commission rates</div>';
                        })();

                        tr.innerHTML = `
                            <td class="px-4 py-3">
                                <div class="font-semibold">${escapeHtml(t.name)}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">${escapeHtml(Number(amount).toFixed(2))}</span>
                                <div class="text-xs text-gray-500 mt-1">Setup: ${escapeHtml(Number(setup).toFixed(2))} ${escapeHtml(t.cost_currency || 'PHP')}</div>
                            </td>
                            <td class="px-4 py-3">
                                ${recurring ? `<span class="font-semibold">${escapeHtml(Number(recurring).toFixed(2))}</span><div class="text-xs text-gray-500 mt-1">${escapeHtml(t.billing_interval || 'MONTH')}</div>` : '<div class="text-gray-400">No recurring</div>'}
                            </td>
                            <td class="px-4 py-3 text-sm"><div class="text-xs text-gray-500 dark:text-gray-400">${commissionHtml}</div></td>
                            <td class="px-4 py-3 text-xs">${escapeHtml(t.paypal_plan_id || '—')}</td>
                            <td class="px-4 py-3 text-xs">${escapeHtml(t.paypal_plan_id_sandbox || '—')}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <button class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition" title="Edit"></button>
                                    <button class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition" title="Delete"></button>
                                </div>
                            </td>
                        `;

                        // Add button behaviors
                        const editBtn = tr.querySelector('button[title="Edit"]');
                        const deleteBtn = tr.querySelector('button[title="Delete"]');
                        editBtn.innerHTML = '<i class="fas fa-edit"></i>';
                        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                        editBtn.onclick = () => editTier(t);
                        deleteBtn.onclick = () => deleteTier(t.id, t.name);

                        return tr;
                    }

                    // Insert or replace row in table body
                    const tbody = document.querySelector('table tbody');
                    if (newId) {
                        const existing = tbody.querySelector(`tr[data-tier-id="${newId}"]`);
                        const newRow = buildRow(tierObj);
                        if (existing && existing.parentNode) {
                            existing.parentNode.replaceChild(newRow, existing);
                        } else {
                            // insert at top
                            if (tbody.firstChild) tbody.insertBefore(newRow, tbody.firstChild);
                            else tbody.appendChild(newRow);
                        }
                    }

                    resultIcon.innerHTML = '<i class="fas fa-check-circle text-emerald-500"></i>';
                    resultTitle.textContent = 'Tier Saved';
                    resultMessage.textContent = result.message || 'Tier created/updated successfully.';
                    resultReload.classList.add('hidden');
                } else {
                    resultIcon.innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                    resultTitle.textContent = 'Failed to Save';
                    resultMessage.textContent = result.error || result.message || 'An error occurred while saving the tier.';
                }

                resultClose.onclick = function () {
                    resultModal.classList.add('hidden');
                    resultModal.classList.remove('flex');
                    // Close create/edit modal if success
                    if (result && result.success) closeModal();
                };
                resultReload.onclick = function () { resultModal.classList.add('hidden'); resultModal.classList.remove('flex'); };

            } catch (err) {
                if (loader) loader.classList.add('hidden');
                if (submitBtn) submitBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;

                console.error('Error saving tier', err);
                // Show error in result modal
                const resultModal = document.getElementById('tierResultModal');
                resultModal.classList.remove('hidden');
                resultModal.classList.add('flex');
                document.getElementById('resultIcon').innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                document.getElementById('resultTitle').textContent = 'Error';
                document.getElementById('resultMessage').textContent = err.message || 'Unknown error';
                document.getElementById('resultClose').onclick = function () { resultModal.classList.add('hidden'); resultModal.classList.remove('flex'); };
            }
        }

        // Toggle helper to enable/disable PayPal inputs
        function toggleManualPaypal(enable) {
            const paypal = document.getElementById('paypalInput');
            const paypalSandbox = document.getElementById('paypalSandboxInput');
            if (paypal) { paypal.disabled = !enable; paypal.closest('div').style.opacity = enable ? '1' : '0.6'; }
            if (paypalSandbox) { paypalSandbox.disabled = !enable; paypalSandbox.closest('div').style.opacity = enable ? '1' : '0.6'; }
        }

        // Wire checkbox
        (function () {
            const toggle = document.getElementById('manualPaypalToggle');
            if (toggle) {
                toggle.addEventListener('change', function () { toggleManualPaypal(!!this.checked); });
                // initialize disabled state
                toggleManualPaypal(!!toggle.checked);
            }
        })();

        async function deleteTier(id, name) {
            // Use the universal confirm modal if available
            let confirmed = false;
            if (typeof showConfirmModal === 'function') {
                confirmed = await showConfirmModal({
                    title: 'Delete Tier',
                    message: `Delete tier "${name}"? This action cannot be undone.`,
                    type: 'danger',
                    confirmText: 'Delete'
                });
            } else {
                confirmed = confirm(`Delete tier "${name}"? This action cannot be undone.`);
            }
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            // Show loader overlay and update text
            const loader = document.getElementById('tierLoader');
            if (loader) {
                const textEl = loader.querySelector('.text-sm');
                if (textEl) textEl.textContent = 'Deleting...';
                loader.classList.remove('hidden');
            }

            // Disable buttons in the row while deleting
            const row = document.querySelector(`table tbody tr[data-tier-id="${id}"]`);
            const rowButtons = row ? row.querySelectorAll('button') : [];
            rowButtons.forEach(b => b.disabled = true);

            try {
                const response = await fetch(`/admin/tier/${id}/delete`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: formData });
                const result = await response.json().catch(() => ({}));
                if (result.success) {
                    // Remove the row from the table (update UI in-place)
                    try {
                        const row = document.querySelector(`table tbody tr[data-tier-id="${id}"]`);
                        if (row && row.parentNode) row.parentNode.removeChild(row);
                    } catch (e) { /* ignore */ }
                    if (loader) loader.classList.add('hidden');

                    if (document.getElementById('tierResultModal')) {
                        document.getElementById('resultIcon').innerHTML = '<i class="fas fa-check-circle text-emerald-500"></i>';
                        document.getElementById('resultTitle').textContent = 'Deleted';
                        document.getElementById('resultMessage').textContent = result.message || 'Tier deleted successfully.';
                        document.getElementById('tierResultModal').classList.remove('hidden');
                        document.getElementById('tierResultModal').classList.add('flex');
                        document.getElementById('resultReload').classList.add('hidden');
                        document.getElementById('resultClose').onclick = () => { document.getElementById('tierResultModal').classList.add('hidden'); document.getElementById('tierResultModal').classList.remove('flex'); };
                    }
                } else {
                    if (loader) loader.classList.add('hidden');
                    // Re-enable row buttons on failure
                    rowButtons.forEach(b => b.disabled = false);

                    if (document.getElementById('tierResultModal')) {
                        document.getElementById('resultIcon').innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                        document.getElementById('resultTitle').textContent = 'Failed to Delete';
                        document.getElementById('resultMessage').textContent = result.error || 'Failed to delete tier';
                        document.getElementById('tierResultModal').classList.remove('hidden');
                        document.getElementById('tierResultModal').classList.add('flex');
                        document.getElementById('resultReload').classList.add('hidden');
                        document.getElementById('resultClose').onclick = () => { document.getElementById('tierResultModal').classList.add('hidden'); document.getElementById('tierResultModal').classList.remove('flex'); };
                    } else {
                        alert(result.error || 'Failed to delete tier');
                    }
                }
            } catch (err) {
                console.error(err);
                if (loader) loader.classList.add('hidden');
                rowButtons.forEach(b => b.disabled = false);
                if (document.getElementById('tierResultModal')) {
                    document.getElementById('resultIcon').innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                    document.getElementById('resultTitle').textContent = 'Error';
                    document.getElementById('resultMessage').textContent = err.message || 'Error deleting tier';
                    document.getElementById('tierResultModal').classList.remove('hidden');
                    document.getElementById('tierResultModal').classList.add('flex');
                    document.getElementById('resultClose').onclick = () => { document.getElementById('tierResultModal').classList.add('hidden'); document.getElementById('tierResultModal').classList.remove('flex'); };
                } else {
                    alert('Error deleting tier');
                }
            }
        }

        // Close modal on outside click
        document.getElementById('tierModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
