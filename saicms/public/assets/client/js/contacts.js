/**
 * Manages fetching and displaying contacts in the sidebar.
 */
class ContactsManager {
    constructor(listContainerId, loadingStateId, emptyStateId) {
        this.listContainer = document.getElementById(listContainerId);
        this.loadingState = document.getElementById(loadingStateId);
        this.emptyState = document.getElementById(emptyStateId);
        
        this.chatManager = window.globalChatManager || null;
        this.statusPollingInterval = null;

        if (!this.listContainer || !this.loadingState || !this.emptyState) {
            console.error('ContactsManager: Constructor - One or more required DOM elements are missing. Contacts will not load.');
            return;
        }
        setTimeout(() => this.init(), 50);
    }
    
    /**
     * NEW HELPER: Centralizes getting the CSRF token.
     * @returns {string|null} The CSRF token or null if not found.
     */
    _getCsrfToken() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        return csrfMeta ? csrfMeta.getAttribute('content') : null;
    }

    init() {
        if (!this.chatManager && window.globalChatManager) {
            this.chatManager = window.globalChatManager;
        }
        if (!this.chatManager) {
            console.warn('ContactsManager: ChatManager is not available. Contacts cannot open chats directly.');
        }
        if (typeof window.APP_USER_ID !== 'undefined' && window.APP_USER_ID !== null) {
            this.loadContacts();
        } else {
            this._showVisualState('loginRequired');
        }
    }

    _showVisualState(stateType, message = '') {
        if (!this.listContainer || !this.loadingState || !this.emptyState) return;

        this.loadingState.classList.add('hidden');
        this.emptyState.classList.add('hidden');
        this._stopStatusPolling();

        const itemsToRemove = Array.from(this.listContainer.children)
                                .filter(child => child !== this.loadingState && child !== this.emptyState);
        itemsToRemove.forEach(child => child.remove());

        switch (stateType) {
            case 'loading':
                this.loadingState.classList.remove('hidden');
                break;
            case 'empty':
            case 'error':
            case 'loginRequired':
                this.emptyState.textContent = message || (stateType === 'empty' ? 'No contacts found.' : (stateType === 'error' ? 'Error loading contacts.' : 'Log in to see contacts.'));
                this.emptyState.classList.remove('hidden');
                break;
            case 'listReady':
                break;
        }
    }

    async loadContacts() {
        this._showVisualState('loading');
        try {
            // GET requests don't need CSRF tokens, so this is fine as-is.
            const response = await fetch('/contacts?type=friends&limit=15'); 
            if (!response.ok) {
                let errorMsg = `HTTP error ${response.status}`;
                try { const errorData = await response.json(); errorMsg = errorData.error || errorMsg; } catch (e) { /* Ignore */ }
                throw new Error(errorMsg);
            }

            const result = await response.json();

            if (result.success && Array.isArray(result.contacts)) {
                if (result.contacts.length === 0) {
                    this._showVisualState('empty', 'You have no friends yet.');
                } else {
                    this._showVisualState('listReady');
                    result.contacts.forEach(contact => {
                        const contactElement = this._createContactElement(contact);
                        if (contactElement) {
                           this.listContainer.appendChild(contactElement);
                        }
                    });
                    this._startStatusPolling();
                }
            } else {
                throw new Error(result.error || 'Failed to load contacts: Invalid data format.');
            }
        } catch (error) {
            console.error('ContactsManager: Error in loadContacts -', error);
            this._showVisualState('error', `Error: ${error.message}`);
        }
    }

    _createContactElement(contact) {
        if (!contact || typeof contact.id === 'undefined' || !contact.name) {
            console.warn('ContactsManager: Invalid contact data received.', contact);
            return null;
        }

        const contactLink = document.createElement('a');
        contactLink.href = '#';
        contactLink.className = 'message-item contact-item flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg cursor-pointer';
        
        contactLink.dataset.userId = String(contact.id);
        contactLink.dataset.userName = contact.name;
        contactLink.dataset.userAvatar = contact.avatar;
        contactLink.dataset.isOnline = String(contact.isOnline || false);

        const onlineStatusDotClass = contact.isOnline ? 'bg-green-500' : 'bg-gray-400';
        const onlineStatusDiv = `
            <div class="status-indicator absolute bottom-0 right-0 w-3 h-3 ${onlineStatusDotClass} rounded-full border-2 border-white dark:border-dark-700"></div>
        `;
        
        const safeDisplayName = (this.chatManager && typeof this.chatManager.sanitizeHTML === 'function')
                                ? this.chatManager.sanitizeHTML(contact.name)
                                : contact.name.replace(/</g, "<").replace(/>/g, ">");

        contactLink.innerHTML = `
            <div class="relative flex-shrink-0">
                <img src="${contact.avatar}" alt="${safeDisplayName}" class="w-8 h-8 rounded-full object-cover">
                ${onlineStatusDiv}
            </div>
            <span class="ml-2 dark:text-white truncate">${safeDisplayName}</span>
        `;

        contactLink.addEventListener('click', (event) => {
            event.preventDefault();
            if (this.chatManager && typeof this.chatManager.openChat === 'function') {
                this.chatManager.openChat(contact.id, contact.name, contact.avatar);
            } else {
                console.warn('ContactsManager: ChatManager not available. Cannot open chat.');
                alert('Chat functionality is currently unavailable.');
            }
        });
        return contactLink;
    }

    _startStatusPolling() {
        this._stopStatusPolling();
        this._refreshStatuses();
        this.statusPollingInterval = setInterval(() => this._refreshStatuses(), 20000);
    }

    _stopStatusPolling() {
        if (this.statusPollingInterval) {
            clearInterval(this.statusPollingInterval);
            this.statusPollingInterval = null;
        }
    }

    /*********************************************************************
     * REFACTORED FUNCTION
     * The `fetch` call inside this method now includes the CSRF token.
     *********************************************************************/
    async _refreshStatuses() {
        const contactElements = this.listContainer.querySelectorAll('.contact-item');
        if (contactElements.length === 0) {
            this._stopStatusPolling();
            return;
        }
        const userIds = Array.from(contactElements).map(el => el.dataset.userId);

        // --- START OF MODIFICATION ---
        const csrfToken = this._getCsrfToken();
        if (!csrfToken) {
            console.error('CSRF token not found. Status polling aborted.');
            this._stopStatusPolling(); // Stop polling if token is missing
            return;
        }
        // --- END OF MODIFICATION ---

        try {
            const response = await fetch('/contacts/statuses', {
                method: 'POST',
                // --- MODIFIED HEADERS ---
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken // Add CSRF token to the request
                },
                body: JSON.stringify({ ids: userIds })
            });

            if (!response.ok) {
                // If it's a CSRF error, stop polling to prevent repeated failures.
                if (response.status === 403) {
                    console.error('CSRF validation failed for status polling. Stopping polling.');
                    this._stopStatusPolling();
                } else {
                    console.error('Status poll failed with status:', response.status);
                }
                return;
            }

            const result = await response.json();
            if (result.success && result.statuses) {
                this._updateStatusIndicators(result.statuses);
            }
        } catch (error) {
            console.error('Error during status refresh:', error);
        }
    }
    
    _updateStatusIndicators(statuses) {
        for (const userId in statuses) {
            if (statuses.hasOwnProperty(userId)) {
                const isOnline = statuses[userId];
                const contactElement = this.listContainer.querySelector(`.contact-item[data-user-id='${userId}']`);

                if (contactElement) {
                    const statusDot = contactElement.querySelector('.status-indicator');
                    if (statusDot) {
                        const currentlyOnline = statusDot.classList.contains('bg-green-500');
                        if (isOnline !== currentlyOnline) {
                             contactElement.dataset.isOnline = String(isOnline);
                             statusDot.classList.toggle('bg-green-500', isOnline);
                             statusDot.classList.toggle('bg-gray-400', !isOnline);
                        }
                    }
                }
            }
        }
    }
}


