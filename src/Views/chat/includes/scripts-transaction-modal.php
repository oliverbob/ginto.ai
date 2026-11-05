<?php
/**
 * Transaction Details Modal Scripts
 * For users to view payment/transaction details and request admin review
 */
?>
<script>
  // ========================================
  // Transaction Details Modal
  // ========================================
  const transactionModal = document.getElementById('transaction-modal');
  const transactionModalBackdrop = document.getElementById('transaction-modal-backdrop');
  const transactionModalContent = document.getElementById('transaction-modal-content');
  const transactionDetailsContainer = document.getElementById('transaction-details');
  const closeTransactionModalBtn = document.getElementById('close-transaction-modal');
  
  let currentTransactionId = null;
  
  async function showTransactionDetails(transactionId) {
    if (!transactionModal) return;
    
    currentTransactionId = transactionId;
    
    // Show modal with loading state
    transactionModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    
    if (transactionDetailsContainer) {
      transactionDetailsContainer.innerHTML = `
        <div class="flex items-center justify-center py-12">
          <i class="fas fa-spinner fa-spin text-2xl text-indigo-600 dark:text-indigo-400"></i>
        </div>
      `;
    }
    
    try {
      const csrfToken = window.GINTO_AUTH?.csrfToken || '';
      const response = await fetch('/api/payment/transaction/' + transactionId, {
        method: 'GET',
        headers: { 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin'
      });
      
      const data = await response.json();
      
      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Failed to load transaction details');
      }
      
      const tx = data.transaction;
      
      // Format status badge
      const statusColors = {
        'completed': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'failed': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'refunded': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'under_review': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
      };
      const statusClass = statusColors[tx.status] || statusColors['pending'];
      
      // Format currency
      const formattedAmount = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: tx.currency || 'USD'
      }).format(tx.amount || 0);
      
      // Format date
      const formattedDate = new Date(tx.created_at).toLocaleString();
      
      let html = `
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction Details</h4>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
              ${escapeHtml((tx.status || '').replace('_', ' ').toUpperCase())}
            </span>
          </div>
          
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-500 dark:text-gray-400">Transaction ID</span>
              <span class="text-sm font-mono text-gray-900 dark:text-white">${escapeHtml(tx.id || tx.transaction_id || '-')}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-500 dark:text-gray-400">Amount</span>
              <span class="text-sm font-semibold text-gray-900 dark:text-white">${formattedAmount}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-500 dark:text-gray-400">Type</span>
              <span class="text-sm text-gray-900 dark:text-white">${escapeHtml(tx.type || 'Payment')}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-500 dark:text-gray-400">Provider</span>
              <span class="text-sm text-gray-900 dark:text-white">${escapeHtml(tx.provider || 'PayPal')}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-500 dark:text-gray-400">Date</span>
              <span class="text-sm text-gray-900 dark:text-white">${formattedDate}</span>
            </div>
            ${tx.plan_name ? `
            <div class="flex justify-between">
              <span class="text-sm text-gray-500 dark:text-gray-400">Plan</span>
              <span class="text-sm text-gray-900 dark:text-white">${escapeHtml(tx.plan_name)}</span>
            </div>
            ` : ''}
            ${tx.description ? `
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
              <span class="text-sm text-gray-500 dark:text-gray-400 block mb-1">Description</span>
              <span class="text-sm text-gray-900 dark:text-white">${escapeHtml(tx.description)}</span>
            </div>
            ` : ''}
          </div>
          
          ${tx.status === 'failed' || tx.status === 'pending' ? `
          <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <h5 class="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">Need Help?</h5>
            <p class="text-xs text-yellow-700 dark:text-yellow-300 mb-3">
              If you believe this transaction was processed incorrectly, you can request an admin review.
            </p>
            <button onclick="requestAdminReview('${escapeHtml(tx.id || tx.transaction_id)}')"
                    class="w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors">
              <i class="fas fa-flag mr-2"></i>Request Admin Review
            </button>
          </div>
          ` : ''}
          
          <div class="flex gap-3 pt-2">
            <button onclick="closeTransactionModal()"
                    class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-white text-sm font-medium rounded-lg transition-colors">
              Close
            </button>
            <button onclick="refreshPaymentStatus()"
                    class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
              <i class="fas fa-refresh mr-2"></i>Refresh Status
            </button>
          </div>
        </div>
      `;
      
      if (transactionDetailsContainer) {
        transactionDetailsContainer.innerHTML = html;
      }
      
    } catch (err) {
      console.error('[Transaction] Error loading details:', err);
      if (transactionDetailsContainer) {
        transactionDetailsContainer.innerHTML = `
          <div class="text-center py-8">
            <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-3"></i>
            <p class="text-red-600 dark:text-red-400">${escapeHtml(err.message)}</p>
            <button onclick="closeTransactionModal()" class="mt-4 px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg text-sm">Close</button>
          </div>
        `;
      }
    }
  }
  
  function closeTransactionModal() {
    if (transactionModal) {
      transactionModal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }
    currentTransactionId = null;
  }
  
  async function refreshPaymentStatus() {
    if (!currentTransactionId) return;
    
    try {
      const csrfToken = window.GINTO_AUTH?.csrfToken || '';
      const response = await fetch('/api/payment/refresh-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({ transaction_id: currentTransactionId })
      });
      
      const data = await response.json();
      
      if (data.success) {
        showToast('Payment status refreshed', 'success');
        // Reload transaction details
        showTransactionDetails(currentTransactionId);
      } else {
        showToast(data.error || 'Failed to refresh status', 'error');
      }
    } catch (err) {
      showToast('Failed to refresh status: ' + err.message, 'error');
    }
  }
  
  async function requestAdminReview(transactionId) {
    const confirmed = await showConfirmModal(
      'Request Admin Review',
      'Are you sure you want to request an admin review for this transaction? An administrator will be notified to review your payment.',
      'Request Review',
      'Cancel'
    );
    
    if (!confirmed) return;
    
    try {
      const csrfToken = window.GINTO_AUTH?.csrfToken || '';
      const response = await fetch('/api/payment/request-review', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({ transaction_id: transactionId })
      });
      
      const data = await response.json();
      
      if (data.success) {
        showToast('Admin review requested successfully', 'success');
        closeTransactionModal();
      } else {
        showToast(data.error || 'Failed to request review', 'error');
      }
    } catch (err) {
      showToast('Failed to request review: ' + err.message, 'error');
    }
  }
  
  // Close transaction modal handlers
  closeTransactionModalBtn?.addEventListener('click', closeTransactionModal);
  transactionModalBackdrop?.addEventListener('click', closeTransactionModal);
  
  // Escape key to close
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && transactionModal && !transactionModal.classList.contains('hidden')) {
      closeTransactionModal();
    }
  });
</script>
