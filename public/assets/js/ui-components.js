/**
 * Ginto AI - Shared UI Components
 * Reusable modal, toast, and notification components
 */

// ========== TOAST NOTIFICATIONS ==========
const GintoUI = {
    toastContainer: null,

    /**
     * Initialize toast container if not exists
     */
    initToastContainer() {
        if (!this.toastContainer) {
            this.toastContainer = document.getElementById('ginto-toast-container');
            if (!this.toastContainer) {
                this.toastContainer = document.createElement('div');
                this.toastContainer.id = 'ginto-toast-container';
                this.toastContainer.className = 'fixed bottom-6 right-6 z-[9999] flex flex-col gap-3';
                document.body.appendChild(this.toastContainer);
            }
        }
        return this.toastContainer;
    },

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - 'success' | 'error' | 'warning' | 'info'
     * @param {number} duration - Duration in ms (default 4000)
     */
    toast(message, type = 'info', duration = 4000) {
        const container = this.initToastContainer();
        
        const colors = {
            success: 'bg-emerald-600 border-emerald-500',
            error: 'bg-red-600 border-red-500',
            warning: 'bg-yellow-600 border-yellow-500',
            info: 'bg-blue-600 border-blue-500'
        };
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const toast = document.createElement('div');
        toast.className = `flex items-center gap-3 px-4 py-3 rounded-lg border text-white shadow-lg transform translate-x-full transition-transform duration-300 ${colors[type] || colors.info}`;
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info}"></i>
            <span class="flex-1">${message}</span>
            <button onclick="this.parentElement.remove()" class="text-white/70 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
        });
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    },

    /**
     * Show success toast
     */
    success(message, duration = 4000) {
        return this.toast(message, 'success', duration);
    },

    /**
     * Show error toast
     */
    error(message, duration = 5000) {
        return this.toast(message, 'error', duration);
    },

    /**
     * Show warning toast
     */
    warning(message, duration = 4000) {
        return this.toast(message, 'warning', duration);
    },

    /**
     * Show info toast
     */
    info(message, duration = 4000) {
        return this.toast(message, 'info', duration);
    },

    // ========== MODAL ==========

    /**
     * Show a modal dialog
     * @param {Object} options - Modal options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message (can include HTML)
     * @param {string} options.type - 'success' | 'error' | 'warning' | 'info' | 'confirm'
     * @param {Function} options.onConfirm - Callback for confirm button
     * @param {Function} options.onCancel - Callback for cancel button
     * @param {string} options.confirmText - Confirm button text (default: 'OK')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     */
    modal(options = {}) {
        const {
            title = 'Notice',
            message = '',
            type = 'info',
            onConfirm = null,
            onCancel = null,
            confirmText = 'OK',
            cancelText = 'Cancel',
            showCancel = type === 'confirm'
        } = options;

        const colors = {
            success: 'text-emerald-500',
            error: 'text-red-500',
            warning: 'text-yellow-500',
            info: 'text-blue-500',
            confirm: 'text-blue-500'
        };
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle',
            confirm: 'fa-question-circle'
        };

        const btnColors = {
            success: 'bg-emerald-600 hover:bg-emerald-700',
            error: 'bg-red-600 hover:bg-red-700',
            warning: 'bg-yellow-600 hover:bg-yellow-700',
            info: 'bg-blue-600 hover:bg-blue-700',
            confirm: 'bg-blue-600 hover:bg-blue-700'
        };

        // Remove existing modal
        const existing = document.getElementById('ginto-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'ginto-modal';
        modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] opacity-0 transition-opacity duration-200';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full mx-4 transform scale-95 transition-transform duration-200 shadow-2xl">
                <div class="flex items-start gap-4 mb-4">
                    <i class="fas ${icons[type]} text-3xl ${colors[type]}"></i>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">${title}</h3>
                        <p class="text-gray-600 dark:text-gray-300 mt-1">${message}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    ${showCancel ? `<button id="ginto-modal-cancel" class="px-4 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">${cancelText}</button>` : ''}
                    <button id="ginto-modal-confirm" class="px-4 py-2 ${btnColors[type]} text-white rounded-lg">${confirmText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Animate in
        requestAnimationFrame(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.bg-white, .dark\\:bg-gray-800')?.classList.remove('scale-95');
        });

        const close = (confirmed = false) => {
            modal.classList.add('opacity-0');
            setTimeout(() => modal.remove(), 200);
            if (confirmed && onConfirm) onConfirm();
            if (!confirmed && onCancel) onCancel();
        };

        modal.querySelector('#ginto-modal-confirm').onclick = () => close(true);
        if (showCancel) {
            modal.querySelector('#ginto-modal-cancel').onclick = () => close(false);
        }
        modal.onclick = (e) => {
            if (e.target === modal) close(false);
        };

        return { close };
    },

    /**
     * Show alert modal (replaces browser alert)
     */
    alert(message, title = 'Notice', type = 'info') {
        return new Promise(resolve => {
            this.modal({
                title,
                message,
                type,
                onConfirm: resolve
            });
        });
    },

    /**
     * Show confirm modal (replaces browser confirm)
     */
    confirm(message, title = 'Confirm') {
        return new Promise(resolve => {
            this.modal({
                title,
                message,
                type: 'confirm',
                showCancel: true,
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false)
            });
        });
    }
};

// Global shortcuts
const showToast = (msg, type, duration) => GintoUI.toast(msg, type, duration);
const showAlert = (msg, title, type) => GintoUI.alert(msg, title, type);
const showConfirm = (msg, title) => GintoUI.confirm(msg, title);