/*********************************************************************
 * REFACTORED FUNCTION
 * The `fetch` call inside the heartbeat now includes the CSRF token.
 *********************************************************************/
function setupUserActivityHeartbeat() {
    if (typeof window.APP_USER_ID === 'undefined' || window.APP_USER_ID === null) {
        return;
    }

    const sendHeartbeat = () => {
        // --- START OF MODIFICATION ---
        // We get the token right before sending the request.
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

        if (!csrfToken) {
            console.warn('User activity heartbeat skipped: CSRF token not found.');
            return;
        }
        // --- END OF MODIFICATION ---
        
        // "Fire-and-forget" fetch request.
        fetch('/user/activity', { 
            method: 'POST',
            // --- MODIFIED HEADERS ---
            headers: {
                'X-CSRF-TOKEN': csrfToken // Add CSRF token to the request
            }
        }).catch(err => {
            console.warn('User activity heartbeat failed.', err);
        });
    };

    sendHeartbeat(); // Send one immediately on page load
    setInterval(sendHeartbeat, 60000); // And then every 60 seconds
}


// Initialize everything after the DOM is loaded.
document.addEventListener('DOMContentLoaded', () => {
    setupUserActivityHeartbeat();

    setTimeout(() => {
        if (document.getElementById('contactsListContainer')) {
            new ContactsManager(
                'contactsListContainer',
                'contactsLoadingState',
                'contactsEmptyState'
            );
        } else {
            console.warn("Contacts module: 'contactsListContainer' not found. Feature not initialized.");
        }
    }, 150);
});