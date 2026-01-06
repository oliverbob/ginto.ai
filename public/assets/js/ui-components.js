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
    },

    /**
     * Create an autocomplete/typeahead input
     * @param {HTMLElement|string} container - Container element or selector to create autocomplete in
     * @param {Object} options - Configuration options
     * @param {string} options.searchApi - API endpoint for searching (appends query value)
     * @param {Array} options.data - Static data array (alternative to searchApi)
     * @param {Function} options.renderItem - Function to render each dropdown item (item) => HTML
     * @param {Function} options.onSelect - Callback when item is selected (item) => void
     * @param {Function} options.onChange - Callback when input value changes (value) => void
     * @param {Function} options.getDisplayValue - Function to get display value from item (item) => string
     * @param {number} options.minChars - Minimum characters before searching (default: 2)
     * @param {number} options.debounceMs - Debounce delay in ms (default: 250)
     * @param {string} options.placeholder - Placeholder text
     * @param {string} options.inputClass - CSS classes for the input
     */
    autocomplete(container, options = {}) {
        const containerEl = typeof container === 'string' 
            ? document.querySelector(container) 
            : container;
        
        if (!containerEl) {
            console.error('GintoUI.autocomplete: Container element not found:', container);
            return null;
        }

        const config = {
            searchApi: options.searchApi || '',
            data: options.data || null,
            renderItem: options.renderItem || (item => `<div class="px-3 py-2">${this.escapeHtml(item.name || item.label || item.username || '')}</div>`),
            onSelect: options.onSelect || (() => {}),
            onChange: options.onChange || (() => {}),
            getDisplayValue: options.getDisplayValue || (item => item.username || item.name || item.label || ''),
            minChars: options.minChars ?? 2,
            debounceMs: options.debounceMs ?? 250,
            placeholder: options.placeholder || 'Search...',
            inputClass: options.inputClass || 'w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600'
        };

        // Create wrapper and input
        const wrapper = document.createElement('div');
        wrapper.className = 'ginto-autocomplete-wrapper relative';
        
        const input = document.createElement('input');
        input.type = 'text';
        input.className = config.inputClass;
        input.placeholder = config.placeholder;
        input.autocomplete = 'off';
        wrapper.appendChild(input);

        const dropdown = document.createElement('div');
        dropdown.className = 'ginto-autocomplete-dropdown absolute left-0 right-0 top-full mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl max-h-64 overflow-y-auto z-50 hidden backdrop-blur-sm';
        wrapper.appendChild(dropdown);

        // Loading indicator
        const loader = document.createElement('div');
        loader.className = 'ginto-autocomplete-loader absolute right-3 top-1/2 -translate-y-1/2 hidden';
        loader.innerHTML = '<i class="fas fa-spinner fa-spin text-gray-400"></i>';
        wrapper.appendChild(loader);

        containerEl.appendChild(wrapper);

        let debounceTimer = null;
        let currentItems = [];
        let selectedIndex = -1;
        let isOpen = false;

        const showDropdown = () => {
            dropdown.classList.remove('hidden');
            isOpen = true;
        };

        const hideDropdown = () => {
            dropdown.classList.add('hidden');
            isOpen = false;
            selectedIndex = -1;
        };

        const highlightItem = (index) => {
            const items = dropdown.querySelectorAll('.ginto-autocomplete-item');
            items.forEach((item, i) => {
                item.classList.toggle('bg-emerald-50', i === index);
                item.classList.toggle('dark:bg-emerald-900/30', i === index);
            });
            selectedIndex = index;
            // Scroll into view
            if (items[index]) {
                items[index].scrollIntoView({ block: 'nearest' });
            }
        };

        const selectItem = (item) => {
            input.value = config.getDisplayValue(item);
            hideDropdown();
            config.onSelect(item);
        };

        const renderItems = (items) => {
            currentItems = items;
            if (!items.length) {
                dropdown.innerHTML = '<div class="px-4 py-3 text-gray-400 dark:text-gray-500 text-sm text-center"><i class="fas fa-search mr-2"></i>No results found</div>';
                showDropdown();
                return;
            }

            dropdown.innerHTML = items.map((item, index) => {
                const html = config.renderItem(item);
                return `<div class="ginto-autocomplete-item cursor-pointer hover:bg-emerald-50 dark:hover:bg-emerald-900/30 px-4 py-2.5 border-b border-gray-100 dark:border-gray-700/50 last:border-b-0 transition-all duration-150" data-index="${index}">${html}</div>`;
            }).join('');

            dropdown.querySelectorAll('.ginto-autocomplete-item').forEach((el, index) => {
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectItem(currentItems[index]);
                });
                el.addEventListener('mouseenter', () => highlightItem(index));
            });

            showDropdown();
        };

        const search = async (query) => {
            if (query.length < config.minChars) {
                hideDropdown();
                return;
            }

            // If static data provided, filter it
            if (config.data) {
                const q = query.toLowerCase();
                const filtered = config.data.filter(item => {
                    const searchFields = [
                        item.name, item.label, item.username, 
                        item.fullname, item.email, item.text
                    ].filter(Boolean).map(s => s.toLowerCase());
                    return searchFields.some(s => s.includes(q));
                });
                renderItems(filtered.slice(0, 15));
                return;
            }

            // API search
            if (!config.searchApi) return;
            
            loader.classList.remove('hidden');
            try {
                const url = config.searchApi + encodeURIComponent(query);
                const res = await fetch(url, { credentials: 'include' });
                const data = await res.json();
                renderItems(data.results || data.items || data.users || data.data || []);
            } catch (err) {
                console.error('Autocomplete search error:', err);
                dropdown.innerHTML = '<div class="px-3 py-2 text-red-500 text-sm">Error loading results</div>';
                showDropdown();
            } finally {
                loader.classList.add('hidden');
            }
        };

        // Event listeners
        input.addEventListener('input', (e) => {
            config.onChange(e.target.value);
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => search(e.target.value), config.debounceMs);
        });

        input.addEventListener('focus', () => {
            // Show initial items on focus (up to 10)
            if (config.data && config.data.length > 0 && !isOpen) {
                renderItems(config.data.slice(0, 10));
            } else if (input.value.length >= config.minChars && currentItems.length) {
                showDropdown();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (!isOpen && e.key !== 'ArrowDown') return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (!isOpen && input.value.length >= config.minChars) {
                        search(input.value);
                    } else if (isOpen) {
                        highlightItem(Math.min(selectedIndex + 1, currentItems.length - 1));
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    highlightItem(Math.max(selectedIndex - 1, 0));
                    break;
                case 'Enter':
                    if (selectedIndex >= 0 && currentItems[selectedIndex]) {
                        e.preventDefault();
                        selectItem(currentItems[selectedIndex]);
                    }
                    break;
                case 'Escape':
                    hideDropdown();
                    break;
                case 'Tab':
                    hideDropdown();
                    break;
            }
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) {
                hideDropdown();
            }
        });

        // Return API for controlling the autocomplete
        return {
            clear: () => {
                input.value = '';
                currentItems = [];
                hideDropdown();
            },
            setValue: (value) => {
                input.value = value || '';
            },
            getValue: () => input.value,
            setData: (data) => {
                config.data = data;
            },
            focus: () => input.focus(),
            destroy: () => {
                wrapper.remove();
            },
            getInput: () => input
        };
    },

    /**
     * Escape HTML for safe output
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Global shortcuts
const showToast = (msg, type, duration) => GintoUI.toast(msg, type, duration);
const showAlert = (msg, title, type) => GintoUI.alert(msg, title, type);
const showConfirm = (msg, title) => GintoUI.confirm(msg, title);
