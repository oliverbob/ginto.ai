<!-- Universal Confirmation Modal (matches /admin/lxc style) -->
<div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4" style="background: rgba(0,0,0,0.5);">
    <div id="confirm-modal-card" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700" style="border-radius: 4px; max-width: 28rem; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <!-- Header -->
        <div class="border-b border-gray-200 dark:border-gray-700" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
            <div id="confirm-modal-icon" style="width: 2.5rem; height: 2.5rem; border-radius: 50%; background: rgba(217,119,6,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-exclamation-circle" style="color: #f59e0b; font-size: 1.1rem;"></i>
            </div>
            <div style="flex: 1; min-width: 0;">
                <h3 id="confirm-modal-title" class="text-gray-900 dark:text-white" style="font-size: 1.1rem; font-weight: 600; margin: 0;">Confirm Action</h3>
                <p id="confirm-modal-subtitle" class="hidden text-gray-500 dark:text-gray-400" style="font-size: 0.875rem; margin: 0.25rem 0 0 0;"></p>
            </div>
        </div>
        <!-- Body -->
        <div style="padding: 1.25rem;">
            <p id="confirm-modal-message" class="text-gray-600 dark:text-gray-300" style="font-size: 0.95rem; margin: 0; line-height: 1.5;">Are you sure you want to proceed?</p>
        </div>
        <!-- Footer -->
        <div class="border-t border-gray-200 dark:border-gray-700" style="padding: 1rem 1.25rem; display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
            <button onclick="closeConfirmModal()" id="confirm-modal-cancel" class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600" style="padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 500; transition: background 0.15s;">
                Cancel
            </button>
            <button id="confirm-modal-action" style="padding: 0.5rem 1rem; background: #d97706; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: background 0.15s;">
                <i id="confirm-modal-btn-icon" class="fas fa-check"></i>
                <span id="confirm-modal-btn-text">Confirm</span>
            </button>
        </div>
    </div>
</div>

<script>
// ============= Universal Confirmation Modal =============
let confirmModalCallback = null;

/**
 * Show a confirmation modal with customizable options
 * 
 * @param {Object} options - Modal configuration
 * @param {string} options.title - Modal title
 * @param {string} options.subtitle - Subtitle under title (optional)
 * @param {string} options.message - Modal message body
 * @param {string} options.confirmText - Confirm button text (default: "Confirm")
 * @param {string} options.confirmIcon - Font Awesome icon class (default: "fa-check")
 * @param {string} options.cancelText - Cancel button text (default: "Cancel")
 * @param {string} options.type - Modal type: 'danger', 'warning', 'info', 'success' (default: 'warning')
 * @returns {Promise<boolean>} Resolves to true if confirmed, false if cancelled
 */
function showConfirmModal(options = {}) {
    const modal = document.getElementById('confirm-modal');
    const title = document.getElementById('confirm-modal-title');
    const subtitle = document.getElementById('confirm-modal-subtitle');
    const message = document.getElementById('confirm-modal-message');
    const actionBtn = document.getElementById('confirm-modal-action');
    const cancelBtn = document.getElementById('confirm-modal-cancel');
    const btnText = document.getElementById('confirm-modal-btn-text');
    const btnIcon = document.getElementById('confirm-modal-btn-icon');
    const iconContainer = document.getElementById('confirm-modal-icon');
    
    if (!modal) return Promise.resolve(false);
    
    // Set content
    title.textContent = options.title || 'Confirm Action';
    message.textContent = options.message || 'Are you sure you want to proceed?';
    btnText.textContent = options.confirmText || 'Confirm';
    cancelBtn.textContent = options.cancelText || 'Cancel';
    
    // Set subtitle if provided
    if (options.subtitle) {
        subtitle.textContent = options.subtitle;
        subtitle.classList.remove('hidden');
    } else if (options.details) {
        subtitle.textContent = options.details;
        subtitle.classList.remove('hidden');
    } else {
        subtitle.classList.add('hidden');
    }
    
    // Type-based styling with explicit colors
    const type = options.type || 'warning';
    const typeConfig = {
        danger: {
            iconBg: 'rgba(220,38,38,0.2)',
            iconColor: '#ef4444',
            iconClass: 'fa-exclamation-triangle',
            btnBg: '#dc2626',
            btnHover: '#b91c1c',
            defaultIcon: 'fa-trash'
        },
        warning: {
            iconBg: 'rgba(217,119,6,0.2)',
            iconColor: '#f59e0b',
            iconClass: 'fa-exclamation-circle',
            btnBg: '#d97706',
            btnHover: '#b45309',
            defaultIcon: 'fa-check'
        },
        info: {
            iconBg: 'rgba(37,99,235,0.2)',
            iconColor: '#3b82f6',
            iconClass: 'fa-info-circle',
            btnBg: '#2563eb',
            btnHover: '#1d4ed8',
            defaultIcon: 'fa-check'
        },
        success: {
            iconBg: 'rgba(22,163,74,0.2)',
            iconColor: '#22c55e',
            iconClass: 'fa-check-circle',
            btnBg: '#16a34a',
            btnHover: '#15803d',
            defaultIcon: 'fa-check'
        }
    };
    
    const config = typeConfig[type] || typeConfig.warning;
    
    // Set icon styling
    iconContainer.style.background = config.iconBg;
    iconContainer.innerHTML = `<i class="fas ${config.iconClass}" style="color: ${config.iconColor}; font-size: 1.1rem;"></i>`;
    
    // Set button styling
    actionBtn.style.background = config.btnBg;
    actionBtn.onmouseenter = () => actionBtn.style.background = config.btnHover;
    actionBtn.onmouseleave = () => actionBtn.style.background = config.btnBg;
    btnIcon.className = `fas ${options.confirmIcon || config.defaultIcon}`;
    
    // Cancel button hover
    cancelBtn.onmouseenter = () => cancelBtn.style.background = '#4b5563';
    cancelBtn.onmouseleave = () => cancelBtn.style.background = '#374151';
    
    // Show modal
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    
    // Return promise that resolves when user makes a choice
    return new Promise((resolve) => {
        confirmModalCallback = resolve;
        
        // Set up action button click
        actionBtn.onclick = () => {
            confirmModalCallback = null;
            closeConfirmModal();
            resolve(true);
        };
    });
}

function closeConfirmModal() {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;
    
    modal.classList.add('hidden');
    modal.style.display = 'none';
    
    if (confirmModalCallback) {
        confirmModalCallback(false);
        confirmModalCallback = null;
    }
}

// Close on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('confirm-modal');
        if (modal && !modal.classList.contains('hidden')) {
            closeConfirmModal();
        }
    }
});

// Close on backdrop click
document.getElementById('confirm-modal')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
        closeConfirmModal();
    }
});
</script>
