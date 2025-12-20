<?php
// Admin Payments Management View
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
$filter = $filter ?? 'pending';
$payments = $payments ?? [];
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
    <title>Payment Management - Ginto Admin</title>
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
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-credit-card text-amber-500"></i>
                        Payment Management
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Approve or reject pending payments and manage subscriptions</p>
                </div>
                
                <!-- Filter Tabs -->
                <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                    <a href="/admin/payments?filter=pending" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filter === 'pending' ? 'bg-amber-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                        <i class="fas fa-clock"></i>
                        Pending
                        <?php if (($counts['pending'] ?? 0) > 0): ?>
                        <span class="bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 text-xs px-2 py-0.5 rounded-full"><?= $counts['pending'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/payments?filter=review" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filter === 'review' ? 'bg-red-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                        <i class="fas fa-user-shield"></i>
                        Needs Review
                        <?php if (($counts['review'] ?? 0) > 0): ?>
                        <span class="bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs px-2 py-0.5 rounded-full"><?= $counts['review'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/payments?filter=completed" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filter === 'completed' ? 'bg-green-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                        <i class="fas fa-check-circle"></i>
                        Completed
                        <span class="text-xs text-gray-500 dark:text-gray-400">(<?= $counts['completed'] ?? 0 ?>)</span>
                    </a>
                    <a href="/admin/payments?filter=failed" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filter === 'failed' ? 'bg-gray-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                        <i class="fas fa-times-circle"></i>
                        Failed/Rejected
                        <span class="text-xs text-gray-500 dark:text-gray-400">(<?= $counts['failed'] ?? 0 ?>)</span>
                    </a>
                    <a href="/admin/payments?filter=all" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filter === 'all' ? 'bg-blue-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                        <i class="fas fa-list"></i>
                        All
                        <span class="text-xs text-gray-500 dark:text-gray-400">(<?= $counts['all'] ?? 0 ?>)</span>
                    </a>
                </div>
                
                <!-- Payments Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                    <?php if (empty($payments)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400">No payments found</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Transaction</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($payments as $p): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors" id="payment-row-<?= $p['id'] ?>">
                                    <td class="px-4 py-4">
                                        <div class="font-mono text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($p['transaction_id'] ?? 'N/A') ?></div>
                                        <?php if ($p['payment_reference']): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ref: <?= htmlspecialchars(substr($p['payment_reference'], 0, 20)) ?><?= strlen($p['payment_reference']) > 20 ? '...' : '' ?></div>
                                        <?php endif; ?>
                                        <?php if ($p['admin_review_requested']): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs rounded-full">
                                            <i class="fas fa-exclamation-circle"></i> Review Requested
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if ($p['user']): ?>
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($p['user']['fullname'] ?? $p['user']['username']) ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($p['user']['email']) ?></div>
                                        <?php else: ?>
                                        <span class="text-gray-400">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php
                                        $methodIcons = [
                                            'paypal' => ['fab fa-paypal', 'text-blue-500'],
                                            'credit_card' => ['fas fa-credit-card', 'text-purple-500'],
                                            'gcash' => ['fas fa-mobile-alt', 'text-blue-400'],
                                            'bank_transfer' => ['fas fa-university', 'text-green-500'],
                                            'crypto' => ['fab fa-bitcoin', 'text-amber-500']
                                        ];
                                        $icon = $methodIcons[$p['payment_method']] ?? ['fas fa-money-bill', 'text-gray-500'];
                                        ?>
                                        <span class="inline-flex items-center gap-2">
                                            <i class="<?= $icon[0] ?> <?= $icon[1] ?>"></i>
                                            <span class="capitalize"><?= str_replace('_', ' ', $p['payment_method']) ?></span>
                                        </span>
                                        <?php if ($p['receipt_filename']): ?>
                                        <div class="mt-2">
                                            <button onclick="showReceiptModal('/receipt-image/<?= urlencode($p['receipt_filename']) ?>', '<?= htmlspecialchars($p['transaction_id'] ?? 'Receipt') ?>')" class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 rounded transition-colors">
                                                <i class="fas fa-image"></i> View Receipt
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="font-semibold text-gray-900 dark:text-white"><?= strtoupper($p['currency'] ?? 'USD') ?> <?= number_format((float)$p['amount'], 2) ?></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php
                                        $statusStyles = [
                                            'pending' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200',
                                            'completed' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200',
                                            'failed' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200'
                                        ];
                                        $style = $statusStyles[$p['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $style ?>">
                                            <?= ucfirst($p['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white"><?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= date('g:i A', strtotime($p['created_at'])) ?></div>
                                        <?php if ($p['geo_country'] || $p['geo_city']): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(implode(', ', array_filter([$p['geo_city'], $p['geo_country']]))) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if ($p['payment_status'] === 'pending'): ?>
                                        <div class="flex items-center gap-2">
                                            <button onclick="approvePayment(<?= $p['id'] ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg transition-colors">
                                                <i class="fas fa-check"></i>
                                                Approve
                                            </button>
                                            <button onclick="openRejectModal(<?= $p['id'] ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors">
                                                <i class="fas fa-times"></i>
                                                Reject
                                            </button>
                                        </div>
                                        <?php elseif ($p['payment_status'] === 'completed'): ?>
                                        <span class="text-green-500"><i class="fas fa-check-double"></i> Approved</span>
                                        <?php else: ?>
                                        <span class="text-red-500"><?= htmlspecialchars($p['rejection_reason'] ?? 'Rejected') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                            <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                            <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Approve Modal -->
    <div id="approve-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                Approve Payment
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Are you sure you want to approve this payment? This will activate the user's subscription.</p>
            <input type="hidden" id="approve-payment-id" value="">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeApproveModal()" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="confirmApprove()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fas fa-check mr-1"></i> Approve Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="reject-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-times-circle text-red-500 mr-2"></i>
                Reject Payment
            </h3>
            <form id="reject-form">
                <input type="hidden" id="reject-payment-id" value="">
                <div class="mb-4">
                    <label for="reject-reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reason for rejection (optional)</label>
                    <textarea id="reject-reason" rows="3" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="e.g., Invalid receipt, payment not received..."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                        <i class="fas fa-times mr-1"></i> Reject Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const csrfToken = '<?= $csrf_token ?>';
        
        function approvePayment(paymentId) {
            document.getElementById('approve-payment-id').value = paymentId;
            document.getElementById('approve-modal').classList.remove('hidden');
            document.getElementById('approve-modal').classList.add('flex');
        }
        
        function closeApproveModal() {
            document.getElementById('approve-modal').classList.add('hidden');
            document.getElementById('approve-modal').classList.remove('flex');
        }
        
        function confirmApprove() {
            const paymentId = document.getElementById('approve-payment-id').value;
            
            fetch(`/admin/payments/${paymentId}/approve`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ csrf_token: csrfToken })
            })
            .then(r => {
                if (!r.ok) {
                    return r.text().then(text => {
                        throw new Error(`HTTP ${r.status}: ${text}`);
                    });
                }
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    closeApproveModal();
                    // Update row visually or reload
                    const row = document.getElementById(`payment-row-${paymentId}`);
                    if (row) {
                        row.classList.add('bg-green-50', 'dark:bg-green-900/20');
                        setTimeout(() => location.reload(), 500);
                    }
                } else {
                    alert(data.message || data.error || 'Failed to approve payment');
                }
            })
            .catch(err => {
                console.error('Approve error:', err);
                alert('Error: ' + err.message);
            });
        }
        
        // Close approve modal on backdrop click
        document.getElementById('approve-modal').addEventListener('click', function(e) {
            if (e.target === this) closeApproveModal();
        });
        
        function openRejectModal(paymentId) {
            document.getElementById('reject-payment-id').value = paymentId;
            document.getElementById('reject-reason').value = '';
            document.getElementById('reject-modal').classList.remove('hidden');
            document.getElementById('reject-modal').classList.add('flex');
        }
        
        function closeRejectModal() {
            document.getElementById('reject-modal').classList.add('hidden');
            document.getElementById('reject-modal').classList.remove('flex');
        }
        
        document.getElementById('reject-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const paymentId = document.getElementById('reject-payment-id').value;
            const reason = document.getElementById('reject-reason').value;
            
            fetch(`/admin/payments/${paymentId}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ reason, csrf_token: csrfToken })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeRejectModal();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to reject payment');
                }
            })
            .catch(err => alert('Network error'));
        });
        
        // Close modal on backdrop click
        document.getElementById('reject-modal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
        
        // Receipt Modal - Zoom & Pan Image Viewer
        let currentZoom = 1;
        let panX = 0, panY = 0;
        let isDragging = false;
        let startX, startY;
        const minZoom = 0.5, maxZoom = 5, zoomStep = 0.25;
        
        function updateTransform() {
            const img = document.getElementById('receipt-modal-image');
            // When zoomed beyond 1, remove max constraints to allow full size
            if (currentZoom > 1) {
                img.style.maxWidth = 'none';
                img.style.maxHeight = 'none';
            } else {
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
            }
            img.style.transform = `translate(${panX}px, ${panY}px) scale(${currentZoom})`;
            document.getElementById('zoom-level').textContent = Math.round(currentZoom * 100) + '%';
        }
        
        function zoomIn() {
            currentZoom = Math.min(maxZoom, currentZoom + zoomStep);
            updateTransform();
        }
        
        function zoomOut() {
            currentZoom = Math.max(minZoom, currentZoom - zoomStep);
            // Reset pan if zooming out to fit
            if (currentZoom <= 1) { panX = 0; panY = 0; }
            updateTransform();
        }
        
        function resetZoom() {
            currentZoom = 1; panX = 0; panY = 0;
            updateTransform();
        }
        
        function showReceiptModal(imageUrl, title) {
            currentZoom = 1; panX = 0; panY = 0;
            document.getElementById('receipt-modal-title').textContent = title;
            document.getElementById('receipt-modal-image').src = imageUrl;
            document.getElementById('receipt-modal-link').href = imageUrl;
            document.getElementById('receipt-modal').classList.remove('hidden');
            document.getElementById('receipt-modal').classList.add('flex');
            updateTransform();
        }
        
        function closeReceiptModal() {
            const modal = document.getElementById('receipt-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            const img = document.getElementById('receipt-modal-image');
            if (img) img.src = '';
            resetZoom();
        }
        
        // Initialize event listeners after DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            // Mouse wheel zoom
            const container = document.getElementById('receipt-container');
            if (container) {
                container.addEventListener('wheel', function(e) {
                    e.preventDefault();
                    if (e.deltaY < 0) zoomIn();
                    else zoomOut();
                }, { passive: false });
                
                // Drag to pan
                container.addEventListener('mousedown', function(e) {
                    if (currentZoom > 1) {
                        isDragging = true;
                        startX = e.clientX - panX;
                        startY = e.clientY - panY;
                        this.style.cursor = 'grabbing';
                    }
                });
            }
            
            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                panX = e.clientX - startX;
                panY = e.clientY - startY;
                updateTransform();
            });
            
            document.addEventListener('mouseup', function() {
                isDragging = false;
                const c = document.getElementById('receipt-container');
                if (c) c.style.cursor = currentZoom > 1 ? 'grab' : 'default';
            });
            
            // Double-click to reset
            const img = document.getElementById('receipt-modal-image');
            if (img) img.addEventListener('dblclick', resetZoom);
            
            // Click backdrop to close
            const modal = document.getElementById('receipt-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) closeReceiptModal();
                });
            }
            
            // Sidebar toggle
            const sidebar = document.getElementById('sidebar');
            const menuButton = document.getElementById('menu-button');
            const closeButton = document.getElementById('close-button');
            
            if (menuButton) {
                menuButton.addEventListener('click', () => sidebar?.classList.remove('-translate-x-full'));
            }
            if (closeButton) {
                closeButton.addEventListener('click', () => sidebar?.classList.add('-translate-x-full'));
            }
        });
    </script>
    
<!-- Receipt Modal - placed at end of body, outside all containers, with highest z-index -->
<div id="receipt-modal" class="fixed inset-0 hidden items-center justify-center" style="background: rgba(0,0,0,0.9); z-index: 99999;">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="width: calc(100vw - 80px); max-width: 900px; height: calc(100vh - 120px); margin-top: 20px;">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <h3 id="receipt-modal-title" class="font-semibold text-gray-900 dark:text-white">Receipt</h3>
            <div class="flex items-center gap-2">
                <button onclick="zoomOut()" class="w-8 h-8 flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded" title="Zoom Out">
                    <i class="fas fa-minus text-xs"></i>
                </button>
                <span id="zoom-level" class="text-sm font-medium min-w-[50px] text-center">100%</span>
                <button onclick="zoomIn()" class="w-8 h-8 flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded" title="Zoom In">
                    <i class="fas fa-plus text-xs"></i>
                </button>
                <button onclick="resetZoom()" class="w-8 h-8 flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded" title="Reset">
                    <i class="fas fa-compress text-xs"></i>
                </button>
                <a id="receipt-modal-link" href="#" target="_blank" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">Open</a>
                <button onclick="closeReceiptModal()" class="w-8 h-8 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-gray-700 rounded" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <!-- Image -->
        <div id="receipt-container" class="flex-1 overflow-hidden flex items-center justify-center bg-gray-800 p-4" style="min-height: 60vh;">
            <img id="receipt-modal-image" src="" alt="Receipt" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        </div>
    </div>
</div>
</body>
</html>
