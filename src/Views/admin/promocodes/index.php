<?php
// Admin Promo Codes Management View
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
$filter = $filter ?? 'active';
$promoCodes = $promoCodes ?? [];
$counts = $counts ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$csrf_token = $csrf_token ?? '';
?>
<!DOCTYPE html>
<html lang="en"<?php echo $htmlDark; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>Promo Codes - Ginto Admin</title>
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
        #sidebar nav::-webkit-scrollbar { width: 8px; }
        #sidebar nav::-webkit-scrollbar-track { background: transparent; }
        #sidebar nav::-webkit-scrollbar-thumb { background-color: rgba(156,163,175,0.5); border-radius: 9999px; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen flex flex-col">
        <?php include __DIR__ . '/../parts/header.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <i class="fas fa-tag text-orange-500"></i>
                            Promo Codes
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">Create and manage promotional discount codes</p>
                    </div>
                    <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-orange-500 to-yellow-500 text-white font-semibold rounded-lg shadow hover:from-orange-600 hover:to-yellow-600 transition">
                        <i class="fas fa-plus mr-2"></i> New Promo Code
                    </button>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <a href="/admin/promocodes?filter=active" class="px-4 py-2 rounded-lg font-medium transition <?= $filter === 'active' ? 'bg-orange-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        Active <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-white/20"><?= $counts['active'] ?? 0 ?></span>
                    </a>
                    <a href="/admin/promocodes?filter=inactive" class="px-4 py-2 rounded-lg font-medium transition <?= $filter === 'inactive' ? 'bg-gray-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        Inactive <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-white/20"><?= $counts['inactive'] ?? 0 ?></span>
                    </a>
                    <a href="/admin/promocodes?filter=expired" class="px-4 py-2 rounded-lg font-medium transition <?= $filter === 'expired' ? 'bg-red-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        Expired <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-white/20"><?= $counts['expired'] ?? 0 ?></span>
                    </a>
                    <a href="/admin/promocodes?filter=all" class="px-4 py-2 rounded-lg font-medium transition <?= $filter === 'all' ? 'bg-blue-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        All <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-white/20"><?= $counts['all'] ?? 0 ?></span>
                    </a>
                </div>

                <!-- Promo Codes Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Discount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Usage</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Validity</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <?php if (empty($promoCodes)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-tag text-4xl mb-3 opacity-50"></i>
                                        <p>No promo codes found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($promoCodes as $promo): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-orange-600 dark:text-orange-400"><?= htmlspecialchars($promo['code']) ?></span>
                                            <button onclick="copyCode('<?= htmlspecialchars($promo['code']) ?>')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Copy code">
                                                <i class="fas fa-copy text-sm"></i>
                                            </button>
                                        </div>
                                        <?php if ($promo['description']): ?>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($promo['description']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                            <?= $promo['discount_value'] ?>% OFF
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                            ₱<?= number_format($promo['discount_value'], 2) ?> OFF
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($promo['min_package_amount']): ?>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Min: ₱<?= number_format($promo['min_package_amount'], 2) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold"><?= $promo['used_count'] ?></span>
                                        <span class="text-gray-500 dark:text-gray-400">/</span>
                                        <span class="text-gray-600 dark:text-gray-400"><?= $promo['max_uses'] ?? '∞' ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php 
                                        $tz = new DateTimeZone('Asia/Manila');
                                        $now = new DateTime('now', $tz);
                                        $validFrom = $promo['valid_from'] ? new DateTime($promo['valid_from'], $tz) : null;
                                        $validUntil = $promo['valid_until'] ? new DateTime($promo['valid_until'], $tz) : null;
                                        $isExpired = $validUntil && $validUntil < $now;
                                        $notYetValid = $validFrom && $validFrom > $now;
                                        ?>
                                        <?php if ($validFrom || $validUntil): ?>
                                            <?php if ($validFrom): ?>
                                            <div class="text-gray-600 dark:text-gray-400">
                                                <i class="fas fa-calendar-alt mr-1"></i> From: <?= $validFrom->format('M d, Y') ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($validUntil): ?>
                                            <div class="<?= $isExpired ? 'text-red-500' : 'text-gray-600 dark:text-gray-400' ?>">
                                                <i class="fas fa-calendar-times mr-1"></i> Until: <?= $validUntil->format('M d, Y') ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">No expiry</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($promo['is_active']): ?>
                                            <?php if ($isExpired): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                                                <i class="fas fa-clock mr-1"></i> Expired
                                            </span>
                                            <?php elseif ($notYetValid): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400">
                                                <i class="fas fa-hourglass-start mr-1"></i> Scheduled
                                            </span>
                                            <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                                <i class="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                            <i class="fas fa-pause-circle mr-1"></i> Inactive
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="editPromo(<?= htmlspecialchars(json_encode($promo)) ?>)" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="togglePromo(<?= $promo['id'] ?>, <?= $promo['is_active'] ?>)" class="p-2 <?= $promo['is_active'] ? 'text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/30' : 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30' ?> rounded-lg transition" title="<?= $promo['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $promo['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                            </button>
                                            <button onclick="deletePromo(<?= $promo['id'] ?>, '<?= htmlspecialchars($promo['code']) ?>')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition" title="Delete">
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
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                            <a href="/admin/promocodes?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="px-3 py-1 rounded bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500 transition">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                            <a href="/admin/promocodes?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="px-3 py-1 rounded bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500 transition">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div id="promoModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900 dark:text-white">New Promo Code</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="promoForm" onsubmit="savePromo(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" id="promoId" name="id" value="">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Promo Code *</label>
                        <input type="text" id="codeInput" name="code" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="e.g., WELCOME10" style="text-transform: uppercase;">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <input type="text" id="descriptionInput" name="description" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Internal note about this promo">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount Type *</label>
                            <select id="discountTypeInput" name="discount_type" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₱)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount Value</label>
                            <input type="number" id="discountValueInput" name="discount_value" step="0.01" min="0" value="0" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Set to 0 for tracking-only codes</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Package Amount (₱)</label>
                        <input type="number" id="minPackageInput" name="min_package_amount" step="0.01" min="0" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Optional">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Total Uses</label>
                            <input type="number" id="maxUsesInput" name="max_uses" min="0" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Unlimited">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Uses Per User</label>
                            <input type="number" id="maxUsesPerUserInput" name="max_uses_per_user" min="0" value="1" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valid From <span class="text-xs text-gray-500">(leave empty = immediately valid)</span></label>
                            <input type="datetime-local" id="validFromInput" name="valid_from" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valid Until <span class="text-xs text-gray-500">(leave empty = no expiry)</span></label>
                            <input type="datetime-local" id="validUntilInput" name="valid_until" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Applicable Packages (comma-separated)</label>
                        <input type="text" id="packagesInput" name="applicable_packages" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Starter, Professional, Executive (leave empty for all)">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="isActiveInput" name="is_active" checked class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                        <label for="isActiveInput" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-gradient-to-r from-orange-500 to-yellow-500 text-white font-semibold hover:from-orange-600 hover:to-yellow-600 transition">
                        <i class="fas fa-save mr-2"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';
        
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'New Promo Code';
            document.getElementById('promoId').value = '';
            document.getElementById('promoForm').reset();
            document.getElementById('codeInput').disabled = false;
            document.getElementById('isActiveInput').checked = true;
            document.getElementById('promoModal').classList.remove('hidden');
            document.getElementById('promoModal').classList.add('flex');
        }
        
        function closeModal() {
            document.getElementById('promoModal').classList.add('hidden');
            document.getElementById('promoModal').classList.remove('flex');
        }
        
        function editPromo(promo) {
            document.getElementById('modalTitle').textContent = 'Edit Promo Code';
            document.getElementById('promoId').value = promo.id;
            document.getElementById('codeInput').value = promo.code;
            document.getElementById('codeInput').disabled = true; // Can't change code
            document.getElementById('descriptionInput').value = promo.description || '';
            document.getElementById('discountTypeInput').value = promo.discount_type;
            document.getElementById('discountValueInput').value = promo.discount_value;
            document.getElementById('minPackageInput').value = promo.min_package_amount || '';
            document.getElementById('maxUsesInput').value = promo.max_uses || '';
            document.getElementById('maxUsesPerUserInput').value = promo.max_uses_per_user || '';
            document.getElementById('validFromInput').value = promo.valid_from ? promo.valid_from.replace(' ', 'T').slice(0, 16) : '';
            document.getElementById('validUntilInput').value = promo.valid_until ? promo.valid_until.replace(' ', 'T').slice(0, 16) : '';
            document.getElementById('packagesInput').value = promo.applicable_packages ? JSON.parse(promo.applicable_packages).join(', ') : '';
            document.getElementById('isActiveInput').checked = promo.is_active == 1;
            
            document.getElementById('promoModal').classList.remove('hidden');
            document.getElementById('promoModal').classList.add('flex');
        }
        
        async function savePromo(e) {
            e.preventDefault();
            const form = document.getElementById('promoForm');
            const formData = new FormData(form);
            const promoId = formData.get('id');
            
            const url = promoId ? `/admin/promocodes/${promoId}/update` : '/admin/promocodes/create';
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(result.error || 'Failed to save promo code');
                }
            } catch (err) {
                alert('Error saving promo code');
                console.error(err);
            }
        }
        
        async function togglePromo(id, currentStatus) {
            if (!confirm(currentStatus ? 'Deactivate this promo code?' : 'Activate this promo code?')) return;
            
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            
            try {
                const response = await fetch(`/admin/promocodes/${id}/toggle`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to toggle promo code');
                }
            } catch (err) {
                alert('Error toggling promo code');
                console.error(err);
            }
        }
        
        async function deletePromo(id, code) {
            if (!confirm(`Delete promo code "${code}"? This action cannot be undone.`)) return;
            
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            
            try {
                const response = await fetch(`/admin/promocodes/${id}/delete`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to delete promo code');
                }
            } catch (err) {
                alert('Error deleting promo code');
                console.error(err);
            }
        }
        
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                // Brief visual feedback could be added here
            });
        }
        
        // Close modal on outside click
        document.getElementById('promoModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        // Convert code to uppercase as user types
        document.getElementById('codeInput').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>
