/**
 * Ginto Messenger Multi-Chat Manager
 * Manages multiple Facebook Messenger-style chat windows
 * Supports responsive layout, persistence, and animations
 */

// Inject messenger theme styles that respond to light/dark mode
(function injectMessengerStyles() {
    if (document.getElementById('messenger-theme-styles')) return;
    const style = document.createElement('style');
    style.id = 'messenger-theme-styles';
    style.textContent = `
        /* Messenger Theme Variables */
        .messenger-bg { background-color: #ffffff; }
        .messenger-bg-secondary { background-color: #f0f2f5; }
        .messenger-bg-input { background-color: #f0f2f5; }
        .messenger-bg-hover:hover { background-color: #f0f2f5; }
        .messenger-bg-active { background-color: #e4e6eb; }
        .messenger-border { border-color: #e4e6eb; }
        .messenger-text { color: #050505; }
        .messenger-text-secondary { color: #65676b; }
        .messenger-text-muted { color: #8a8d91; }
        .messenger-avatar-border { border-color: #ffffff; }
        .messenger-online-border { border-color: #ffffff; }
        .messenger-msg-incoming { background-color: #e4e6eb; color: #050505; }
        .messenger-msg-system { background-color: #e4e6eb; color: #050505; }
        
        .dark .messenger-bg { background-color: #242526; }
        .dark .messenger-bg-secondary { background-color: #3a3b3c; }
        .dark .messenger-bg-input { background-color: #3a3b3c; }
        .dark .messenger-bg-hover:hover { background-color: #3a3b3c; }
        .dark .messenger-bg-active { background-color: #3a3b3c; }
        .dark .messenger-border { border-color: #3e4042; }
        .dark .messenger-text { color: #e4e6eb; }
        .dark .messenger-text-secondary { color: #b0b3b8; }
        .dark .messenger-text-muted { color: #8a8d91; }
        .dark .messenger-avatar-border { border-color: #242526; }
        .dark .messenger-online-border { border-color: #242526; }
        .dark .messenger-msg-incoming { background-color: #3a3b3c; color: #e4e6eb; }
        .dark .messenger-msg-system { background-color: #3a3b3c; color: #e4e6eb; }

        /* Ensure links inside message bubbles are readable on dark backgrounds */
        .messenger-msg-incoming a, .messenger-msg-system a, .dark .messenger-msg-incoming a, .dark .messenger-msg-system a { color: #ffffff !important; font-weight: 600 !important; }
        .message a, .chat-message a, .call-waiting a { color: #ffffff !important; font-weight: 600 !important; }
        .text-blue-400 { color: #ffffff !important; font-weight: 600 !important; }
        
        /* Messenger scrollbar styling */
        .messenger-scroll::-webkit-scrollbar { width: 8px; }
        .messenger-scroll::-webkit-scrollbar-track { background: transparent; }
        .messenger-scroll::-webkit-scrollbar-thumb { background: #65676b; border-radius: 4px; }
        .messenger-scroll::-webkit-scrollbar-thumb:hover { background: #8a8d91; }
    `;
    document.head.appendChild(style);
})();

// Avatar color palette for consistent user colors
const AVATAR_COLORS = [
    'from-blue-500 to-blue-600',
    'from-purple-500 to-purple-600',
    'from-pink-500 to-pink-600',
    'from-green-500 to-green-600',
    'from-yellow-500 to-orange-500',
    'from-red-500 to-red-600',
    'from-indigo-500 to-indigo-600',
    'from-teal-500 to-teal-600',
    'from-cyan-500 to-cyan-600',
    'from-rose-500 to-rose-600',
    'from-amber-500 to-amber-600',
    'from-emerald-500 to-emerald-600'
];

/**
 * Get consistent color for a user based on their ID
 */
function getAvatarColor(userId) {
    const id = parseInt(userId) || 0;
    return AVATAR_COLORS[id % AVATAR_COLORS.length];
}

/**
 * Generate group avatar HTML with stacked member initials
 */
function generateGroupAvatar(participants, size = 12, maxShow = 2) {
    if (!participants || participants.length === 0) {
        const sizeClass = size === 12 ? 'w-12 h-12' : (size === 8 ? 'w-8 h-8' : `w-${size} h-${size}`);
        return `<div class="${sizeClass} rounded-full bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center text-white font-semibold text-lg">G</div>`;
    }

    
    
    const toShow = participants.slice(0, maxShow);
    const sizeClass = size === 12 ? 'w-12 h-12' : (size === 8 ? 'w-8 h-8' : `w-${size} h-${size}`);
    const innerSize = size === 12 ? 'w-8 h-8' : (size === 8 ? 'w-5 h-5' : 'w-6 h-6');
    const textSize = size === 12 ? 'text-xs' : 'text-[10px]';
    const iconSize = size === 12 ? 'w-4 h-4' : 'w-3 h-3';
    
    // Always show stacked style for groups - even with 1 participant, show group icon
    if (toShow.length === 1) {
        const p = toShow[0];
        const name = p.fullname || p.display_name || p.username || '?';
        const initial = name.charAt(0).toUpperCase();
        const color = getAvatarColor(p.id);
        return `
            <div class="${sizeClass} relative">
                <div class="${innerSize} rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white font-semibold ${textSize} absolute top-0 left-0 border-2 messenger-online-border">${initial}</div>
                <div class="${innerSize} rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white absolute bottom-0 right-0 border-2 messenger-online-border">
                    <svg class="${iconSize}" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
            </div>
        `;
    }
    
    // Stacked avatars for 2+ participants
    return `
        <div class="${sizeClass} relative">
            ${toShow.map((p, i) => {
                const name = p.fullname || p.display_name || p.username || '?';
                const initial = name.charAt(0).toUpperCase();
                const color = getAvatarColor(p.id);
                const position = i === 0 ? 'top-0 left-0' : 'bottom-0 right-0';
                return `<div class="${innerSize} rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white font-semibold ${textSize} absolute ${position} border-2 messenger-online-border">${initial}</div>`;
            }).join('')}
        </div>
    `;
}

class MessengerMultiChat {
    constructor(config) {
        this.config = config;
        this.chatWindows = new Map(); // conversationId -> ChatWindow instance
        this.openChats = []; // Array of conversation IDs in order (right to left)
        this.minimizedChats = []; // Array of minimized chat data
        this.conversations = [];
        this.unreadCount = 0;
        this.mainWindowOpen = false;
        
        // Layout constants
        this.CHAT_WIDTH = 328;
        this.CHAT_HEIGHT = 455;
        this.CHAT_GAP = 8;
        this.RIGHT_MARGIN = 16;
        this.BOTTOM_MARGIN = 0;
        this.MINIMIZED_WIDTH = 56;
        this.MINIMIZED_HEIGHT = 56;
        
        // Lazy loading flag - conversations loaded only on first messenger click
        this.conversationsLoaded = false;
        
        // WebSocket connection
        this.ws = null;
        this.wsConnected = false;
        this.wsReconnectAttempts = 0;
        this.wsMaxReconnectAttempts = 5;
        this.wsReconnectDelay = 2000;
        
        // Preload audio files
        this.sounds = {
            ding: new Audio('/assets/audio/ding.mp3'),
            pop: new Audio('/assets/audio/pop.mp3'),
            ring: new Audio('/assets/audio/ring.mp3')
        };
        Object.values(this.sounds).forEach(audio => {
            audio.preload = 'auto';
            audio.volume = 0.5;
        });
        
        // WebRTC call state
        this.currentCall = null;
        this.callTimerInterval = null;
        this.ringtoneContext = null;
        this.ringtoneInterval = null;
        this.pendingIceCandidates = []; // Queue ICE candidates until remote description is set
        
        // Don't request notification permission on load - it's annoying
        // Only show notifications if user has already granted permission
        
        this.init();
    }
    
    // Show browser notification for incoming call (only if already permitted)
    showCallNotification(callerName, callType) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification('Incoming Call', {
                body: `${callerName} is calling you (${callType === 'video' ? 'Video' : 'Voice'} call)`,
                icon: '/assets/img/favicon.png',
                tag: 'incoming-call',
                requireInteraction: true,
                silent: false
            });
            
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
            
            // Store reference to close it when call ends
            this.callNotification = notification;
        }
    }
    
    // Close call notification
    closeCallNotification() {
        if (this.callNotification) {
            this.callNotification.close();
            this.callNotification = null;
        }
    }
    
    getCsrfToken() {
        return window.GINTO_AUTH?.csrfToken || 
               window.GINTO_CONFIG?.csrfToken || 
               window.CSRF_TOKEN || 
               this.config.csrfToken || 
               '';
    }
    
    init() {
        this.createMainContainer();
        this.createMainWindow();
        this.createNewChatModal();
        this.bindGlobalEvents();
        // Don't load conversations on page load - load lazily on first click
        // this.loadConversations();
        this.restorePersistedChats();
        // Connect to WebSocket for real-time updates (replaces polling)
        this.connectWebSocket();
        this.handleResize();
        
        // Listen for resize events
        window.addEventListener('resize', () => this.handleResize());
    }
    
    // Calculate max visible chats based on screen width
    getMaxChats() {
        const screenWidth = window.innerWidth;
        // Reserve space for main messenger button area
        const availableWidth = screenWidth - 100;
        const chatTotalWidth = this.CHAT_WIDTH + this.CHAT_GAP;
        
        // Calculate how many can fit, max 3
        let maxChats = Math.floor(availableWidth / chatTotalWidth);
        maxChats = Math.min(maxChats, 3);
        maxChats = Math.max(maxChats, 1);
        
        // On mobile (< 640px), only allow 1 chat
        if (screenWidth < 640) {
            maxChats = 1;
        } else if (screenWidth < 900) {
            maxChats = Math.min(maxChats, 2);
        }
        
        return maxChats;
    }
    
    handleResize() {
        const maxChats = this.getMaxChats();
        
        // If we have more open chats than allowed, minimize the oldest ones
        while (this.openChats.length > maxChats) {
            const oldestId = this.openChats.shift();
            const chatWindow = this.chatWindows.get(oldestId);
            if (chatWindow) {
                this.minimizeChat(oldestId, false); // Don't persist yet
            }
        }
        
        // Reposition all open chats with animation
        this.repositionAllChats(true);
        this.persistState();
    }
    
    getChatPosition(index) {
        // Position from right to left
        const right = this.RIGHT_MARGIN + (index * (this.CHAT_WIDTH + this.CHAT_GAP));
        return { right, bottom: this.BOTTOM_MARGIN };
    }
    
    repositionAllChats(animate = true) {
        const newChatModal = document.getElementById('messenger-new-chat-modal');
        const isModalOpen = newChatModal && !newChatModal.classList.contains('hidden');
        
        if (!animate) {
            this.openChats.forEach((conversationId, index) => {
                const chatWindow = this.chatWindows.get(conversationId);
                if (chatWindow && chatWindow.element) {
                    const pos = this.getChatPosition(index);
                    chatWindow.element.style.transition = 'none';
                    chatWindow.element.style.right = `${pos.right}px`;
                    chatWindow.element.style.bottom = `${pos.bottom}px`;
                }
            });
            
            if (this.groupComposer && this.groupComposer.element) {
                const pos = this.getChatPosition(this.openChats.length);
                this.groupComposer.element.style.transition = 'none';
                this.groupComposer.element.style.right = `${pos.right}px`;
                this.groupComposer.element.style.bottom = `${pos.bottom}px`;
            }
            
            if (isModalOpen) {
                const pos = this.getChatPosition(this.openChats.length + (this.groupComposer ? 1 : 0));
                newChatModal.style.transition = 'none';
                newChatModal.style.right = `${pos.right}px`;
                newChatModal.style.bottom = `${pos.bottom}px`;
            }
        } else {
            this.openChats.forEach((conversationId) => {
                const chatWindow = this.chatWindows.get(conversationId);
                if (chatWindow && chatWindow.element) {
                    chatWindow.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
                }
            });
            
            if (this.groupComposer && this.groupComposer.element) {
                this.groupComposer.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
            }
            
            if (isModalOpen) {
                newChatModal.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
            }
            
            void this.container.offsetHeight;
            
            this.openChats.forEach((conversationId, index) => {
                const chatWindow = this.chatWindows.get(conversationId);
                if (chatWindow && chatWindow.element) {
                    const pos = this.getChatPosition(index);
                    chatWindow.element.style.right = `${pos.right}px`;
                    chatWindow.element.style.bottom = `${pos.bottom}px`;
                }
            });
            
            if (this.groupComposer && this.groupComposer.element) {
                const pos = this.getChatPosition(this.openChats.length);
                this.groupComposer.element.style.right = `${pos.right}px`;
                this.groupComposer.element.style.bottom = `${pos.bottom}px`;
            }
            
            if (isModalOpen) {
                const pos = this.getChatPosition(this.openChats.length + (this.groupComposer ? 1 : 0));
                newChatModal.style.right = `${pos.right}px`;
                newChatModal.style.bottom = `${pos.bottom}px`;
            }
        }
    }
    
    createMainContainer() {
        const container = document.createElement('div');
        container.id = 'messenger-multi-chat-container';
        document.body.appendChild(container);
        this.container = container;
    }
    
    createMainWindow() {
        const mainWindow = document.createElement('div');
        mainWindow.id = 'messenger-main-window';
        mainWindow.className = 'hidden fixed bottom-0 right-4 w-[328px] h-[455px] messenger-bg rounded-t-lg shadow-2xl flex flex-col overflow-hidden z-[100] transition-all duration-300 ease-out';
        mainWindow.style.fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';
        
        mainWindow.innerHTML = `
            <!-- Header -->
            <div class="flex items-center justify-between px-3 py-2 messenger-bg border-b messenger-border">
                <div class="flex items-center gap-2">
                    <svg class="w-6 h-6 text-[#0084ff]" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2z"/>
                    </svg>
                    <span class="font-semibold messenger-text text-[15px]">Chats</span>
                </div>
                <div class="flex items-center">
                    <a href="/messenger" class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Open full Messenger">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                    </a>
                    <button id="main-new-chat-btn" class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="New message">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button id="main-minimize-btn" class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Minimize">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 13H5v-2h14v2z"/>
                        </svg>
                    </button>
                    <button id="main-close-btn" class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Close">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Search -->
            <div class="p-2">
                <div class="relative">
                    <input type="text" id="main-search-input" 
                        class="w-full messenger-bg-input border-none rounded-full py-2 pl-9 pr-3 text-[13px] messenger-text placeholder-gray-400 dark:placeholder-[#b0b3b8] focus:outline-none"
                        placeholder="Search Messenger">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
            
            <!-- Conversations List -->
            <div id="main-conversations-list" class="flex-1 overflow-y-auto messenger-scroll">
                <div class="flex items-center justify-center py-8 messenger-text-secondary">
                    <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading...
                </div>
            </div>
        `;
        
        this.container.appendChild(mainWindow);
        this.mainWindow = mainWindow;
        
        // Bind main window events
        document.getElementById('main-close-btn').addEventListener('click', () => this.closeMainWindow());
        document.getElementById('main-minimize-btn').addEventListener('click', () => this.closeMainWindow());
        document.getElementById('main-new-chat-btn').addEventListener('click', () => this.openNewChatModal());
        document.getElementById('main-search-input').addEventListener('input', (e) => this.filterConversations(e.target.value));
    }
    
    createNewChatModal() {
        const modal = document.createElement('div');
        modal.id = 'messenger-new-chat-modal';
        modal.className = 'hidden fixed bottom-0 right-4 w-[328px] h-[455px] messenger-bg rounded-t-lg shadow-2xl border messenger-border flex flex-col overflow-hidden z-[100]';
        modal.style.fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';
        
        modal.innerHTML = `
                <div class="flex items-center justify-between p-3 border-b messenger-border">
                    <h3 class="font-semibold messenger-text">New Message</h3>
                    <button id="close-new-chat-modal" class="p-1 messenger-bg-hover rounded-full messenger-text-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-3 py-2 border-b messenger-border">
                    <div class="flex items-center gap-2">
                        <span class="messenger-text-secondary text-[15px]">To:</span>
                        <input type="text" id="new-chat-search-users" 
                            class="flex-1 bg-transparent border-none outline-none text-[13px] messenger-text placeholder-gray-400 dark:placeholder-[#b0b3b8]"
                            placeholder="">
                    </div>
                </div>
                <div id="new-chat-user-results" class="max-h-60 overflow-y-auto messenger-scroll flex-1">
                    <!-- Typeahead results will appear here -->
                </div>`;
        
        this.container.appendChild(modal);
        this.newChatModal = modal;
        
        // Bind modal events
        document.getElementById('close-new-chat-modal').addEventListener('click', () => this.closeNewChatModal());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closeNewChatModal();
        });
        
        // Initialize typeahead for new chat modal
        this.newChatTypeahead = new TypeaheadUserSearch({
            inputElement: document.getElementById('new-chat-search-users'),
            resultsElement: document.getElementById('new-chat-user-results'),
            manager: this,
            mode: 'single', // Single user selection
            onSelect: (user) => {
                // Close modal IMMEDIATELY
                this.closeNewChatModal();
                // Then start the conversation
                this.startConversation(user.id, user.displayName);
            }
        });
    }
    
    bindGlobalEvents() {
        // Header messenger icon click
        const headerLink = document.getElementById('header-messenger-link');
        if (headerLink) {
            headerLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleMainWindow();
            });
        }
        
        // Sidebar messenger item: only bind toggle behavior if it is NOT a link
        const sidebarLink = document.getElementById('open-messenger');
        if (sidebarLink) {
            try {
                const href = (typeof sidebarLink.getAttribute === 'function') ? sidebarLink.getAttribute('href') : null;
                const isAnchorToMessenger = href && href.trim().startsWith('/messenger');
                if (!isAnchorToMessenger) {
                    sidebarLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.toggleMainWindow();
                    });
                }
            } catch (err) {
                // Fallback: if anything goes wrong, bind the toggle to preserve previous behavior
                sidebarLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleMainWindow();
                });
            }
        }
        
        // Mobile messenger button click
        const mobileLink = document.getElementById('mobile-messenger-link');
        if (mobileLink) {
            mobileLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleMainWindow();
            });
        }
    }
    
    toggleMainWindow() {
        if (this.mainWindowOpen) {
            this.closeMainWindow();
        } else {
            this.openMainWindow();
        }
    }
    
    openMainWindow() {
        this.mainWindow.classList.remove('hidden');
        this.mainWindow.style.transform = 'scale(1)';
        this.mainWindow.style.opacity = '1';
        this.mainWindowOpen = true;
        this.persistState();
        
        // Lazy load conversations on first open
        if (!this.conversationsLoaded) {
            this.conversationsLoaded = true;
            this.loadConversations();
            // No polling needed - WebSocket handles real-time updates
        }
    }
    
    closeMainWindow() {
        this.mainWindow.classList.add('hidden');
        this.mainWindowOpen = false;
        this.persistState();
    }
    
    // Create a chat window for a conversation
    // options.restoring = true skips animation and persistence (used during page load restore)
    createChatWindow(conversationId, displayName, otherUserId, options = {}) {
        const isRestoring = options.restoring || false;
        
        // Check if this chat is already minimized - if so, restore it instead
        const minimizedIdx = this.minimizedChats.findIndex(c => c.conversationId === conversationId);
        if (minimizedIdx !== -1) {
            this.restoreMinimizedChat(conversationId);
            return this.chatWindows.get(conversationId);
        }
        
        // Check if already open
        if (this.chatWindows.has(conversationId)) {
            // Bring to front (move to index 0)
            const idx = this.openChats.indexOf(conversationId);
            if (idx > 0) {
                this.openChats.splice(idx, 1);
                this.openChats.unshift(conversationId);
                this.repositionAllChats();
            }
            return this.chatWindows.get(conversationId);
        }
        
        // When restoring, don't minimize existing chats (we're restoring saved state)
        if (!isRestoring) {
            // Check if we need to minimize an old chat
            const maxChats = this.getMaxChats();
            const isMobile = window.innerWidth < 640;
            
            if (this.openChats.length >= maxChats) {
                // On mobile: minimize previous chats instead of just removing them
                // On desktop: keep the rotation behavior (pop oldest, add newest)
                if (isMobile) {
                    // Minimize all open chats on mobile when reaching limit
                    while (this.openChats.length >= maxChats) {
                        const oldestId = this.openChats.shift();
                        this.minimizeChat(oldestId, false); // Don't persist yet
                    }
                } else {
                    const oldestId = this.openChats.pop();
                    this.minimizeChat(oldestId);
                }
            }
        }
        
        const chatWindow = new ChatWindow({
            conversationId,
            displayName,
            otherUserId,
            conversationType: options.conversationType || 'direct',
            participants: options.participants || [],
            memberSince: options.memberSince || null,
            manager: this,
            config: this.config
        });
        
        this.chatWindows.set(conversationId, chatWindow);
        
        // When restoring, push to maintain order; otherwise unshift for newest-first
        if (isRestoring) {
            this.openChats.push(conversationId);
        } else {
            this.openChats.unshift(conversationId);
        }
        
        this.container.appendChild(chatWindow.element);
        
        // Position
        const idx = this.openChats.indexOf(conversationId);
        const pos = this.getChatPosition(idx);
        
        if (isRestoring) {
            // No animation during restore
            chatWindow.element.style.right = `${pos.right}px`;
            chatWindow.element.style.bottom = `${pos.bottom}px`;
            chatWindow.element.style.opacity = '1';
            chatWindow.element.style.transform = 'scale(1)';
        } else {
            // Animate in
            chatWindow.element.style.right = `${pos.right}px`;
            chatWindow.element.style.bottom = `${pos.bottom - 50}px`;
            chatWindow.element.style.opacity = '0';
            chatWindow.element.style.transform = 'scale(0.8)';
            
            requestAnimationFrame(() => {
                chatWindow.element.style.transition = 'all 0.3s ease-out';
                chatWindow.element.style.bottom = `${pos.bottom}px`;
                chatWindow.element.style.opacity = '1';
                chatWindow.element.style.transform = 'scale(1)';
                
                // Reposition OTHER chats (not this one) with animation
                this.openChats.forEach((conversationId, index) => {
                    if (conversationId !== chatWindow.conversationId) {
                        const otherChat = this.chatWindows.get(conversationId);
                        if (otherChat && otherChat.element) {
                            const otherPos = this.getChatPosition(index);
                            otherChat.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
                            otherChat.element.style.right = `${otherPos.right}px`;
                            otherChat.element.style.bottom = `${otherPos.bottom}px`;
                        }
                    }
                });
                
                // Also reposition group composer if it exists
                if (this.groupComposer && this.groupComposer.element) {
                    const groupPos = this.getChatPosition(this.openChats.length - 1);
                    this.groupComposer.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
                    this.groupComposer.element.style.right = `${groupPos.right}px`;
                    this.groupComposer.element.style.bottom = `${groupPos.bottom}px`;
                }
                
                // Also reposition new chat modal if it's visible
                const newChatModal = document.getElementById('messenger-new-chat-modal');
                if (newChatModal && !newChatModal.classList.contains('hidden')) {
                    const modalPos = this.getChatPosition(this.openChats.length + (this.groupComposer ? 1 : 0));
                    newChatModal.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
                    newChatModal.style.right = `${modalPos.right}px`;
                    newChatModal.style.bottom = `${modalPos.bottom}px`;
                }
            });
        }
        
        // Load messages
        chatWindow.loadMessages();
        
        // Only persist if not restoring
        if (!isRestoring) {
            this.persistState();
        }
        
        return chatWindow;
    }
    
    closeChat(conversationId) {
        const chatWindow = this.chatWindows.get(conversationId);
        if (!chatWindow) return;
        
        // Remove from tracking immediately so other chats reposition right away
        this.chatWindows.delete(conversationId);
        const idx = this.openChats.indexOf(conversationId);
        if (idx !== -1) {
            this.openChats.splice(idx, 1);
        }
        
        // Reposition remaining open chats
        this.openChats.forEach((cid) => {
            const chat = this.chatWindows.get(cid);
            if (chat && chat.element) {
                chat.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
            }
        });
        
        // Also set transitions on group composer and new message composer
        if (this.groupComposer && this.groupComposer.element) {
            this.groupComposer.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
        }
        const newChatModal = document.getElementById('messenger-new-chat-modal');
        if (newChatModal && !newChatModal.classList.contains('hidden')) {
            newChatModal.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out, transform 0.3s ease-out';
        }
        
        // Force reflow
        void this.container.offsetHeight;
        
        // Update all positions - they will animate
        this.openChats.forEach((cid, index) => {
            const chat = this.chatWindows.get(cid);
            if (chat && chat.element) {
                const pos = this.getChatPosition(index);
                chat.element.style.right = `${pos.right}px`;
                chat.element.style.bottom = `${pos.bottom}px`;
            }
        });
        
        // Reposition group composer
        if (this.groupComposer && this.groupComposer.element) {
            const pos = this.getChatPosition(this.openChats.length);
            this.groupComposer.element.style.right = `${pos.right}px`;
            this.groupComposer.element.style.bottom = `${pos.bottom}px`;
        }
        
        // Reposition new message composer
        if (newChatModal && !newChatModal.classList.contains('hidden')) {
            const pos = this.getChatPosition(this.openChats.length + (this.groupComposer ? 1 : 0));
            newChatModal.style.right = `${pos.right}px`;
            newChatModal.style.bottom = `${pos.bottom}px`;
        }
        
        // Animate closing chat out
        chatWindow.element.style.transition = 'all 0.2s ease-in';
        chatWindow.element.style.opacity = '0';
        chatWindow.element.style.transform = 'scale(0.8)';
        
        // Remove from DOM after animation completes
        setTimeout(() => {
            chatWindow.element.remove();
            this.persistState();
        }, 200);
    }
    
    minimizeChat(conversationId, persist = true) {
        const chatWindow = this.chatWindows.get(conversationId);
        if (!chatWindow) return;
        
        // Store minimized data
        const minimizedData = {
            conversationId,
            displayName: chatWindow.displayName,
            otherUserId: chatWindow.otherUserId,
            conversationType: chatWindow.conversationType || 'direct'
        };
        this.minimizedChats.push(minimizedData);
        
        // Create minimized indicator in shared container
        this.createMinimizedIndicator(minimizedData);
        
        // Animate and close the chat window
        this.closeChat(conversationId);
        
        if (persist) {
            this.persistState();
        }
    }
    
    // Create minimized chat circle in shared container
    createMinimizedIndicator(chatData) {
        const container = document.getElementById('iframe-minimized-container');
        if (!container) {
            console.warn('[Messenger] Shared minimized container not found');
            return;
        }
        
        const initial = (chatData.displayName || '?')[0].toUpperCase();
        
        const div = document.createElement('div');
        div.id = `messenger-minimized-${chatData.conversationId}`;
        div.className = 'minimized-tab messenger-minimized-tab flex items-center bg-gradient-to-br from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white shadow-lg cursor-pointer';
        div.dataset.conversationId = chatData.conversationId;
        div.innerHTML = `
            <button class="flex items-center justify-center gap-2 p-3 flex-shrink-0">
                <span class="w-5 h-5 flex items-center justify-center text-sm font-bold flex-shrink-0">${initial}</span>
                <span class="tab-title text-sm font-medium whitespace-nowrap">${this.escapeHtml(chatData.displayName)}</span>
            </button>
            <!-- Previously a close button; repurposed to open/restore the minimized chat -->
            <button class="tab-close text-white/70 hover:text-white/90 transition-colors flex-shrink-0 pr-3" title="Open">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        `;
        
        // Click to restore
        div.querySelector('button:first-child').addEventListener('click', () => {
            this.restoreMinimizedChat(chatData.conversationId);
        });
        
        // Repurposed 'close' button: restore/open the minimized chat instead of closing
        div.querySelector('.tab-close').addEventListener('click', (e) => {
            e.stopPropagation();
            this.restoreMinimizedChat(chatData.conversationId);
        });
        
        // Insert at the beginning (bottom of stack visually since flex-col items-end)
        container.insertBefore(div, container.firstChild);
    }
    
    // Restore minimized chat to full window
    restoreMinimizedChat(conversationId) {
        const idx = this.minimizedChats.findIndex(c => c.conversationId === conversationId);
        if (idx === -1) return;
        
        const chatData = this.minimizedChats[idx];
        
        // Remove from minimized array
        this.minimizedChats.splice(idx, 1);
        
        // Remove indicator
        const indicator = document.getElementById(`messenger-minimized-${conversationId}`);
        if (indicator) indicator.remove();
        
        // Create full chat window with conversationType
        this.createChatWindow(chatData.conversationId, chatData.displayName, chatData.otherUserId, {
            conversationType: chatData.conversationType || 'direct'
        });
        
        this.persistState();
    }
    
    // Remove chat from minimized (close completely)
    removeMinimizedChat(conversationId) {
        const idx = this.minimizedChats.findIndex(c => c.conversationId === conversationId);
        if (idx !== -1) {
            this.minimizedChats.splice(idx, 1);
        }
        
        const indicator = document.getElementById(`messenger-minimized-${conversationId}`);
        if (indicator) indicator.remove();
        
        this.persistState();
    }
    
    // Restore all minimized chats from persisted state
    restoreMinimizedIndicators() {
        this.minimizedChats.forEach(chatData => {
            this.createMinimizedIndicator(chatData);
        });
    }
    
    // Archive a conversation
    async archiveChat(conversationId) {
        try {
            const response = await fetch('/messenger/archive', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    csrf_token: this.getCsrfToken()
                })
            });
            const data = await response.json();
            if (data.success) {
                this.closeChat(conversationId);
                this.loadConversations(true);
            } else {
                alert(data.error || 'Failed to archive conversation');
            }
        } catch (error) {
            alert('Failed to archive conversation');
        }
    }
    
    // Delete a conversation
    async deleteChat(conversationId) {
        try {
            const response = await fetch('/messenger/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    csrf_token: this.getCsrfToken()
                })
            });
            const data = await response.json();
            if (data.success) {
                this.closeChat(conversationId);
                this.loadConversations(true);
            } else {
                alert(data.error || 'Failed to delete conversation');
            }
        } catch (error) {
            alert('Failed to delete conversation');
        }
    }
    
    // ==================== WebRTC Calling ====================
    
    // Start a call (audio or video)
    // Supports single-target and group calls. For group calls pass `isGroup=true`
    // or ensure `otherUserId === 'group'` and the server has group members for the conversation.
    async startCall(conversationId, otherUserId, displayName, callType = 'audio', isGroup = false, participants = null) {
        if (this.currentCall) {
            alert('Already in a call');
            return;
        }
        
        // Create call UI
        this.createCallUI(displayName, callType, true, isGroup);
        this.updateCallStatus('Requesting media...');

        // If this is a group call, load participants from argument or server
        let targets = [];
        if (isGroup || otherUserId === 'group' || this.isGroupChat) {
            if (Array.isArray(participants) && participants.length > 0) {
                targets = participants.slice();
            } else {
                try {
                    const resp = await fetch(`/messenger/group-members/${conversationId}`);
                    const data = await resp.json();
                    if (data.success && Array.isArray(data.members)) {
                        targets = data.members.map(m => m.id || m.user_id || m.member_id).filter(Boolean);
                    }
                } catch (e) {
                    console.warn('Failed to load group members for call', e);
                }
            }
            // Exclude self from targets
            const me = (window.GINTO_CONFIG && window.GINTO_CONFIG.userId) || null;
            targets = targets.filter(t => t && parseInt(t) !== parseInt(me));

            // Enforce maximum concurrent participants (including you)
            const MAX_PARTICIPANTS = 10;
            if (targets.length + 1 > MAX_PARTICIPANTS) {
                targets = targets.slice(0, MAX_PARTICIPANTS - 1);
            }

            if (targets.length === 0) {
                alert('No participants available to call in this conversation');
                return;
            }

            // Store call info for group
            this.currentCall = {
                conversationId,
                isGroup: true,
                participants: targets,
                displayName,
                callType,
                isOutgoing: true,
                peerConnections: {}, // targetId -> RTCPeerConnection
                localStream: null,
                remoteStreams: {} // targetId -> MediaStream
            };
        } else {
            // Single target call
            this.currentCall = {
                conversationId,
                otherUserId,
                displayName,
                callType,
                isOutgoing: true,
                peerConnection: null,
                localStream: null,
                remoteStream: null
            };
        }
        
        try {
            // Get user media
            const constraints = {
                audio: true,
                video: callType === 'video'
            };
            
            this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Show local video if video call
            if (callType === 'video') {
                const localVideo = document.getElementById('call-local-video');
                if (localVideo) {
                    localVideo.srcObject = this.currentCall.localStream;
                }
            }
            
            this.updateCallStatus('Calling...');
            
            if (this.currentCall.isGroup) {
                // For each target, create a peer connection, add tracks, and send offer
                for (const targetId of this.currentCall.participants) {
                    try {
                        const pc = await this.createPeerConnectionFor(targetId);
                        this.currentCall.peerConnections[targetId] = pc;

                        // Add local tracks
                        this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

                        // Create and send offer per-target
                        const offer = await pc.createOffer();
                        await pc.setLocalDescription(offer);

                        console.log('Sending group call_offer to user:', targetId, 'callType:', callType);
                        this.wsSend({
                            type: 'call_offer',
                            conversationId: conversationId,
                            targetUserId: targetId,
                            offer: offer,
                            callType: callType,
                            callerName: this.config.userName || 'Someone',
                            isGroup: true
                        });
                    } catch (e) {
                        console.warn('Failed to create offer for target', targetId, e);
                    }
                }
            } else {
                // Single-target flow (unchanged)
                // Create peer connection
                await this.createPeerConnection();

                // Add local tracks to connection
                this.currentCall.localStream.getTracks().forEach(track => {
                    this.currentCall.peerConnection.addTrack(track, this.currentCall.localStream);
                });

                // Create and send offer
                const offer = await this.currentCall.peerConnection.createOffer();
                await this.currentCall.peerConnection.setLocalDescription(offer);

                // Send offer via WebSocket
                console.log('Sending call_offer to user:', otherUserId, 'callType:', callType);
                this.wsSend({
                    type: 'call_offer',
                    conversationId: conversationId,
                    targetUserId: otherUserId,
                    offer: offer,
                    callType: callType,
                    callerName: this.config.userName || 'Someone'
                });
            }
            
            // Play ringtone
            this.playRingtone();
            
            // Set a timeout for no answer (45 seconds)
            this.connectionTimeout = setTimeout(() => {
                if (!this.currentCall || !this.currentCall.isOutgoing) return;

                // For group calls, check per-target peerConnections and only end
                // the call if none are connected or connecting.
                if (this.currentCall.isGroup && this.currentCall.peerConnections) {
                    const pcs = Object.values(this.currentCall.peerConnections);
                    const anyConnecting = pcs.some(pc => pc && (pc.connectionState === 'connected' || pc.connectionState === 'connecting' || pc.iceConnectionState === 'connected'));
                    if (anyConnecting) {
                        console.log('📞 Some participants connecting — keeping call open');
                        return;
                    }
                    console.log('📞 Call timeout (group) - no participants connected');
                    this.updateCallStatus('No answer');
                    setTimeout(() => this.endCall(), 1500);
                    return;
                }

                // Single-target fallback
                const state = this.currentCall.peerConnection?.connectionState;
                if (state !== 'connected') {
                    console.log('📞 Call timeout - no answer');
                    this.updateCallStatus('No answer');
                    setTimeout(() => this.endCall(), 1500);
                }
            }, 45000);
            
        } catch (error) {
            console.error('Error starting call:', error);
            this.updateCallStatus('Failed to access media');
            setTimeout(() => this.endCall(), 2000);
        }
    }
    
    // Create a per-target peer connection for group calls
    async createPeerConnectionFor(targetId) {
        const hostname = window.location.hostname;
        const turnHost = (hostname === 'localhost' || hostname === '127.0.0.1')
            ? '149.28.145.52'
            : hostname.replace(/^www\./, '');

        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                {
                    urls: `turn:${turnHost}:3478`,
                    username: 'ginto',
                    credential: 'ginto-turn-2026'
                },
                {
                    urls: `turn:${turnHost}:3478?transport=tcp`,
                    username: 'ginto',
                    credential: 'ginto-turn-2026'
                }
            ],
            iceCandidatePoolSize: 10
        };

        const pc = new RTCPeerConnection(configuration);

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                console.log('📞 Sending ICE candidate for', targetId);
                this.wsSend({
                    type: 'call_ice',
                    targetUserId: targetId,
                    candidate: event.candidate
                });
            }
        };

        pc.oniceconnectionstatechange = () => {
            const state = pc.iceConnectionState;
            console.log('📞 ICE Connection state for', targetId, state);
        };

        // Handle overall PeerConnection connection state for per-target PCs
        pc.onconnectionstatechange = () => {
            const state = pc.connectionState;
            console.log('📞 Connection state for', targetId, state);
            if (state === 'connected') {
                // If any participant connects, clear the overall connection timeout
                if (this.connectionTimeout) {
                    clearTimeout(this.connectionTimeout);
                    this.connectionTimeout = null;
                }
                this.stopRingtone();
                this.updateCallStatus('Connected');
                this.startCallTimer();
            } else if (state === 'disconnected' || state === 'failed') {
                // If participant failed, remove their connection after a short delay
                setTimeout(() => {
                    try { pc.close(); } catch (e) {}
                    if (this.currentCall && this.currentCall.peerConnections) {
                        delete this.currentCall.peerConnections[targetId];
                    }
                    const audioEl = document.getElementById(`call-remote-audio-${targetId}`);
                    if (audioEl && audioEl.parentNode) audioEl.parentNode.removeChild(audioEl);
                    const videoEl = document.getElementById(`call-remote-video-${targetId}`);
                    if (videoEl && videoEl.parentNode) videoEl.parentNode.removeChild(videoEl);
                }, 1000);
            }
        };

        pc.ontrack = (event) => {
            console.log('📞 Remote track received for', targetId, event.track.kind);
            this.currentCall.remoteStreams = this.currentCall.remoteStreams || {};
            this.currentCall.remoteStreams[targetId] = event.streams[0];

            // If this is a group call, create a per-participant media element
            if (this.currentCall && this.currentCall.isGroup) {
                const container = document.getElementById('call-remote-streams') || (function() {
                    const modal = document.getElementById('call-modal');
                    if (!modal) return null;
                    const wrapper = document.createElement('div');
                    wrapper.id = 'call-remote-streams';
                    wrapper.className = 'flex flex-col items-center gap-2 p-2';
                    // insert before audio bars if present
                    const bars = modal.querySelector('.audio-bar') ? modal.querySelector('.audio-bar').parentNode : null;
                    if (bars && bars.parentNode) bars.parentNode.insertBefore(wrapper, bars);
                    else modal.querySelector('.bg-[#242526]')?.appendChild(wrapper);
                    return document.getElementById('call-remote-streams');
                })();

                if (container) {
                    // Create element per target (video or audio)
                    if (this.currentCall.callType === 'video') {
                        let vid = document.getElementById(`call-remote-video-${targetId}`);
                        if (!vid) {
                            vid = document.createElement('video');
                            vid.id = `call-remote-video-${targetId}`;
                            vid.autoplay = true;
                            vid.playsInline = true;
                            vid.className = 'w-full h-32 object-cover rounded-lg border border-white/20';
                            container.appendChild(vid);
                        }
                        vid.srcObject = event.streams[0];
                        vid.play().catch(() => {});
                    } else {
                        let aud = document.getElementById(`call-remote-audio-${targetId}`);
                        if (!aud) {
                            aud = document.createElement('audio');
                            aud.id = `call-remote-audio-${targetId}`;
                            aud.autoplay = true;
                            aud.playsInline = true;
                            aud.className = 'hidden';
                            container.appendChild(aud);
                        }
                        aud.srcObject = event.streams[0];
                        aud.play().catch(() => {});
                        this.startAudioVisualizer(event.streams[0]);
                    }
                }

                return;
            }

            // Single-target fallback (existing behavior)
            const remoteAudio = document.getElementById('call-remote-audio');
            const remoteVideo = document.getElementById('call-remote-video');
            if (remoteVideo && this.currentCall.callType === 'video') {
                remoteVideo.srcObject = event.streams[0];
            } else if (remoteAudio) {
                remoteAudio.srcObject = event.streams[0];
                remoteAudio.play().catch(e => console.log('Audio autoplay blocked:', e));
                this.startAudioVisualizer(event.streams[0]);
            }
        };

        // If an answer arrived earlier before we created this PC, apply it now
        try {
            const pending = this.pendingAnswers && (this.pendingAnswers[targetId] || this.pendingAnswers[String(targetId)]);
            if (pending) {
                console.log('📞 Applying pending answer for', targetId);
                await pc.setRemoteDescription(new RTCSessionDescription(pending));
                // Remove applied pending answer
                try { delete this.pendingAnswers[targetId]; } catch (e) { delete this.pendingAnswers[String(targetId)]; }
                // Process any ICE candidates queued for this peer
                await this.processPendingIceCandidates();
            }
        } catch (e) {
            console.error('📞 Error applying pending answer for', targetId, e);
        }

        return pc;
    }

    // Create WebRTC peer connection
    async createPeerConnection() {
        // Get TURN server from site config or use defaults
        const hostname = window.location.hostname;
        const turnHost = (hostname === 'localhost' || hostname === '127.0.0.1') 
            ? '149.28.145.52' // Use production TURN for local dev too
            : hostname.replace(/^www\./, '');
        
        const configuration = {
            iceServers: [
                // Google STUN servers (free, but can't relay through NAT)
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                // Our own TURN server (required for mobile/symmetric NAT)
                {
                    urls: `turn:${turnHost}:3478`,
                    username: 'ginto',
                    credential: 'ginto-turn-2026'
                },
                {
                    urls: `turn:${turnHost}:3478?transport=tcp`,
                    username: 'ginto',
                    credential: 'ginto-turn-2026'
                }
            ],
            iceCandidatePoolSize: 10
        };
        
        this.currentCall.peerConnection = new RTCPeerConnection(configuration);
        
        // Handle ICE candidates
        this.currentCall.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                console.log('📞 Sending ICE candidate');
                this.wsSend({
                    type: 'call_ice',
                    targetUserId: this.currentCall.otherUserId,
                    candidate: event.candidate
                });
            }
        };
        
        // Handle ICE connection state
        this.currentCall.peerConnection.oniceconnectionstatechange = () => {
            const state = this.currentCall?.peerConnection?.iceConnectionState;
            console.log('📞 ICE Connection state:', state);
        };
        
        // Handle remote stream
        this.currentCall.peerConnection.ontrack = (event) => {
            console.log('📞 Remote track received:', event.track.kind);
            this.currentCall.remoteStream = event.streams[0];
            const remoteAudio = document.getElementById('call-remote-audio');
            const remoteVideo = document.getElementById('call-remote-video');
            
            if (remoteVideo && this.currentCall.callType === 'video') {
                remoteVideo.srcObject = event.streams[0];
                remoteVideo.play().catch(e => console.log('Video autoplay blocked:', e));
            } else if (remoteAudio) {
                remoteAudio.srcObject = event.streams[0];
                // Ensure audio plays
                remoteAudio.play().catch(e => console.log('Audio autoplay blocked:', e));
                // Start audio visualizer for voice calls
                this.startAudioVisualizer(event.streams[0]);
            }
        };
        
        // Handle connection state changes
        this.currentCall.peerConnection.onconnectionstatechange = () => {
            const state = this.currentCall?.peerConnection?.connectionState;
            console.log('📞 Connection state:', state);
            
            switch (state) {
                case 'connecting':
                    this.updateCallStatus('Connecting...');
                    break;
                case 'connected':
                    // Clear connection timeout
                    if (this.connectionTimeout) {
                        clearTimeout(this.connectionTimeout);
                        this.connectionTimeout = null;
                    }
                    // Cancel any pending end-call timers (transient disconnects)
                    if (this._callEndTimer) { clearTimeout(this._callEndTimer); this._callEndTimer = null; }
                    this.stopRingtone();
                    this.updateCallStatus('Connected');
                    this.startCallTimer();
                    break;
                case 'disconnected':
                case 'failed':
                    // Transient disconnections can occur briefly when the remote
                    // side answers or during ICE restarts. Wait a short grace
                    // period before ending the entire call to avoid premature
                    // 'user left' or 'user busy' symptoms.
                    this.updateCallStatus('Call ended');
                    if (this._callEndTimer) clearTimeout(this._callEndTimer);
                    this._callEndTimer = setTimeout(() => {
                        const state = this.currentCall?.peerConnection?.connectionState;
                        if (!this.currentCall || state === 'disconnected' || state === 'failed') {
                            this.endCall();
                        }
                    }, 3000);
                    break;
            }
        };
    }
    
    // Start audio level visualizer
    startAudioVisualizer(stream) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const analyser = audioContext.createAnalyser();
            const source = audioContext.createMediaStreamSource(stream);
            source.connect(analyser);
            analyser.fftSize = 32;
            
            const dataArray = new Uint8Array(analyser.frequencyBinCount);
            const bars = document.querySelectorAll('.audio-bar');
            
            const updateBars = () => {
                if (!this.currentCall) {
                    audioContext.close();
                    return;
                }
                
                analyser.getByteFrequencyData(dataArray);
                const average = dataArray.reduce((a, b) => a + b, 0) / dataArray.length;
                
                bars.forEach((bar, i) => {
                    const height = Math.max(4, (average / 255) * 24 + Math.random() * 8);
                    bar.style.height = `${height}px`;
                    bar.style.opacity = average > 10 ? '1' : '0.3';
                });
                
                requestAnimationFrame(updateBars);
            };
            
            updateBars();
        } catch (e) {
            console.log('Audio visualizer not supported:', e);
        }
    }
    
    // Handle incoming call
    async handleIncomingCall(data) {
        console.log('📞 Incoming call received:', data);
        const { fromUserId, callerName, offer, callType, isGroup, targetUserId } = data;

        // If we're already in a call, allow special handling for group peer-offers.
        // When in a group call, other participants will send per-peer offers to
        // a joining participant. Rejecting those by default prevents participants
        // from establishing peer-to-peer connections with each other. Handle
        // that case here by creating a per-target RTCPeerConnection and answering.
        if (this.currentCall) {
            const myId = this.config?.userId || (window.GINTO_CONFIG && window.GINTO_CONFIG.userId) || null;

            // Determine whether this incoming offer is a group peer-offer targeted at us
            const intendedTarget = targetUserId || data.to || data.userId || null;
            if (isGroup && this.currentCall.isGroup && myId && intendedTarget && String(intendedTarget) === String(myId)) {
                const from = fromUserId || data.from || data.userId || null;
                if (!from) {
                    console.warn('📞 Group offer missing fromUserId, cannot answer');
                    return;
                }

                try {
                    // Ensure we have local media
                    if (!this.currentCall.localStream) {
                        const constraints = { audio: true, video: this.currentCall.callType === 'video' };
                        this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                        const localVid = document.getElementById('call-local-video');
                        if (localVid) localVid.srcObject = this.currentCall.localStream;
                    }

                    // Create per-target peer connection and add local tracks
                    const pc = await this.createPeerConnectionFor(from);
                    this.currentCall.peerConnections = this.currentCall.peerConnections || {};
                    this.currentCall.peerConnections[from] = pc;
                    this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

                    // Accept remote offer and send answer back
                    await pc.setRemoteDescription(new RTCSessionDescription(offer));
                    await this.processPendingIceCandidates();
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);

                    this.wsSend({ type: 'call_answer', targetUserId: from, answer: answer, conversationId: this.currentCall.conversationId || data.conversationId });
                    console.log('📞 Answered group peer-offer from', from);
                } catch (e) {
                    console.error('Error answering group peer offer:', e);
                }

                return;
            }

            // Default behavior: already busy in another call
            this.wsSend({
                type: 'call_end',
                targetUserId: fromUserId,
                reason: 'busy'
            });
            return;
        }

        // Store call info for the initial incoming offer (not already in a call)
        this.currentCall = {
            otherUserId: fromUserId,
            displayName: callerName,
            callType,
            isOutgoing: false,
            peerConnection: null,
            localStream: null,
            remoteStream: null,
            offer: offer,
            isGroup: !!isGroup,
            conversationId: data.conversationId || data.conversation_id || null
        };
        
        // Show browser notification if page is not focused
        if (document.hidden || !document.hasFocus()) {
            this.showCallNotification(callerName, callType);
        }
        
        // Create incoming call UI
        this.createIncomingCallUI(callerName, callType);
        this.playRingtone();
    }
    
    // Accept incoming call
    async acceptCall() {
        if (!this.currentCall || !this.currentCall.offer) return;
        
        // Close browser notification
        this.closeCallNotification();
        
        const modal = document.getElementById('call-modal');
        if (modal) modal.remove();
        
        // Create call UI (active call)
        this.createCallUI(this.currentCall.displayName, this.currentCall.callType, false, this.currentCall.isGroup);
        this.updateCallStatus('Connecting...');
        
        // Set a connection timeout - end call if not connected in 30 seconds
        this.connectionTimeout = setTimeout(() => {
            if (this.currentCall && this.currentCall.peerConnection) {
                const state = this.currentCall.peerConnection.connectionState;
                if (state !== 'connected') {
                    console.log('📞 Connection timeout - state:', state);
                    this.updateCallStatus('Connection failed');
                    setTimeout(() => this.endCall(), 1500);
                }
            }
        }, 30000);
        
        try {
            // Get user media
            const constraints = {
                audio: true,
                video: this.currentCall.callType === 'video'
            };
            
            this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Show local video if video call
            if (this.currentCall.callType === 'video') {
                const localVideo = document.getElementById('call-local-video');
                if (localVideo) {
                    localVideo.srcObject = this.currentCall.localStream;
                }
            }
            
            // Create peer connection
            await this.createPeerConnection();
            
            // Add local tracks
            this.currentCall.localStream.getTracks().forEach(track => {
                this.currentCall.peerConnection.addTrack(track, this.currentCall.localStream);
            });
            
            // Set remote description (the offer)
            await this.currentCall.peerConnection.setRemoteDescription(
                new RTCSessionDescription(this.currentCall.offer)
            );
            
            // Process any ICE candidates that arrived before the offer was set
            await this.processPendingIceCandidates();
            
            // Create and send answer
            const answer = await this.currentCall.peerConnection.createAnswer();
            await this.currentCall.peerConnection.setLocalDescription(answer);
            
            console.log('📞 Sending call_answer to:', this.currentCall.otherUserId);
            const sent = this.wsSend({
                type: 'call_answer',
                targetUserId: this.currentCall.otherUserId,
                answer: answer
            });
            console.log('📞 call_answer sent:', sent);
            
            this.stopRingtone();
            
            // Notify other participants that we've joined so they can establish peer connections
            if (this.currentCall?.conversationId) {
                this.wsSend({ type: 'call_join', conversationId: this.currentCall.conversationId, joiningUserId: this.config.userId });
            } else {
                this.wsSend({ type: 'call_join', joiningUserId: this.config.userId });
            }

            // Fallback: if server does not broadcast `call_join` (no restart possible),
            // proactively create per-peer offers to group members so they will answer.
            // This allows joining clients to initiate connections without server changes.
            if (this.currentCall?.isGroup && this.currentCall.conversationId) {
                (async () => {
                    try {
                        // Small delay to let other participants settle their state
                        await new Promise(r => setTimeout(r, 350));

                        // Fetch group members from server (same endpoint used when starting a call)
                        const resp = await fetch(`/messenger/group-members/${this.currentCall.conversationId}`);
                        const data = await resp.json();
                        if (!data.success || !Array.isArray(data.members)) return;

                        const me = String(this.config.userId || (window.GINTO_CONFIG && window.GINTO_CONFIG.userId) || '');
                        const targets = data.members.map(m => String(m.id || m.user_id || m.member_id)).filter(id => id && id !== me);

                        // If there are no targets, no-op
                        if (targets.length === 0) return;

                        // Ensure local media is available
                        if (!this.currentCall.localStream) {
                            const constraints = { audio: true, video: this.currentCall.callType === 'video' };
                            this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                            const localVid = document.getElementById('call-local-video');
                            if (localVid) localVid.srcObject = this.currentCall.localStream;
                        }

                        for (const targetId of targets) {
                            // Skip if we already have a PC for them
                            if (this.currentCall.peerConnections && this.currentCall.peerConnections[targetId]) continue;

                            try {
                                const pc = await this.createPeerConnectionFor(targetId);
                                this.currentCall.peerConnections = this.currentCall.peerConnections || {};
                                this.currentCall.peerConnections[targetId] = pc;
                                this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

                                const offer = await pc.createOffer();
                                await pc.setLocalDescription(offer);

                                this.wsSend({ type: 'call_offer', targetUserId: targetId, conversationId: this.currentCall.conversationId, offer: offer, callType: this.currentCall.callType, callerName: this.config.userName || 'Someone', isGroup: true });
                                console.log('📞 (fallback) Sent proactive offer to', targetId);
                            } catch (e) {
                                console.warn('📞 Failed to send proactive offer to', targetId, e);
                            }
                        }
                    } catch (e) {
                        console.warn('📞 Proactive group-offer fallback failed:', e);
                    }
                })();
            }
            
        } catch (error) {
            console.error('Error accepting call:', error);
            this.updateCallStatus('Failed to connect');
            setTimeout(() => this.endCall(), 2000);
        }
    }
    
    // Handle call answer (for caller)
    async handleCallAnswer(data) {
        console.log('📞 Received call_answer from:', data.fromUserId);
        if (!this.currentCall) {
            console.log('📞 No current call for answer');
            return;
        }

        try {
            console.log('📞 Setting remote description from answer');
            if (this.currentCall.isGroup) {
                const from = data.fromUserId;
                const pc = this.currentCall.peerConnections && this.currentCall.peerConnections[from];
                if (!pc) {
                    console.warn('📞 No peerConnection for answer from', from);
                    // store pending answer for later
                    this.pendingAnswers = this.pendingAnswers || {};
                    this.pendingAnswers[from] = data.answer;
                    return;
                }
                await pc.setRemoteDescription(new RTCSessionDescription(data.answer));
            } else {
                if (!this.currentCall.peerConnection) {
                    console.log('📞 No current peerConnection for answer');
                    return;
                }
                await this.currentCall.peerConnection.setRemoteDescription(new RTCSessionDescription(data.answer));
            }

            console.log('📞 Remote description set successfully');
            // Now process any ICE candidates that arrived before the answer
            await this.processPendingIceCandidates();
            this.stopRingtone();
        } catch (error) {
            console.error('Error handling call answer:', error);
        }
    }
    
    // Handle ICE candidate
    async handleCallIce(data) {
        console.log('📞 Received ICE candidate from:', data.fromUserId);
        
        if (!this.currentCall) {
            console.log('📞 No current call for ICE candidate - ignoring');
            return;
        }
        
        // If group call, route candidate to appropriate peerConnection
        if (this.currentCall.isGroup) {
            const from = data.fromUserId;
            const pc = this.currentCall.peerConnections && this.currentCall.peerConnections[from];
            if (!pc || !pc.remoteDescription) {
                console.log('📞 Queuing ICE candidate (group) - remote description not set yet for', from);
                this.pendingIceCandidates.push({ candidate: data.candidate, fromUserId: from });
                return;
            }

            try {
                await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
                console.log('📞 ICE candidate added successfully for', from);
            } catch (error) {
                console.error('Error adding ICE candidate (group):', error);
            }
            return;
        }

        // Single-target call handling
        if (!this.currentCall.peerConnection || !this.currentCall.peerConnection.remoteDescription) {
            console.log('📞 Queuing ICE candidate - remote description not set yet');
            this.pendingIceCandidates.push(data.candidate);
            return;
        }

        try {
            await this.currentCall.peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate));
            console.log('📞 ICE candidate added successfully');
        } catch (error) {
            console.error('Error adding ICE candidate:', error);
        }
    }
    
    // Process queued ICE candidates after remote description is set
    async processPendingIceCandidates() {
        if (!this.currentCall) return;

        console.log(`📞 Processing ${this.pendingIceCandidates.length} pending ICE candidates`);

        // Keep candidates we can't apply yet and try to apply the rest
        const remaining = [];

        while (this.pendingIceCandidates.length > 0) {
            const item = this.pendingIceCandidates.shift();
            try {
                if (item && item.fromUserId) {
                    // group candidate object
                    const pc = this.currentCall.peerConnections && this.currentCall.peerConnections[item.fromUserId];
                    // Only add candidate if PC exists and remote description is set
                    if (pc && pc.remoteDescription) {
                        await pc.addIceCandidate(new RTCIceCandidate(item.candidate));
                        console.log('📞 Queued ICE candidate added for', item.fromUserId);
                    } else {
                        // Defer candidate until peer connection/remote description is ready
                        remaining.push(item);
                        console.log('📞 Deferring ICE candidate for', item.fromUserId);
                    }
                } else {
                    // single candidate string
                    if (this.currentCall.peerConnection && this.currentCall.peerConnection.remoteDescription) {
                        await this.currentCall.peerConnection.addIceCandidate(new RTCIceCandidate(item));
                        console.log('📞 Queued ICE candidate added');
                    } else {
                        remaining.push(item);
                        console.log('📞 Deferring single-target ICE candidate');
                    }
                }
            } catch (error) {
                console.error('Error adding queued ICE candidate:', error);
            }
        }

        // Re-queue any deferred candidates
        this.pendingIceCandidates = remaining;
    }

    // Handle notification that a participant joined the call (create offer to new joiner)
    async handleCallJoin(data) {
        console.log('📞 call_join received:', data);
        const joiningUserId = data.joiningUserId || data.userId || data.user_id || null;
        const conversationId = data.conversationId || data.conversation_id || null;
        if (!joiningUserId) return;

        // If we're not in a call or not part of the same conversation, ignore
        if (!this.currentCall) return;
        if (conversationId && this.currentCall.conversationId && conversationId !== this.currentCall.conversationId) return;

        // If already have a tile/pc for this user, skip
        if (this.currentCall.peerConnections && this.currentCall.peerConnections[joiningUserId]) return;

        try {
            // Ensure local media exists
            if (!this.currentCall.localStream) {
                const constraints = { audio: true, video: this.currentCall.callType === 'video' };
                this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                const localVid = document.getElementById('call-local-video');
                if (localVid) localVid.srcObject = this.currentCall.localStream;
            }

            // Create peer connection and initiate offer to the joining user
            const pc = await this.createPeerConnectionFor(joiningUserId);
            if (!this.currentCall.peerConnections) this.currentCall.peerConnections = {};
            this.currentCall.peerConnections[joiningUserId] = pc;
            this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            this.wsSend({ type: 'call_offer', targetUserId: joiningUserId, conversationId: this.currentCall.conversationId || conversationId, offer: offer, callType: this.currentCall.callType, callerName: this.config.userName || 'Someone', isGroup: true });
            console.log('📞 Sent offer to joining user', joiningUserId);
        } catch (e) {
            console.error('Error creating offer to joining participant:', e);
        }
    }
    
    // Handle call ended by other party
    handleCallEnded(data) {
        // Ignore stray call_end messages if we're not in a call
        if (!this.currentCall) {
            console.debug('Ignored call_end in multi-chat: no active call', data);
            return;
        }

        this.stopRingtone();
        // If this is a group call, a participant may have left or declined
        if (this.currentCall && this.currentCall.isGroup && data.fromUserId) {
            const from = data.fromUserId;
            // Close and remove per-target peer connection
            if (this.currentCall.peerConnections && this.currentCall.peerConnections[from]) {
                try { this.currentCall.peerConnections[from].close(); } catch (e) {}
                delete this.currentCall.peerConnections[from];
            }

            // Remove remote media element if present
            const audioEl = document.getElementById(`call-remote-audio-${from}`);
            if (audioEl && audioEl.parentNode) audioEl.parentNode.removeChild(audioEl);
            const videoEl = document.getElementById(`call-remote-video-${from}`);
            if (videoEl && videoEl.parentNode) videoEl.parentNode.removeChild(videoEl);

            // Remove participant from list
            if (Array.isArray(this.currentCall.participants)) {
                this.currentCall.participants = this.currentCall.participants.filter(p => String(p) !== String(from));
            }

            this.updateCallStatus('Participant left');
            return;
        }

        // If a target is specified and it's not related to our call, ignore it
        const target = data.targetUserId || data.target || data.fromUserId || null;
        if (target) {
            const matchesTarget = String(target) === String(this.currentCall.otherUserId) || String(target) === String(this.currentCall.conversationId) || (this.currentCall.participants && this.currentCall.participants.includes(target));
            if (!matchesTarget) {
                console.debug('Ignored call_end: not relevant to current multi-call', data);
                return;
            }
        }

        this.updateCallStatus(data.reason === 'busy' ? 'User is busy' : 'Call ended');
        setTimeout(() => this.endCall(), 1500);
    }
    
    // Handle call unavailable (user offline)
    handleCallUnavailable(data) {
        this.stopRingtone();
        // For group calls, remove the unavailable participant only
        if (this.currentCall && this.currentCall.isGroup && data.fromUserId) {
            const from = data.fromUserId;
            if (this.currentCall.peerConnections && this.currentCall.peerConnections[from]) {
                try { this.currentCall.peerConnections[from].close(); } catch (e) {}
                delete this.currentCall.peerConnections[from];
            }
            const audioEl = document.getElementById(`call-remote-audio-${from}`);
            if (audioEl && audioEl.parentNode) audioEl.parentNode.removeChild(audioEl);
            const videoEl = document.getElementById(`call-remote-video-${from}`);
            if (videoEl && videoEl.parentNode) videoEl.parentNode.removeChild(videoEl);
            if (Array.isArray(this.currentCall.participants)) {
                this.currentCall.participants = this.currentCall.participants.filter(p => String(p) !== String(from));
            }
            this.updateCallStatus('Participant unavailable');
            return;
        }

        this.updateCallStatus('User is offline');
        setTimeout(() => this.endCall(), 2000);
    }
    
    // Create call UI
    createCallUI(displayName, callType, isOutgoing, isGroup = false) {
        const existingModal = document.getElementById('call-modal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/90 flex items-center justify-center z-[1000]';
        modal.id = 'call-modal';
        
        let videoSection;
        if (callType === 'video' && isGroup) {
            videoSection = `
                <div id="call-remote-streams" class="grid grid-cols-2 gap-2 mb-4">
                    <video id="call-local-video" class="w-full h-32 object-cover rounded-lg border-2 border-white/30" autoplay playsinline muted></video>
                </div>
            `;
        } else if (callType === 'video') {
            videoSection = `
                <div class="relative w-full h-64 bg-black rounded-lg overflow-hidden mb-4">
                    <video id="call-remote-video" class="w-full h-full object-cover" autoplay playsinline></video>
                    <video id="call-local-video" class="absolute bottom-2 right-2 w-24 h-18 rounded-lg object-cover border-2 border-white/30" autoplay playsinline muted></video>
                </div>
            `;
        } else {
            videoSection = `
                <div id="call-remote-streams" class="flex flex-col items-center gap-2">
                    <audio id="call-remote-audio" autoplay playsinline></audio>
                </div>
                <!-- Audio level indicator -->
                <div class="flex items-center justify-center gap-1 my-4">
                    <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 8px;"></div>
                    <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 12px;"></div>
                    <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 16px;"></div>
                    <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 12px;"></div>
                    <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 8px;"></div>
                </div>
            `;
        }
        
        modal.innerHTML = `
            <div class="bg-[#242526] rounded-2xl w-[360px] shadow-2xl overflow-hidden">
                <!-- Call Header -->
                <div class="bg-gradient-to-br from-blue-600 to-purple-700 p-6 text-center">
                    ${callType !== 'video' ? `
                    <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-2xl font-bold shadow-lg mb-4">
                        ${(displayName || '?')[0].toUpperCase()}
                    </div>
                    ` : ''}
                    <h3 class="text-white text-lg font-semibold">${this.escapeHtml(displayName)}</h3>
                    <p class="text-blue-100 text-sm mt-1 call-status">${isOutgoing ? 'Calling...' : 'Incoming call'}</p>
                    <p class="text-blue-200 text-xs mt-1 call-timer hidden">00:00</p>
                </div>
                
                ${videoSection}
                
                <!-- Call Actions -->
                <div class="p-6 flex justify-center gap-4">
                    <button class="call-mute-btn w-14 h-14 rounded-full bg-[#3a3b3c] hover:bg-[#4a4b4c] flex items-center justify-center transition-colors" title="Mute">
                        <svg class="w-6 h-6 text-white mic-on" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                        </svg>
                        <svg class="w-6 h-6 text-red-400 mic-off hidden" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 11h-1.7c0 .74-.16 1.43-.43 2.05l1.23 1.23c.56-.98.9-2.09.9-3.28zm-4.02.17c0-.06.02-.11.02-.17V5c0-1.66-1.34-3-3-3S9 3.34 9 5v.18l5.98 5.99zM4.27 3L3 4.27l6.01 6.01V11c0 1.66 1.33 3 2.99 3 .22 0 .44-.03.65-.08l1.66 1.66c-.71.33-1.5.52-2.31.52-2.76 0-5.3-2.1-5.3-5.1H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c.91-.13 1.77-.45 2.54-.9L19.73 21 21 19.73 4.27 3z"/>
                        </svg>
                    </button>
                    ${callType === 'video' ? `
                    <button class="call-camera-btn w-14 h-14 rounded-full bg-[#3a3b3c] hover:bg-[#4a4b4c] flex items-center justify-center transition-colors" title="Camera">
                        <svg class="w-6 h-6 text-white cam-on" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                        </svg>
                        <svg class="w-6 h-6 text-red-400 cam-off hidden" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M21 6.5l-4 4V7c0-.55-.45-1-1-1H9.82L21 17.18V6.5zM3.27 2L2 3.27 4.73 6H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.21 0 .39-.08.54-.18L19.73 21 21 19.73 3.27 2z"/>
                        </svg>
                    </button>
                    ` : ''}
                    <button class="call-end-btn w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 flex items-center justify-center transition-colors" title="End call">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.71l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.74-1.69-1.36-2.67-1.85-.33-.16-.56-.5-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Bind button events
        modal.querySelector('.call-end-btn').addEventListener('click', () => this.endCall());
        modal.querySelector('.call-mute-btn').addEventListener('click', () => this.toggleMute());
        
        const cameraBtn = modal.querySelector('.call-camera-btn');
        if (cameraBtn) {
            cameraBtn.addEventListener('click', () => this.toggleCamera());
        }
    }
    
    // Create incoming call UI
    createIncomingCallUI(callerName, callType) {
        const existingModal = document.getElementById('call-modal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/90 flex items-center justify-center z-[1000]';
        modal.id = 'call-modal';
        
        modal.innerHTML = `
            <div class="bg-[#242526] rounded-2xl w-[320px] shadow-2xl overflow-hidden animate-pulse">
                <div class="bg-gradient-to-br from-green-600 to-blue-700 p-8 text-center">
                    <div class="w-24 h-24 mx-auto rounded-full bg-gradient-to-br from-green-400 to-blue-500 flex items-center justify-center text-white text-3xl font-bold shadow-lg mb-4 ring-4 ring-white/30 animate-bounce">
                        ${(callerName || '?')[0].toUpperCase()}
                    </div>
                    <h3 class="text-white text-xl font-semibold">${this.escapeHtml(callerName)}</h3>
                    <p class="text-green-100 text-sm mt-2">${callType === 'video' ? 'Video' : 'Voice'} call...</p>
                </div>
                <div class="p-6 flex justify-center gap-8">
                    <button class="call-decline-btn w-16 h-16 rounded-full bg-red-600 hover:bg-red-700 flex items-center justify-center transition-all transform hover:scale-110" title="Decline">
                        <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.71l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.74-1.69-1.36-2.67-1.85-.33-.16-.56-.5-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                        </svg>
                    </button>
                    <button class="call-accept-btn w-16 h-16 rounded-full bg-green-600 hover:bg-green-700 flex items-center justify-center transition-all transform hover:scale-110" title="Accept">
                        <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        modal.querySelector('.call-decline-btn').addEventListener('click', () => {
            this.wsSend({
                type: 'call_end',
                targetUserId: this.currentCall.otherUserId,
                reason: 'declined'
            });
            this.endCall();
        });
        
        modal.querySelector('.call-accept-btn').addEventListener('click', () => this.acceptCall());
    }
    
    // Toggle mute
    toggleMute() {
        if (!this.currentCall?.localStream) return;
        
        const audioTrack = this.currentCall.localStream.getAudioTracks()[0];
        if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            
            const btn = document.querySelector('.call-mute-btn');
            if (btn) {
                btn.querySelector('.mic-on').classList.toggle('hidden', !audioTrack.enabled);
                btn.querySelector('.mic-off').classList.toggle('hidden', audioTrack.enabled);
                btn.classList.toggle('bg-red-600', !audioTrack.enabled);
                btn.classList.toggle('bg-[#3a3b3c]', audioTrack.enabled);
            }
        }
    }
    
    // Toggle camera
    toggleCamera() {
        if (!this.currentCall?.localStream) return;
        
        const videoTrack = this.currentCall.localStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            
            const btn = document.querySelector('.call-camera-btn');
            if (btn) {
                btn.querySelector('.cam-on').classList.toggle('hidden', !videoTrack.enabled);
                btn.querySelector('.cam-off').classList.toggle('hidden', videoTrack.enabled);
                btn.classList.toggle('bg-red-600', !videoTrack.enabled);
                btn.classList.toggle('bg-[#3a3b3c]', videoTrack.enabled);
            }
        }
    }
    
    // Update call status text
    updateCallStatus(status) {
        const statusEl = document.querySelector('.call-status');
        if (statusEl) {
            statusEl.textContent = status;
        }
    }
    
    // Start call timer
    startCallTimer() {
        const timerEl = document.querySelector('.call-timer');
        if (!timerEl) return;
        
        timerEl.classList.remove('hidden');
        let seconds = 0;
        
        this.callTimerInterval = setInterval(() => {
            seconds++;
            const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
            const secs = (seconds % 60).toString().padStart(2, '0');
            timerEl.textContent = `${mins}:${secs}`;
        }, 1000);
    }
    
    // End the current call
    endCall() {
        // Close browser notification
        this.closeCallNotification();
        
        // Clear connection timeout
        if (this.connectionTimeout) {
            clearTimeout(this.connectionTimeout);
            this.connectionTimeout = null;
        }
        
        if (this.currentCall) {
            // Notify others: group or single
            if (this.currentCall.isGroup) {
                this.wsSend({ type: 'call_end', conversationId: this.currentCall.conversationId, isGroup: true, reason: 'ended' });
            } else if (this.currentCall.otherUserId) {
                this.wsSend({ type: 'call_end', targetUserId: this.currentCall.otherUserId, reason: 'ended' });
            }

            // Stop local stream
            if (this.currentCall.localStream) {
                this.currentCall.localStream.getTracks().forEach(track => track.stop());
            }

            // Close peer connections
            if (this.currentCall.isGroup && this.currentCall.peerConnections) {
                Object.values(this.currentCall.peerConnections).forEach(pc => {
                    try { pc.close(); } catch (e) {}
                });
            } else if (this.currentCall.peerConnection) {
                try { this.currentCall.peerConnection.close(); } catch (e) {}
            }

            this.currentCall = null;
        }
        
        // Clear timer
        if (this.callTimerInterval) {
            clearInterval(this.callTimerInterval);
            this.callTimerInterval = null;
        }

        // Clear any pending end-call timers
        if (this._callEndTimer) {
            clearTimeout(this._callEndTimer);
            this._callEndTimer = null;
        }
        
        // Remove modal
        const modal = document.getElementById('call-modal');
        if (modal) modal.remove();
        
        this.stopRingtone();
    }
    
    // Play ringtone using Web Audio API
    playRingtone() {
        if (this.ringtoneContext) return;
        
        try {
            this.ringtoneContext = new (window.AudioContext || window.webkitAudioContext)();
            this.ringtoneGain = this.ringtoneContext.createGain();
            this.ringtoneGain.gain.value = 0.3;
            this.ringtoneGain.connect(this.ringtoneContext.destination);
            
            const playTone = () => {
                if (!this.ringtoneContext) return;
                
                const osc = this.ringtoneContext.createOscillator();
                osc.type = 'sine';
                osc.frequency.value = 440;
                osc.connect(this.ringtoneGain);
                osc.start();
                osc.stop(this.ringtoneContext.currentTime + 0.5);
                
                setTimeout(() => {
                    if (!this.ringtoneContext) return;
                    const osc2 = this.ringtoneContext.createOscillator();
                    osc2.type = 'sine';
                    osc2.frequency.value = 480;
                    osc2.connect(this.ringtoneGain);
                    osc2.start();
                    osc2.stop(this.ringtoneContext.currentTime + 0.5);
                }, 600);
            };
            
            playTone();
            this.ringtoneInterval = setInterval(playTone, 2000);
        } catch (e) {
            console.log('Could not play Web Audio ringtone, trying fallback:', e);
            // Fallback to preloaded audio file
            try {
                if (this.sounds.ring) {
                    this.sounds.ring.loop = true;
                    this.sounds.ring.currentTime = 0;
                    this.sounds.ring.play().catch(() => {});
                }
            } catch (e2) {
                console.log('Fallback ringtone also failed:', e2);
            }
        }
    }
    
    // Stop ringtone
    stopRingtone() {
        if (this.ringtoneInterval) {
            clearInterval(this.ringtoneInterval);
            this.ringtoneInterval = null;
        }
        if (this.ringtoneContext) {
            try {
                this.ringtoneContext.close();
            } catch (e) {}
            this.ringtoneContext = null;
        }
        // Also stop fallback audio
        if (this.sounds.ring) {
            this.sounds.ring.pause();
            this.sounds.ring.currentTime = 0;
            this.sounds.ring.loop = false;
        }
    }
    
    // Open a conversation (from list or user search)
    async openConversation(conversationId, displayName, otherUserId, conversationType = 'direct', participants = [], memberSince = null) {
        this.closeMainWindow();
        this.closeNewChatModal();
        this.createChatWindow(conversationId, displayName, otherUserId, { conversationType, participants, memberSince });
    }
    
    // Start new conversation
    async startConversation(userId, displayName) {
        try {
            const response = await fetch('/messenger/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    user_id: userId,
                    csrf_token: this.getCsrfToken()
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Open the conversation
                this.openConversation(data.conversation_id, displayName, userId, 'direct');
                this.loadConversations(); // Refresh list
            } else {
                alert(data.error || 'Failed to start conversation');
            }
        } catch (error) {
            alert('Failed to start conversation');
        }
    }
    
    async loadConversations(silent = false) {
        const container = document.getElementById('main-conversations-list');
        
        try {
            const response = await fetch('/messenger/conversations');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Check if conversations changed before re-rendering
                const oldJson = JSON.stringify(this.conversations.map(c => ({ id: c.id, unread: c.unread_count, last: c.last_message })));
                const newJson = JSON.stringify(data.conversations.map(c => ({ id: c.id, unread: c.unread_count, last: c.last_message })));
                
                if (!silent || oldJson !== newJson) {
                    this.conversations = data.conversations;
                    this.renderConversationsList();
                }
            } else {
                // API returned error
                if (!silent && container) {
                    container.innerHTML = `
                        <div class="text-center py-8 messenger-text-secondary">
                            <p class="text-[13px]">Failed to load conversations</p>
                            <button onclick="window.messengerMultiChat?.loadConversations()" class="mt-2 text-[#0084ff] text-[13px] hover:underline">Retry</button>
                        </div>
                    `;
                }
            }
        } catch (error) {
            if (!silent) {
                console.error('Failed to load conversations:', error);
                if (container) {
                    container.innerHTML = `
                        <div class="text-center py-8 messenger-text-secondary">
                            <p class="text-[13px]">Failed to load conversations</p>
                            <button onclick="window.messengerMultiChat?.loadConversations()" class="mt-2 text-[#0084ff] text-[13px] hover:underline">Retry</button>
                        </div>
                    `;
                }
            }
        }
    }
    
    renderConversationsList() {
        const container = document.getElementById('main-conversations-list');
        
        if (this.conversations.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 messenger-text-secondary">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2z"/>
                    </svg>
                    <p class="text-[13px]">No conversations yet</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.conversations.map(conv => {
            // API returns 'display_name' not 'other_user_name'
            const displayName = conv.display_name || conv.other_user_name || 'Unknown';
            const isUnread = parseInt(conv.unread_count) > 0;
            const isOnline = conv.is_online;
            const isGroup = conv.type === 'group';
            const participants = conv.participants || [];
            
            // Generate avatar - different for group vs direct
            let avatarHtml;
            if (isGroup && participants.length > 0) {
                avatarHtml = generateGroupAvatar(participants, 12, 2);
            } else {
                const initial = displayName[0].toUpperCase();
                const color = conv.other_user_id ? getAvatarColor(conv.other_user_id) : 'from-blue-500 to-purple-600';
                avatarHtml = `<div class="w-12 h-12 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white font-semibold text-lg">${initial}</div>`;
            }
            
            return `
                <div class="conversation-item flex items-center gap-3 px-2 py-2 messenger-bg-hover cursor-pointer rounded-lg mx-1 transition-colors"
                     data-conversation-id="${conv.id}"
                     data-display-name="${this.escapeHtml(displayName)}"
                     data-other-user-id="${conv.other_user_id || ''}"
                     data-type="${conv.type || 'direct'}"
                     data-member-since="${conv.member_since || ''}"
                     data-participants='${JSON.stringify(participants)}'>
                    <div class="relative flex-shrink-0">
                        ${avatarHtml}
                        ${isOnline && !isGroup ? `<div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 rounded-full border-2 messenger-online-border"></div>` : ''}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold messenger-text text-[15px] truncate ${isUnread ? '' : 'font-normal'}">${this.escapeHtml(displayName)}</span>
                            <span class="text-[11px] messenger-text-secondary flex-shrink-0">${this.formatTime(conv.last_message_at)}</span>
                        </div>
                        <p class="text-[13px] messenger-text-secondary truncate ${isUnread ? 'messenger-text font-semibold' : ''}">${this.escapeHtml(conv.last_message || 'No messages yet')}</p>
                    </div>
                    ${isUnread ? `<div class="w-3 h-3 bg-[#0084ff] rounded-full flex-shrink-0"></div>` : ''}
                </div>
            `;
        }).join('');
        
        // Bind click events
        container.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', () => {
                let participants = [];
                try {
                    participants = JSON.parse(item.dataset.participants || '[]');
                } catch (e) {}
                this.openConversation(
                    parseInt(item.dataset.conversationId),
                    item.dataset.displayName,
                    parseInt(item.dataset.otherUserId) || null,
                    item.dataset.type || 'direct',
                    participants,
                    item.dataset.memberSince || null
                );
            });
        });
    }
    
    filterConversations(query) {
        const items = document.querySelectorAll('#main-conversations-list .conversation-item');
        const lowerQuery = query.toLowerCase();
        
        items.forEach(item => {
            const name = item.dataset.displayName.toLowerCase();
            item.style.display = name.includes(lowerQuery) ? '' : 'none';
        });
    }
    
    openNewChatModal() {
        // Close group composer if it's open
        if (this.groupComposer) {
            this.closeGroupComposer();
        }
        
        // Check if we need to minimize an old chat (treat modal as taking up a slot)
        const maxChats = this.getMaxChats();
        const isMobile = window.innerWidth < 640;
        
        if (this.openChats.length >= maxChats) {
            if (isMobile) {
                while (this.openChats.length >= maxChats) {
                    const oldestId = this.openChats.shift();
                    this.minimizeChat(oldestId, false);
                }
            } else {
                const oldestId = this.openChats.pop();
                this.minimizeChat(oldestId);
            }
        }
        
        this.newChatModal.classList.remove('hidden');
        // Position it after other open chats
        const pos = this.getChatPosition(this.openChats.length);
        this.newChatModal.style.right = `${pos.right}px`;
        this.newChatModal.style.bottom = `${pos.bottom}px`;
        const input = document.getElementById('new-chat-search-users');
        input.value = '';
        input.focus();
        // Load suggested users when modal opens
        if (this.newChatTypeahead) {
            this.newChatTypeahead.loadSuggestions();
        }
    }
    
    openCreateGroupModal(initialUserId = null) {
        // Close other windows
        this.closeMainWindow();
        this.closeNewChatModal();
        
        // Check if group composer already exists
        if (this.groupComposer) {
            // Bring to front
            this.container.appendChild(this.groupComposer.element);
            if (initialUserId) {
                this.groupComposer.addUserById(initialUserId);
            }
            return;
        }
        
        // Minimize existing open chats (like opening a new chatbox)
        const maxChats = this.getMaxChats();
        const isMobile = window.innerWidth < 640;
        
        // On mobile: minimize ALL open chats to make room for group composer
        // On desktop: only minimize if at max capacity
        if (isMobile) {
            while (this.openChats.length > 0) {
                const oldestId = this.openChats.shift();
                this.minimizeChat(oldestId, false);
            }
        } else if (this.openChats.length >= maxChats) {
            const oldestId = this.openChats.pop();
            this.minimizeChat(oldestId);
        }
        
        // Create new group composer window
        this.groupComposer = new GroupChatComposer({
            manager: this,
            config: this.config,
            initialUserId: initialUserId
        });
        
        this.container.appendChild(this.groupComposer.element);
        
        // Position to the right of existing chats
        const pos = this.getChatPosition(this.openChats.length);
        this.groupComposer.element.style.right = `${pos.right}px`;
        this.groupComposer.element.style.bottom = `${pos.bottom - 50}px`;
        this.groupComposer.element.style.opacity = '0';
        this.groupComposer.element.style.transform = 'scale(0.8)';
        
        requestAnimationFrame(() => {
            this.groupComposer.element.style.transition = 'all 0.3s ease-out';
            this.groupComposer.element.style.bottom = `${pos.bottom}px`;
            this.groupComposer.element.style.opacity = '1';
            this.groupComposer.element.style.transform = 'scale(1)';
            
            // Reposition other chats if any
            if (this.openChats.length > 0) {
                this.openChats.forEach((cid, index) => {
                    const chat = this.chatWindows.get(cid);
                    if (chat && chat.element) {
                        const chatPos = this.getChatPosition(index);
                        chat.element.style.transition = 'right 0.3s ease-out, bottom 0.3s ease-out';
                        chat.element.style.right = `${chatPos.right}px`;
                        chat.element.style.bottom = `${chatPos.bottom}px`;
                    }
                });
            }
        });
    }
    
    closeGroupComposer() {
        if (this.groupComposer) {
            // Animate out
            this.groupComposer.element.style.transition = 'all 0.2s ease-in';
            this.groupComposer.element.style.opacity = '0';
            this.groupComposer.element.style.transform = 'scale(0.8)';
            
            setTimeout(() => {
                this.groupComposer.element.remove();
                this.groupComposer = null;
                this.persistState();
            }, 200);
        }
    }
    
    closeNewChatModal() {
        this.newChatModal.classList.add('hidden');
        document.getElementById('new-chat-search-users').value = '';
        document.getElementById('new-chat-user-results').innerHTML = '';
    }
    
    // WebSocket connection for real-time messaging
    connectWebSocket() {
        // Connect through Caddy proxy (same as terminal)
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.host;
        const wsUrl = `${protocol}//${host}/messenger-ws/`;
        
        console.log('Connecting to messenger WebSocket:', wsUrl);
        
        try {
            this.ws = new WebSocket(wsUrl);
            
            this.ws.onopen = () => {
                console.log('Messenger WebSocket connected');
                this.wsConnected = true;
                this.wsReconnectAttempts = 0;
                
                // Authenticate with server
                const userId = this.config.userId || window.GINTO_AUTH?.userId;
                if (userId) {
                    this.ws.send(JSON.stringify({
                        type: 'auth',
                        userId: userId,
                        token: this.getCsrfToken()
                    }));
                    // Also subscribe to currently open conversation (if any) so server tracks us
                    try {
                        const convId = this.currentConversationId || (this.openChats && this.openChats[0]) || null;
                        if (convId) {
                            this.ws.send(JSON.stringify({ type: 'subscribe', conversation_id: convId }));
                            console.log('Sent subscribe for conversation', convId);
                        }
                    } catch (e) {
                        console.warn('Failed to send subscribe after WS auth', e);
                    }
                }
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                } catch (e) {
                    console.error('Failed to parse WebSocket message:', e);
                }
            };
            
            this.ws.onclose = () => {
                console.log('Messenger WebSocket closed');
                this.wsConnected = false;
                this.attemptReconnect();
            };
            
            this.ws.onerror = (error) => {
                console.error('Messenger WebSocket error:', error);
                this.wsConnected = false;
            };
        } catch (error) {
            console.error('Failed to connect WebSocket:', error);
            this.wsConnected = false;
        }
    }
    
    attemptReconnect() {
        if (this.wsReconnectAttempts < this.wsMaxReconnectAttempts) {
            this.wsReconnectAttempts++;
            console.log(`WebSocket reconnecting... attempt ${this.wsReconnectAttempts}`);
            setTimeout(() => this.connectWebSocket(), this.wsReconnectDelay);
        } else {
            console.log('WebSocket max reconnect attempts reached');
        }
    }
    
    handleWebSocketMessage(data) {
        // Log all call-related messages
        if (data.type && data.type.startsWith('call_')) {
            console.log('📞 WebSocket call message:', data.type, data);
        }
        
        switch (data.type) {
            case 'auth_success':
                console.log('Messenger WebSocket authenticated');
                // WebSocket handles all updates - no AJAX needed
                break;
                
            case 'auth_error':
                console.error('Messenger WebSocket auth failed:', data.error);
                break;
                
            case 'message':
                // New message received
                this.handleIncomingMessage(data);
                break;
                
            case 'typing':
                // Typing indicator
                this.handleTypingIndicator(data);
                break;
                
            case 'read':
                // Message read receipt
                this.handleReadReceipt(data);
                break;
                
            case 'online':
                // User online status changed
                this.handleOnlineStatus(data);
                break;
                
            // WebRTC Call Signaling
            case 'call_offer':
                this.handleIncomingCall(data);
                break;
                
            case 'call_answer':
                this.handleCallAnswer(data);
                break;
                
            case 'call_ice':
                this.handleCallIce(data);
                break;
                
            case 'call_end':
                this.handleCallEnded(data);
                break;
                
            case 'call_unavailable':
                this.handleCallUnavailable(data);
                break;
                
            case 'call_join':
                this.handleCallJoin && this.handleCallJoin(data);
                break;
        }
    }
    
    handleIncomingMessage(data) {
        const { conversation_id, message } = data;
        
        // Play notification sound
        this.playSound('ding');
        
        // If chat window is open, add the message and mark as read
        const chatWindow = this.chatWindows.get(conversation_id);
        if (chatWindow) {
            chatWindow.addMessage(message);
            // Mark as read since user has it open
            this.sendReadReceipt(conversation_id);
        } else {
            // Increment unread count locally (no AJAX)
            this.unreadCount++;
            this.updateUnreadBadges();
        }
        
        // Refresh conversation list if main window is open
        if (this.mainWindowOpen) {
            this.loadConversations(true);
        }
    }
    
    // Update badges locally without AJAX
    updateUnreadBadges() {
        const badges = [
            document.getElementById('messenger-unread-badge'),
            document.getElementById('header-messenger-badge'),
            document.getElementById('mobile-messenger-badge')
        ];
        
        badges.forEach(badge => {
            if (!badge) return;
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            } else {
                badge.classList.add('hidden');
                badge.classList.remove('flex');
            }
        });
    }
    
    handleTypingIndicator(data) {
        const { conversation_id, user_id, is_typing } = data;
        const chatWindow = this.chatWindows.get(conversation_id);
        if (chatWindow) {
            chatWindow.showTypingIndicator(is_typing);
        }
    }
    
    handleReadReceipt(data) {
        const { conversation_id, user_id } = data;
        const chatWindow = this.chatWindows.get(conversation_id);
        if (chatWindow) {
            chatWindow.markMessagesAsRead();
        }
    }
    
    handleOnlineStatus(data) {
        const { user_id, is_online } = data;
        // Update online indicator in open chat windows
        this.chatWindows.forEach((chatWindow) => {
            if (chatWindow.otherUserId === user_id) {
                chatWindow.updateOnlineStatus(is_online);
            }
        });
        // Refresh conversation list to update online indicators
        if (this.mainWindowOpen) {
            this.loadConversations(true);
        }
    }
    
    // Send message via WebSocket
    sendWebSocketMessage(type, data) {
        if (this.ws && this.wsConnected) {
            this.ws.send(JSON.stringify({ type, ...data }));
            return true;
        }
        return false;
    }
    
    // Alias for WebRTC signaling
    wsSend(data) {
        console.log('wsSend called:', data.type, 'wsConnected:', this.wsConnected, 'ws:', !!this.ws);
        if (this.ws && this.wsConnected) {
            this.ws.send(JSON.stringify(data));
            console.log('wsSend SUCCESS');
            return true;
        }
        console.log('wsSend FAILED - WebSocket not connected');
        return false;
    }
    
    // Send read receipt via WebSocket
    sendReadReceipt(conversationId) {
        this.sendWebSocketMessage('read', { conversation_id: conversationId });
    }
    
    // Send typing indicator via WebSocket
    sendTypingIndicator(conversationId, isTyping) {
        this.sendWebSocketMessage('typing', { 
            conversation_id: conversationId, 
            is_typing: isTyping 
        });
    }
    
    // Broadcast new message via WebSocket (called after HTTP send succeeds)
    broadcastMessage(conversationId, message) {
        this.sendWebSocketMessage('message', {
            conversation_id: conversationId,
            message: message
        });
    }
    
    // Play notification sound
    playSound(soundName) {
        const sound = this.sounds[soundName];
        if (sound) {
            sound.currentTime = 0;
            sound.play().catch(() => {}); // Ignore autoplay errors
        }
    }
    
    // Keep pollUnreadCount for initial load only (called once on auth)
    // No interval - WebSocket handles updates
    pollUnreadCount() {
        // Only called once after WebSocket auth - no interval
    }
    
    // Legacy polling methods - kept for fallback but not used with WebSocket
    startMessagePolling() {
        // Disabled - WebSocket handles real-time updates
        console.log('Message polling disabled - using WebSocket');
    }
    
    // Refresh messages in all open chat windows (used for manual refresh)
    refreshOpenChats() {
        this.chatWindows.forEach((chatWindow, conversationId) => {
            chatWindow.refreshMessages();
        });
    }
    
    async refreshUnreadCount() {
        try {
            const response = await fetch('/messenger/unread-count');
            const data = await response.json();
            
            if (data.success) {
                this.unreadCount = data.unread_count;
                
                const badges = [
                    document.getElementById('messenger-unread-badge'),
                    document.getElementById('header-messenger-badge'),
                    document.getElementById('mobile-messenger-badge')
                ];
                
                badges.forEach(badge => {
                    if (!badge) return;
                    if (this.unreadCount > 0) {
                        badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                        badge.classList.remove('hidden');
                        badge.classList.add('flex');
                    } else {
                        badge.classList.add('hidden');
                        badge.classList.remove('flex');
                    }
                });
            }
        } catch (error) {
            // Silent fail
        }
    }
    
    // Persistence
    persistState() {
        const state = {
            mainWindowOpen: this.mainWindowOpen,
            openChats: this.openChats.map(id => {
                const cw = this.chatWindows.get(id);
                return cw ? {
                    conversationId: id,
                    displayName: cw.displayName,
                    otherUserId: cw.otherUserId,
                    conversationType: cw.conversationType || 'direct',
                    participants: cw.participants || [],
                    memberSince: cw.memberSince || null
                } : null;
            }).filter(Boolean),
            minimizedChats: this.minimizedChats
        };
        
        try {
            localStorage.setItem('ginto_messenger_state', JSON.stringify(state));
        } catch (e) {
            // localStorage might be unavailable
        }
    }
    
    restorePersistedChats() {
        try {
            const stateStr = localStorage.getItem('ginto_messenger_state');
            if (!stateStr) return;
            
            const state = JSON.parse(stateStr);
            
            // Restore main window state
            if (state.mainWindowOpen) {
                this.openMainWindow();
            }
            
            // Restore open chats (in saved order)
            if (state.openChats && Array.isArray(state.openChats)) {
                const maxChats = this.getMaxChats();
                const toRestore = state.openChats.slice(0, maxChats);
                
                toRestore.forEach(chat => {
                    if (chat && chat.conversationId) {
                        this.createChatWindow(chat.conversationId, chat.displayName, chat.otherUserId, { 
                            restoring: true,
                            conversationType: chat.conversationType || 'direct',
                            participants: chat.participants || [],
                            memberSince: chat.memberSince || null
                        });
                    }
                });
                
                // Mark as loaded if we restored any open chats
                if (toRestore.length > 0 && !this.conversationsLoaded) {
                    this.conversationsLoaded = true;
                    // No polling needed - WebSocket handles real-time updates
                }
            }
            
            // Restore minimized chats (those completely minimized to tray)
            if (state.minimizedChats && Array.isArray(state.minimizedChats)) {
                this.minimizedChats = state.minimizedChats;
                // Create minimized indicators for restored chats
                this.restoreMinimizedIndicators();
            }
        } catch (e) {
            console.error('Error restoring messenger state:', e);
            // Invalid state, ignore
        }
    }
    
    // Utility
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // YouTube URL detection - supports all YouTube URL formats
    extractYouTubeId(text) {
        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtube\.com\/watch\?.*&v=)([a-zA-Z0-9_-]{11})/,
            /(?:youtu\.be\/)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/
        ];
        
        for (const pattern of patterns) {
            const match = text.match(pattern);
            if (match) return match[1];
        }
        return null;
    }
    
    hasYouTubeLink(text) {
        return this.extractYouTubeId(text) !== null;
    }
    
    renderYouTubePreview(videoId) {
        const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
        return `
            <div class="youtube-preview mt-2 rounded-lg overflow-hidden bg-black/30 cursor-pointer" data-video-id="${videoId}">
                <div class="youtube-thumbnail relative">
                    <img src="${thumbnailUrl}" alt="Video" class="w-full aspect-video object-cover">
                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/20 transition-colors youtube-play-overlay">
                        <div class="w-12 h-12 rounded-full bg-red-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-play text-white text-lg ml-0.5"></i>
                        </div>
                    </div>
                </div>
                <div class="youtube-embed hidden"></div>
                <div class="px-2 py-1 flex items-center gap-1">
                    <i class="fab fa-youtube text-red-500 text-sm"></i>
                    <span class="text-[10px] text-gray-400">YouTube</span>
                </div>
            </div>
        `;
    }
    
    playYouTubeVideo(previewElement) {
        const videoId = previewElement.dataset.videoId;
        if (!videoId) return;
        
        const thumbnail = previewElement.querySelector('.youtube-thumbnail');
        const embedContainer = previewElement.querySelector('.youtube-embed');
        
        if (thumbnail && embedContainer) {
            thumbnail.classList.add('hidden');
            embedContainer.classList.remove('hidden');
            embedContainer.innerHTML = `
                <div class="aspect-video">
                    <iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" 
                        frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen class="w-full h-full"></iframe>
                </div>
            `;
        }
    }
    
    processMessageContent(content) {
        const escaped = this.escapeHtml(content);
        const videoId = this.extractYouTubeId(content);
        
        if (videoId) {
            const urlPattern = /(https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\/[^\s]+)/gi;
            const textWithLink = escaped.replace(urlPattern, '<a href="$1" target="_blank" class="text-blue-400 hover:underline text-[13px]">$1</a>');
            return textWithLink + this.renderYouTubePreview(videoId);
        }
        
        const linkPattern = /(https?:\/\/[^\s]+)/gi;
        return escaped.replace(linkPattern, '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>');
    }
    
    bindYouTubeEvents(container) {
        const previews = container.querySelectorAll('.youtube-preview');
        previews.forEach(preview => {
            preview.addEventListener('click', (e) => {
                if (e.target.closest('iframe')) return;
                this.playYouTubeVideo(preview);
            });
        });
    }
    
    formatTime(dateStr) {
        if (!dateStr) return '';
        
        // Parse date - handle both ISO strings and MySQL datetime format
        let date;
        if (dateStr.includes('T')) {
            date = new Date(dateStr);
        } else {
            // MySQL datetime format: "2024-01-21 12:30:00"
            // Append 'Z' to treat as UTC if no timezone specified
            date = new Date(dateStr.replace(' ', 'T') + 'Z');
        }
        
        if (isNaN(date.getTime())) return '';
        
        const now = new Date();
        const diff = now - date;
        
        // Negative diff means future time (likely timezone issue) - show "now"
        if (diff < 0) return 'now';
        
        if (diff < 60000) return 'now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
        if (diff < 604800000) return Math.floor(diff / 86400000) + 'd';
        
        return date.toLocaleDateString();
    }
    
    // Play ding sound for received messages
    playNotificationSound() {
        try {
            this.sounds.ding.currentTime = 0;
            this.sounds.ding.play().catch(() => {});
        } catch (e) {
            // Audio might be blocked
        }
    }
    
    // Play pop sound for sent messages
    playSentSound() {
        try {
            this.sounds.pop.currentTime = 0;
            this.sounds.pop.play().catch(() => {});
        } catch (e) {
            // Audio might be blocked
        }
    }
}

/**
 * Individual Chat Window
 */
class ChatWindow {
    constructor(options) {
        this.conversationId = options.conversationId;
        this.displayName = options.displayName;
        this.otherUserId = options.otherUserId;
        this.conversationType = options.conversationType || 'direct';
        this.isGroupChat = this.conversationType === 'group';
        this.participants = options.participants || [];
        this.memberSince = options.memberSince || null;
        this.manager = options.manager;
        this.config = options.config;
        this.messages = [];
        this.isInputExpanded = false;
        
        this.createElement();
        this.bindEvents();
    }
    
    getCsrfToken() {
        return this.manager.getCsrfToken();
    }
    
    createElement() {
        const el = document.createElement('div');
        el.className = 'fixed w-[328px] h-[455px] messenger-bg rounded-lg shadow-2xl flex flex-col overflow-hidden z-[100]';
        el.style.fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';
        el.dataset.conversationId = this.conversationId;
        
        // Generate avatar - different for group vs direct
        let headerAvatarHtml;
        if (this.isGroupChat && this.participants.length > 0) {
            headerAvatarHtml = generateGroupAvatar(this.participants, 8, 2);
        } else {
            const initial = (this.displayName || '?')[0].toUpperCase();
            const color = this.otherUserId ? getAvatarColor(this.otherUserId) : 'from-blue-500 to-purple-600';
            headerAvatarHtml = `<div class="w-8 h-8 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white text-sm font-semibold">${initial}</div>`;
        }
        
        el.innerHTML = `
            <!-- Header - Sticky at top -->
            <div class="chat-header flex-shrink-0 flex items-center justify-between px-2 py-1.5 messenger-bg border-b messenger-border shadow-sm z-10 relative">
                <div class="chat-header-profile flex items-center gap-2 min-w-0 flex-1 cursor-pointer hover:opacity-80" title="Options">
                    <div class="relative flex-shrink-0 chat-header-avatar">
                        ${headerAvatarHtml}
                        ${!this.isGroupChat ? '<div class="chat-online-indicator hidden absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 rounded-full border-2 messenger-online-border"></div>' : ''}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1">
                            <span class="font-semibold messenger-text text-[13px] truncate">${this.escapeHtml(this.displayName)}</span>
                            <svg class="w-3 h-3 messenger-text-secondary chat-dropdown-arrow transition-transform" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="chat-status-text text-[11px] messenger-text-secondary">${this.isGroupChat ? ((this.participants.length + 1) === 1 ? '1 member' : (this.participants.length + 1) + ' members') : 'Tap for info'}</span>
                    </div>
                </div>
                <!-- Dropdown Menu -->
                <div class="chat-dropdown-menu hidden absolute left-0 top-full w-56 messenger-bg rounded-lg shadow-xl border messenger-border py-1 z-50">
                    <a href="/user/${this.otherUserId}" class="chat-menu-item ${this.isGroupChat ? 'hidden' : ''} flex items-center gap-3 px-3 py-2 messenger-bg-hover messenger-text text-[13px] transition-colors">
                        <svg class="w-5 h-5 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        View profile
                    </a>
                    <button class="chat-menu-create-group flex items-center gap-3 px-3 py-2 messenger-bg-hover messenger-text text-[13px] transition-colors w-full text-left">
                        <svg class="w-5 h-5 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Create group
                    </button>
                    <button class="chat-menu-view-members ${this.isGroupChat ? '' : 'hidden'} flex items-center gap-3 px-3 py-2 messenger-bg-hover messenger-text text-[13px] transition-colors w-full text-left">
                        <svg class="w-5 h-5 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                        View group members
                    </button>
                    <div class="border-t messenger-border my-1"></div>
                    <button class="chat-menu-mute flex items-center gap-3 px-3 py-2 messenger-bg-hover messenger-text text-[13px] transition-colors w-full text-left">
                        <svg class="w-5 h-5 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                        </svg>
                        Mute notifications
                    </button>
                    <button class="chat-menu-block flex items-center gap-3 px-3 py-2 messenger-bg-hover messenger-text text-[13px] transition-colors w-full text-left">
                        <svg class="w-5 h-5 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Block user
                    </button>
                    <div class="border-t messenger-border my-1"></div>
                    <button class="chat-menu-archive flex items-center gap-3 px-3 py-2 messenger-bg-hover messenger-text text-[13px] transition-colors w-full text-left">
                        <svg class="w-5 h-5 messenger-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                        Archive chat
                    </button>
                    <button class="chat-menu-delete flex items-center gap-3 px-3 py-2 messenger-bg-hover text-red-400 text-[13px] transition-colors w-full text-left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete chat
                    </button>
                </div>
                <div class="flex items-center">
                    <button class="chat-call-btn p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Start a voice call">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                    </button>
                    <button class="chat-video-btn p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Start a video call">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                        </svg>
                    </button>
                    <button class="chat-new-btn p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="New message">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button class="chat-minimize-btn p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Minimize">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 13H5v-2h14v2z"/>
                        </svg>
                    </button>
                    <button class="chat-close-btn p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors" title="Close">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Messages - Scrollable middle section -->
            <div class="chat-messages-wrapper relative flex-1 min-h-0">
                <!-- Floating expand button - opens in mobile fullscreen mode -->
                <button class="chat-fullscreen-btn absolute top-2 left-2 z-10 p-1.5 text-[#0084ff] hover:text-[#0073e6] transition-colors" title="Open in full view" data-conversation="${this.conversationId}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                </button>
                <div class="chat-messages h-full overflow-y-auto p-2 space-y-1 messenger-scroll">
                <div class="flex items-center justify-center py-8 messenger-text-secondary">
                    <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading...
                </div>
            </div>
            </div>
            
            <!-- Input Area - Sticky at bottom -->
            <div class="chat-footer flex-shrink-0 px-2 py-1.5 messenger-bg border-t messenger-border">
                <div class="flex items-end gap-1">
                    <!-- Expand button -->
                    <button class="chat-expand-btn p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-all duration-200 ease-out overflow-hidden" style="width: 0; opacity: 0;" title="Open more actions">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </button>
                    
                    <!-- Left icons -->
                    <div class="chat-left-icons flex items-center overflow-hidden transition-all duration-200 ease-out" style="max-width: 160px; opacity: 1;">
                        <button class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Voice clip">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                            </svg>
                        </button>
                        <button class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Attach photo">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                        </button>
                        <button class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Emoji">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                            </svg>
                        </button>
                        <button class="p-2 messenger-bg-hover rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="GIF">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.5 9H13v6h-1.5zM9 9H6c-.6 0-1 .5-1 1v4c0 .5.4 1 1 1h3c.6 0 1-.5 1-1v-2H8.5v1.5h-2v-3H10V10c0-.5-.4-1-1-1zm10 1.5V9h-4.5v6H16v-2h2v-1.5h-2v-1z"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Input -->
                    <div class="flex-1 relative">
                        <textarea class="chat-input w-full messenger-bg-input border-none rounded-full py-2 px-3 pr-10 text-base messenger-text placeholder-gray-400 dark:placeholder-[#b0b3b8] focus:outline-none resize-none max-h-[80px] transition-all duration-200"
                            placeholder="Aa" rows="1"></textarea>
                        <button class="chat-emoji-inline absolute right-2 top-1/2 -translate-y-1/2 p-1 text-[#0084ff] hover:text-[#0073e6] transition-opacity duration-200" style="opacity: 0; pointer-events: none;" title="Emoji">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Send -->
                    <button class="chat-send-btn p-2 text-[#0084ff] messenger-bg-hover rounded-full transition-colors" title="Send a like">
                        <svg class="chat-like-icon w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
                        </svg>
                        <svg class="chat-send-icon w-5 h-5 hidden" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        this.element = el;
    }
    
    bindEvents() {
        const el = this.element;
        
        // Close button
        el.querySelector('.chat-close-btn').addEventListener('click', () => {
            this.manager.closeChat(this.conversationId);
        });
        
        // Minimize button
        el.querySelector('.chat-minimize-btn').addEventListener('click', () => {
            this.manager.minimizeChat(this.conversationId);
        });
        
        // New chat button
        el.querySelector('.chat-new-btn').addEventListener('click', () => {
            this.manager.openNewChatModal();
        });
        
        // Voice call button
        el.querySelector('.chat-call-btn').addEventListener('click', () => {
            this.manager.startCall(this.conversationId, this.otherUserId, this.displayName, 'audio');
        });
        
        // Video call button
        el.querySelector('.chat-video-btn').addEventListener('click', () => {
            this.manager.startCall(this.conversationId, this.otherUserId, this.displayName, 'video');
        });
        
        // Fullscreen button - opens messenger with mobile fullscreen mode
        el.querySelector('.chat-fullscreen-btn')?.addEventListener('click', () => {
            const conversationId = this.conversationId;
            const targetUrl = `/messenger?conversation=${conversationId}`;
            
            // On mobile, try to request fullscreen before navigating
            // This helps hide the address bar on Android
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            
            if (isMobile && document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().then(() => {
                    window.location.href = targetUrl;
                }).catch(() => {
                    // Fullscreen failed, just navigate
                    window.location.href = targetUrl;
                });
            } else if (isMobile && document.documentElement.webkitRequestFullscreen) {
                try {
                    document.documentElement.webkitRequestFullscreen();
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 100);
                } catch (e) {
                    window.location.href = targetUrl;
                }
            } else {
                window.location.href = targetUrl;
            }
        });
        
        // Header profile click - toggle dropdown menu
        const headerProfile = el.querySelector('.chat-header-profile');
        const dropdownMenu = el.querySelector('.chat-dropdown-menu');
        const dropdownArrow = el.querySelector('.chat-dropdown-arrow');
        
        headerProfile.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !dropdownMenu.classList.contains('hidden');
            dropdownMenu.classList.toggle('hidden');
            dropdownArrow.style.transform = isOpen ? '' : 'rotate(180deg)';
        });
        
        // Close dropdown when clicking outside the dropdown or header profile
        document.addEventListener('click', (e) => {
            if (!dropdownMenu.classList.contains('hidden') && !headerProfile.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('hidden');
                dropdownArrow.style.transform = '';
            }
        });
        
        // Dropdown menu actions
        el.querySelector('.chat-menu-create-group')?.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = '';
            this.manager.openCreateGroupModal(this.otherUserId);
        });
        
        el.querySelector('.chat-menu-view-members')?.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = '';
            this.showGroupMembers();
        });
        
        el.querySelector('.chat-menu-mute')?.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = '';
            // TODO: Implement mute
            alert('Mute notifications - Coming soon');
        });
        
        el.querySelector('.chat-menu-block')?.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = '';
            if (confirm(`Block ${this.displayName}? They won't be able to message you.`)) {
                // TODO: Implement block
                alert('Block user - Coming soon');
            }
        });
        
        el.querySelector('.chat-menu-archive')?.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = '';
            this.manager.archiveChat(this.conversationId);
        });
        
        el.querySelector('.chat-menu-delete')?.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = '';
            if (confirm('Delete this conversation? This cannot be undone.')) {
                this.manager.deleteChat(this.conversationId);
            }
        });
        
        // Input handling
        const input = el.querySelector('.chat-input');
        const sendBtn = el.querySelector('.chat-send-btn');
        const likeIcon = el.querySelector('.chat-like-icon');
        const sendIcon = el.querySelector('.chat-send-icon');
        const leftIcons = el.querySelector('.chat-left-icons');
        const expandBtn = el.querySelector('.chat-expand-btn');
        const emojiInline = el.querySelector('.chat-emoji-inline');
        
        const setExpanded = (expanded) => {
            this.isInputExpanded = expanded;
            if (expanded) {
                leftIcons.style.maxWidth = '0px';
                leftIcons.style.opacity = '0';
                expandBtn.style.width = '36px';
                expandBtn.style.opacity = '1';
                emojiInline.style.opacity = '1';
                emojiInline.style.pointerEvents = 'auto';
            } else {
                leftIcons.style.maxWidth = '160px';
                leftIcons.style.opacity = '1';
                expandBtn.style.width = '0px';
                expandBtn.style.opacity = '0';
                emojiInline.style.opacity = '0';
                emojiInline.style.pointerEvents = 'none';
            }
        };
        
        expandBtn.addEventListener('click', () => {
            setExpanded(false);
            input.focus();
        });
        
        input.addEventListener('focus', () => setExpanded(true));
        input.addEventListener('blur', () => {
            if (!input.value.trim()) setExpanded(false);
        });
        
        input.addEventListener('input', () => {
            const hasText = input.value.trim().length > 0;
            likeIcon.classList.toggle('hidden', hasText);
            sendIcon.classList.toggle('hidden', !hasText);
            sendBtn.title = hasText ? 'Send' : 'Send a like';
            if (hasText) setExpanded(true);
            
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 80) + 'px';
        });
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        sendBtn.addEventListener('click', () => {
            if (input.value.trim()) {
                this.sendMessage();
            } else {
                this.sendLike();
            }
        });
    }
    
    async loadMessages() {
        try {
            const response = await fetch(`/messenger/messages?conversation_id=${this.conversationId}&limit=50`);
            const data = await response.json();
            
            if (data.success) {
                this.messages = data.messages;
                this.renderMessages();
                
                // Mark as read and update badge immediately
                // Mark as read via WebSocket (no HTTP call needed)
                this.manager.sendReadReceipt(this.conversationId);
            }
        } catch (error) {
            const container = this.element.querySelector('.chat-messages');
            container.innerHTML = '<p class="text-center text-red-500 text-[13px] py-4">Failed to load messages</p>';
        }
    }
    
    // Refresh messages silently (for polling)
    async refreshMessages() {
        try {
            const response = await fetch(`/messenger/messages?conversation_id=${this.conversationId}&limit=50`);
            const data = await response.json();
            
            if (data.success) {
                const oldCount = this.messages.length;
                const newCount = data.messages.length;
                const oldLastId = this.messages[this.messages.length - 1]?.id;
                const newLastId = data.messages[data.messages.length - 1]?.id;
                
                // Only re-render if there are new messages
                if (newCount !== oldCount || oldLastId !== newLastId) {
                    const container = this.element.querySelector('.chat-messages');
                    const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                    
                    // Check if new message is from someone else (for sound)
                    const lastMsg = data.messages[data.messages.length - 1];
                    const hasNewIncoming = newCount > oldCount && lastMsg && lastMsg.sender_id !== this.config.userId;
                    
                    this.messages = data.messages;
                    this.renderMessages();
                    
                    // Play ding sound for new incoming messages
                    if (hasNewIncoming) {
                        this.manager.playNotificationSound();
                    }
                    
                    // Auto-scroll to bottom if was already at bottom
                    if (wasAtBottom) {
                        container.scrollTop = container.scrollHeight;
                    }
                    
                    // Mark as read via WebSocket
                    this.manager.sendReadReceipt(this.conversationId);
                }
            }
        } catch (error) {
            // Silent fail for polling
        }
    }
    
    /**
     * Generate intro header for 1-on-1 private chats
     */
    generatePrivateChatIntroHeader() {
        const initial = (this.displayName || '?')[0].toUpperCase();
        const color = this.otherUserId ? getAvatarColor(this.otherUserId) : 'from-blue-500 to-purple-600';
        
        // Format member since date
        let memberSinceHtml = '';
        if (this.memberSince) {
            const date = new Date(this.memberSince);
            const options = { year: 'numeric', month: 'long' };
            const formattedDate = date.toLocaleDateString('en-US', options);
            memberSinceHtml = `<p class="messenger-text-muted text-[11px] mt-1">Member since ${formattedDate}</p>`;
        }
        
        return `
            <div class="flex flex-col items-center justify-center py-6 text-center mb-2">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white text-2xl font-semibold">
                    ${initial}
                </div>
                <span class="font-semibold messenger-text text-[15px] mt-3 px-4">${this.escapeHtml(this.displayName)}</span>
                ${memberSinceHtml}
                <p class="messenger-text-muted text-[11px] mt-2 px-4">You can message and call each other and see info like active status.</p>
            </div>
        `;
    }
    
    /**
     * Generate the group intro header shown at the top of group chats
     */
    generateGroupIntroHeader() {
        if (!this.isGroupChat) return '';
        
        // If only 1 participant (plus current user = 2 people), it's actually a private chat
        if (this.participants.length <= 1) {
            return this.generatePrivateChatIntroHeader();
        }
        
        // Include current user in participant display
        const currentUserId = this.config.userId;
        const currentUserInitial = (this.config.userName || 'You')[0].toUpperCase();
        
        // Build list of all members including current user
        const allMembers = [
            { id: currentUserId, initial: currentUserInitial, isCurrentUser: true },
            ...this.participants.map(p => ({
                id: p.id,
                initial: (p.fullname || p.display_name || p.username || '?').charAt(0).toUpperCase(),
                isCurrentUser: false
            }))
        ];
        
        const participantCount = allMembers.length;
        const avatarsToShow = allMembers.slice(0, 4); // Show up to 4 members
        
        // Calculate width based on number of avatars (overlapping style)
        const avatarSize = 40; // w-10 = 40px
        const overlap = 12; // How much each avatar overlaps
        const totalWidth = avatarSize + (avatarsToShow.length - 1) * (avatarSize - overlap);
        
        let avatarsHtml;
        if (avatarsToShow.length === 0) {
            avatarsHtml = `<div class="w-16 h-16 rounded-full bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center text-white text-2xl font-semibold">G</div>`;
        } else {
            // Overlapping horizontal avatars
            avatarsHtml = `
                <div class="relative h-10" style="width: ${totalWidth}px;">
                    ${avatarsToShow.map((m, i) => {
                        const color = getAvatarColor(m.id);
                        const leftOffset = i * (avatarSize - overlap);
                        const zIndex = avatarsToShow.length - i; // First avatar on top
                        return `<div class="w-10 h-10 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white text-sm font-semibold absolute top-0 border-2 messenger-online-border" style="left: ${leftOffset}px; z-index: ${zIndex};">${m.initial}</div>`;
                    }).join('')}
                </div>
            `;
        }
        
        const memberText = participantCount === 1 ? '1 member' : `${participantCount} members`;
        
        return `
            <div class="flex flex-col items-center justify-center py-6 text-center mb-2">
                ${avatarsHtml}
                <span class="font-semibold messenger-text text-[15px] mt-3 px-4">${this.escapeHtml(this.displayName)}</span>
                <p class="messenger-text-secondary text-[13px] mt-1">${memberText}</p>
                <p class="messenger-text-muted text-[11px] mt-2 px-4">You can message and call each other and see info like active status.</p>
            </div>
        `;
    }
    
    renderMessages() {
        const container = this.element.querySelector('.chat-messages');
        const userId = this.config.userId;
        
        if (this.messages.length === 0) {
            // Show group intro for group chats, or single user intro for direct
            if (this.isGroupChat && this.participants.length > 0) {
                container.innerHTML = this.generateGroupIntroHeader() + `
                    <div class="flex flex-col items-center justify-center py-4 text-center">
                        <p class="text-[#b0b3b8] text-[13px]">Say hi to start the conversation! 👋</p>
                    </div>
                `;
            } else {
                const initial = (this.displayName || '?')[0].toUpperCase();
                const color = this.otherUserId ? getAvatarColor(this.otherUserId) : 'from-blue-500 to-purple-600';
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white text-2xl font-semibold mb-2">
                            ${initial}
                        </div>
                        <span class="font-semibold text-[#e4e6eb] text-[15px]">${this.escapeHtml(this.displayName)}</span>
                        <p class="text-[#b0b3b8] text-[13px] mt-1">Say hi to start the conversation! 👋</p>
                    </div>
                `;
            }
            return;
        }
        
        // Helper function to parse date string as UTC if no timezone specified
        const parseDate = (dateStr) => {
            // If the date string doesn't have timezone info, treat it as UTC
            if (dateStr && !dateStr.includes('Z') && !dateStr.includes('+') && !dateStr.includes('T')) {
                // MySQL format: "2026-01-21 13:50:00" - treat as UTC
                return new Date(dateStr.replace(' ', 'T') + 'Z');
            }
            return new Date(dateStr);
        };
        
        // Helper function to format timestamp like Facebook
        const formatTimestamp = (dateStr) => {
            const date = parseDate(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            
            const timeStr = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            
            if (diffDays === 0) {
                return timeStr; // Today: just time
            } else if (diffDays === 1) {
                return `Yesterday ${timeStr}`;
            } else if (diffDays < 7) {
                const dayName = date.toLocaleDateString([], { weekday: 'short' });
                return `${dayName} ${timeStr}`;
            } else {
                const dateFormatted = date.toLocaleDateString([], { month: 'short', day: 'numeric' });
                return `${dateFormatted}, ${timeStr}`;
            }
        };
        
        // Check if we should show a timestamp between messages (15+ min gap)
        const shouldShowTimestamp = (prevMsg, currMsg) => {
            if (!prevMsg) return true; // Always show for first message
            const prevDate = parseDate(prevMsg.created_at);
            const currDate = parseDate(currMsg.created_at);
            const diffMinutes = (currDate - prevDate) / (1000 * 60);
            return diffMinutes >= 15;
        };
        
        container.innerHTML = this.messages.map((msg, idx) => {
            const prevMsg = idx > 0 ? this.messages[idx - 1] : null;
            const showTimestamp = shouldShowTimestamp(prevMsg, msg);
            
            // Timestamp divider
            const timestampHtml = showTimestamp ? `
                <div class="flex justify-center my-3">
                    <span class="text-[11px] text-[#65676b] uppercase tracking-wide">${formatTimestamp(msg.created_at)}</span>
                </div>
            ` : '';
            
            // Handle call events - Facebook style
            if (msg.message_type === 'call') {
                const payload = typeof msg.payload === 'string' ? JSON.parse(msg.payload) : msg.payload;
                const callType = payload?.type || 'audio';
                const event = payload?.event || 'call_event';
                const durationSeconds = payload?.duration_seconds || null;
                const reason = payload?.reason || null;
                const isInitiator = msg.sender_id === userId;
                
                // Phone icon with color: blue for outgoing, green for incoming/answered
                const phoneIcon = callType === 'video' 
                    ? `<svg class="w-4 h-4 ${isInitiator ? 'text-blue-400' : 'text-green-400'}" fill="currentColor" viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>`
                    : `<svg class="w-4 h-4 ${isInitiator ? 'text-blue-400' : 'text-green-400'}" fill="currentColor" viewBox="0 0 24 24"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>`;
                
                // Format duration
                let durationText = '';
                if (durationSeconds !== null && durationSeconds > 0) {
                    const minutes = Math.floor(durationSeconds / 60);
                    const seconds = durationSeconds % 60;
                    durationText = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
                }
                
                // Simple Facebook-style text
                let callText = '';
                if (event === 'call_started') {
                    callText = isInitiator ? `You called ${this.displayName}` : `${this.displayName} called you`;
                } else {
                    // call_ended
                    if (durationText) {
                        callText = `${callType === 'video' ? 'Video' : 'Voice'} call • ${durationText}`;
                    } else if (reason === 'declined') {
                        callText = isInitiator ? 'Call declined' : 'You declined';
                    } else if (reason === 'busy') {
                        callText = 'No answer';
                    } else if (reason === 'no_answer' || reason === 'timeout') {
                        callText = isInitiator ? 'No answer' : 'Missed call';
                    } else {
                        callText = 'Call ended';
                    }
                }
                
                return `
                    ${timestampHtml}
                    <div class="flex justify-center my-2">
                        <div class="messenger-msg-system rounded-full px-4 py-2 text-[13px] flex items-center gap-2">
                            ${phoneIcon}
                            <span>${callText}</span>
                        </div>
                    </div>
                `;
            }
            
            const isOwn = msg.sender_id === userId;
            const showAvatar = !isOwn && (idx === 0 || this.messages[idx - 1].sender_id !== msg.sender_id || showTimestamp);
            const initial = (this.displayName || '?')[0].toUpperCase();
            const messageText = msg.content || msg.message_text || '';
            const hasYouTube = this.manager.hasYouTubeLink(messageText);
            const messageContent = this.manager.processMessageContent(messageText);
            
            // For group chats, show sender name
            let senderNameHtml = '';
            if (this.isGroupChat && !isOwn && showAvatar) {
                const senderName = msg.sender_firstname 
                    ? `${msg.sender_firstname}${msg.sender_lastname ? ' ' + msg.sender_lastname : ''}`
                    : msg.sender_username || 'Unknown';
                senderNameHtml = `<div class="text-[11px] messenger-text-muted mb-0.5 ml-1">${this.escapeHtml(senderName)}</div>`;
            }
            
            // In group chat, get initial from sender with consistent color
            const msgInitial = this.isGroupChat && !isOwn
                ? ((msg.sender_firstname || msg.sender_username || '?')[0].toUpperCase())
                : initial;
            const avatarColor = !isOwn 
                ? (msg.sender_id ? getAvatarColor(msg.sender_id) : 'from-blue-500 to-purple-600')
                : 'from-blue-500 to-purple-600';
            
            return `
                ${timestampHtml}
                <div class="flex ${isOwn ? 'justify-end' : 'justify-start'} items-end gap-1">
                    ${!isOwn ? `
                        <div class="w-7 h-7 flex-shrink-0 ${showAvatar ? '' : 'invisible'}">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white text-xs font-semibold">
                                ${msgInitial}
                            </div>
                        </div>
                    ` : ''}
                    <div class="${hasYouTube ? 'max-w-[85%]' : 'max-w-[70%]'}">
                        ${senderNameHtml}
                        <div class="${isOwn ? 'bg-[#0084ff] text-white' : 'messenger-msg-incoming'} rounded-2xl px-3 py-1.5 text-[15px] break-words">
                            ${messageContent}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Add intro header at the top
        let introHeader = '';
        if (this.isGroupChat) {
            introHeader = this.generateGroupIntroHeader();
        } else {
            introHeader = this.generatePrivateChatIntroHeader();
        }
        container.innerHTML = introHeader + container.innerHTML;
        
        // Bind YouTube events after rendering
        this.manager.bindYouTubeEvents(container);
        
        container.scrollTop = container.scrollHeight;
    }
    
    async sendMessage() {
        const input = this.element.querySelector('.chat-input');
        const content = input.value.trim();
        
        if (!content) return;
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';
        this.element.querySelector('.chat-like-icon').classList.remove('hidden');
        this.element.querySelector('.chat-send-icon').classList.add('hidden');
        
        // Play pop sound for sent message
        this.manager.playSentSound();
        
        // Reset input area
        const leftIcons = this.element.querySelector('.chat-left-icons');
        const expandBtn = this.element.querySelector('.chat-expand-btn');
        const emojiInline = this.element.querySelector('.chat-emoji-inline');
        leftIcons.style.maxWidth = '160px';
        leftIcons.style.opacity = '1';
        expandBtn.style.width = '0px';
        expandBtn.style.opacity = '0';
        emojiInline.style.opacity = '0';
        emojiInline.style.pointerEvents = 'none';
        
        // Optimistic update
        const tempMessage = {
            id: Date.now(),
            sender_id: this.config.userId,
            content: content,
            created_at: new Date().toISOString()
        };
        this.messages.push(tempMessage);
        this.renderMessages();
        
        try {
            const response = await fetch('/messenger/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    conversation_id: this.conversationId,
                    content: content,
                    csrf_token: this.getCsrfToken()
                })
            });
            
            const data = await response.json();
            if (data.success && data.message) {
                // Broadcast via WebSocket so other user gets it in real-time
                this.manager.broadcastMessage(this.conversationId, data.message);
            }
        } catch (error) {
            console.error('Send message error:', error);
        }
    }
    
    sendLike() {
        this.messages.push({
            id: Date.now(),
            sender_id: this.config.userId,
            content: '👍',
            created_at: new Date().toISOString()
        });
        this.renderMessages();
        
        fetch('/messenger/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            body: JSON.stringify({
                conversation_id: this.conversationId,
                content: '👍',
                csrf_token: this.getCsrfToken()
            })
        }).then(r => r.json()).then(data => {
            if (data.success && data.message) {
                this.manager.broadcastMessage(this.conversationId, data.message);
            }
        });
    }
    
    // WebSocket helper methods
    addMessage(message) {
        // Add message to local array and re-render
        this.messages.push(message);
        this.renderMessages();
        // Play notification sound
        this.manager.playSound('ding');
    }
    
    showTypingIndicator(isTyping) {
        const statusText = this.element.querySelector('.chat-status-text');
        if (statusText) {
            statusText.textContent = isTyping ? 'Typing...' : 'Tap for info';
            if (isTyping) {
                statusText.classList.add('text-green-400');
            } else {
                statusText.classList.remove('text-green-400');
            }
        }
    }
    
    markMessagesAsRead() {
        // Update UI to show messages were read
        // Could add read receipts visual indicator here
    }
    
    updateOnlineStatus(isOnline) {
        const indicator = this.element.querySelector('.chat-online-indicator');
        const statusText = this.element.querySelector('.chat-status-text');
        if (indicator) {
            if (isOnline) {
                indicator.classList.remove('hidden');
            } else {
                indicator.classList.add('hidden');
            }
        }
        if (statusText && statusText.textContent === 'Tap for info') {
            statusText.textContent = isOnline ? 'Active now' : 'Tap for info';
        }
    }
    
    /**
     * Show group members in a modal overlay
     */
    async showGroupMembers() {
        if (!this.isGroupChat) return;
        
        try {
            const response = await fetch(`/messenger/group-members/${this.conversationId}`);
            const data = await response.json();
            
            if (!data.success) {
                alert(data.error || 'Failed to load group members');
                return;
            }
            
            const members = data.members || [];
            
            // Create modal overlay
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/60 flex items-center justify-center z-[10000] group-members-modal';
            modal.innerHTML = `
                <div class="messenger-bg rounded-xl shadow-2xl w-80 max-h-[400px] overflow-hidden border messenger-border">
                    <div class="flex items-center justify-between px-4 py-3 border-b messenger-border">
                        <h3 class="messenger-text font-semibold text-base">Group Members (${members.length})</h3>
                        <button class="modal-close-btn p-1 messenger-bg-hover rounded-full messenger-text-secondary transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto max-h-[320px] p-2 messenger-scroll">
                        ${members.length === 0 ? `
                            <div class="text-center py-6 messenger-text-secondary text-sm">No members found</div>
                        ` : members.map(member => {
                            const avatarColor = getAvatarColor(member.id);
                            return `
                            <a href="/user/${member.id}" class="flex items-center gap-3 p-2 messenger-bg-hover rounded-lg transition-colors">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white font-semibold flex-shrink-0">
                                    ${this.escapeHtml((member.display_name || member.username || '?').charAt(0).toUpperCase())}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="messenger-text text-sm font-medium truncate">${this.escapeHtml(member.display_name || member.username)}</div>
                                    ${member.is_current_user ? '<div class="text-[#0084ff] text-xs">You</div>' : ''}
                                </div>
                            </a>
                        `;}).join('')}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Close handlers
            const closeModal = () => modal.remove();
            modal.querySelector('.modal-close-btn').addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
            
        } catch (error) {
            console.error('Failed to load group members:', error);
            alert('Failed to load group members');
        }
    }
    
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/**
 * Reusable Typeahead User Search Component
 * Shows suggested users when empty, searches as you type
 */
class TypeaheadUserSearch {
    constructor(options) {
        this.inputElement = options.inputElement;
        this.resultsElement = options.resultsElement;
        this.manager = options.manager;
        this.mode = options.mode || 'single'; // 'single' or 'multi'
        this.onSelect = options.onSelect || (() => {});
        this.onUpdate = options.onUpdate || (() => {}); // Called when selection changes (for multi mode)
        this.selectedUsers = new Map(); // For multi mode
        this.tagsContainer = options.tagsContainer || null; // Container for user tags (multi mode)
        this.searchTimeout = null;
        this.excludeUserIds = new Set();
        
        this.bindEvents();
    }
    
    bindEvents() {
        // Input typing
        this.inputElement.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const query = e.target.value.trim();
                if (query.length === 0) {
                    this.loadSuggestions();
                } else if (query.length >= 2) {
                    this.search(query);
                } else {
                    this.showMessage('Type to search for people');
                }
            }, 300);
        });
        
        // Focus shows suggestions and results container
        this.inputElement.addEventListener('focus', () => {
            this.showResults();
            if (!this.inputElement.value.trim()) {
                this.loadSuggestions();
            }
        });
        
        // Blur hides results after a short delay (to allow clicks on results)
        this.inputElement.addEventListener('blur', () => {
            setTimeout(() => {
                if (document.activeElement !== this.inputElement) {
                    this.hideResults();
                }
            }, 150);
        });
        
        // Backspace removes last tag in multi mode
        if (this.mode === 'multi') {
            this.inputElement.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !this.inputElement.value && this.selectedUsers.size > 0) {
                    const lastUserId = Array.from(this.selectedUsers.keys()).pop();
                    this.removeUser(lastUserId);
                }
            });
        }
    }
    
    showResults() {
        this.resultsElement.classList.remove('hidden');
    }
    
    hideResults() {
        // Only hide if there are selected users (in multi mode)
        if (this.mode === 'multi' && this.selectedUsers.size > 0) {
            this.resultsElement.classList.add('hidden');
        }
    }
    
    async loadSuggestions() {
        const excludeIds = Array.from(this.excludeUserIds).concat(Array.from(this.selectedUsers.keys()));
        const excludeParam = excludeIds.length > 0 ? `?exclude=${excludeIds.join(',')}` : '';
        
        try {
            const response = await fetch(`/messenger/suggested-users${excludeParam}`);
            const data = await response.json();
            
            if (data.success && data.users.length > 0) {
                this.renderUsers(data.users, 'Suggested');
            } else {
                this.showMessage('Type to search for people');
            }
        } catch (error) {
            this.showMessage('Type to search for people');
        }
    }
    
    async search(query) {
        try {
            const response = await fetch(`/messenger/search-users?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.users.length > 0) {
                // Filter out already selected users
                const availableUsers = data.users.filter(u => !this.selectedUsers.has(u.id) && !this.excludeUserIds.has(u.id));
                
                if (availableUsers.length > 0) {
                    this.renderUsers(availableUsers);
                } else {
                    this.showMessage('No more users found');
                }
            } else {
                this.showMessage('No users found');
            }
        } catch (error) {
            this.showMessage('Search failed', true);
        }
    }
    
    async fetchUserById(userId) {
        try {
            const response = await fetch(`/messenger/search-users?q=id:${userId}`);
            const data = await response.json();
            if (data.success && data.users.length > 0) {
                return data.users[0];
            }
        } catch (e) {
            console.error('Failed to fetch user by ID:', e);
        }
        return null;
    }
    
    renderUsers(users, header = null) {
        let html = '';
        
        if (header) {
            html += `<div class="px-3 py-1 text-[11px] messenger-text-muted uppercase tracking-wide">${header}</div>`;
        }
        
        html += users.map(user => {
            const initial = (user.display_name || user.username || '?')[0].toUpperCase();
            const avatarColor = getAvatarColor(user.id);
            return `
                <div class="typeahead-user-item flex items-center gap-3 px-3 py-2 messenger-bg-hover cursor-pointer transition-colors"
                     data-user-id="${user.id}"
                     data-username="${this.escapeHtml(user.username)}"
                     data-display-name="${this.escapeHtml(user.display_name || user.username)}">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white font-semibold text-sm">
                        ${initial}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="messenger-text font-medium text-[15px] block truncate">${this.escapeHtml(user.display_name || user.username)}</span>
                    </div>
                </div>
            `;
        }).join('');
        
        this.resultsElement.innerHTML = html;
        
        // Bind click events
        this.resultsElement.querySelectorAll('.typeahead-user-item').forEach(item => {
            item.addEventListener('click', () => {
                const user = {
                    id: parseInt(item.dataset.userId),
                    username: item.dataset.username,
                    displayName: item.dataset.displayName
                };
                this.selectUser(user);
            });
        });
    }
    
    selectUser(user) {
        if (this.mode === 'single') {
            this.onSelect(user);
        } else {
            // Multi mode - add to selection
            console.debug('Typeahead.selectUser adding', user);
            if (!this.selectedUsers.has(user.id)) {
                this.selectedUsers.set(user.id, user);
                this.renderTags();
                this.onUpdate(this.getSelectedUsers());
            }
            
            // Clear input and reload suggestions
            this.inputElement.value = '';
            this.loadSuggestions();
            this.inputElement.focus();
            // Sync with manager (popup) if present to ensure UI stays consistent
            if (this.manager && typeof this.manager.renderSelectedUsers === 'function') {
                try {
                    this.manager.selectedUsers = this.getSelectedUsers();
                    this.manager.renderSelectedUsers();
                } catch (e) {
                    console.warn('Typeahead: failed to sync manager selected users', e);
                }
            }
        }
    }
    
    async addUserById(userId) {
        const user = await this.fetchUserById(userId);
        if (user) {
            this.selectUser({
                id: user.id,
                username: user.username,
                displayName: user.display_name || user.username
            });
        }
    }
    
    removeUser(userId) {
        console.debug('Typeahead.removeUser', userId);
        this.selectedUsers.delete(userId);
        this.renderTags();
        this.onUpdate(this.getSelectedUsers());
        this.loadSuggestions();
        // Sync with manager (popup) if present
        if (this.manager && typeof this.manager.renderSelectedUsers === 'function') {
            try {
                this.manager.selectedUsers = this.getSelectedUsers();
                this.manager.renderSelectedUsers();
            } catch (e) {
                console.warn('Typeahead: failed to sync manager selected users on remove', e);
            }
        }
    }
    
    renderTags() {
        if (!this.tagsContainer) return;
        
        this.tagsContainer.innerHTML = Array.from(this.selectedUsers.values()).map(user => {
            const initial = (user.displayName || '?')[0].toUpperCase();
            const avatarColor = getAvatarColor(user.id);
            return `
                <span class="inline-flex items-center gap-1.5 messenger-bg-secondary messenger-text rounded-full pl-1 pr-2 py-0.5 text-[13px]" data-user-id="${user.id}">
                    <span class="w-5 h-5 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white text-[10px] font-semibold flex-shrink-0">
                        ${initial}
                    </span>
                    ${this.escapeHtml(user.displayName)}
                    <button class="typeahead-remove-tag messenger-bg-hover rounded-full p-0.5 transition-colors" data-user-id="${user.id}">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </span>
            `;
        }).join('');
        
        // Bind remove buttons
        this.tagsContainer.querySelectorAll('.typeahead-remove-tag').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeUser(parseInt(btn.dataset.userId));
            });
        });
    }
    
    getSelectedUsers() {
        return Array.from(this.selectedUsers.values());
    }
    
    clear() {
        this.selectedUsers.clear();
        this.inputElement.value = '';
        if (this.tagsContainer) {
            this.tagsContainer.innerHTML = '';
        }
        this.resultsElement.innerHTML = '';
    }
    
    showMessage(text, isError = false) {
        const colorClass = isError ? 'text-red-500' : 'messenger-text-secondary';
        this.resultsElement.innerHTML = `<p class="text-center ${colorClass} text-[13px] py-4">${text}</p>`;
    }
    
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/**
 * Group Chat Composer Window
 * Facebook-style "New message" window for creating group chats
 */
class GroupChatComposer {
    constructor(options) {
        this.manager = options.manager;
        this.config = options.config;
        this.initialUserId = options.initialUserId;
        
        this.createElement();
        this.bindEvents();
        this.initTypeahead();
        
        // Add initial user if provided
        if (this.initialUserId) {
            this.typeahead.addUserById(this.initialUserId);
        }
    }
    
    getCsrfToken() {
        return this.manager.getCsrfToken();
    }
    
    createElement() {
        const el = document.createElement('div');
        el.className = 'fixed w-[328px] h-[455px] messenger-bg rounded-lg shadow-2xl flex flex-col overflow-hidden z-[100]';
        el.style.fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';
        
        el.innerHTML = `
            <!-- Header -->
            <div class="flex-shrink-0 flex items-center justify-between px-3 py-2 messenger-bg border-b messenger-border">
                <span class="font-semibold messenger-text text-[15px]">New message</span>
                <button class="group-close-btn p-1.5 messenger-bg-hover rounded-full messenger-text-secondary transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            
            <!-- To: Field -->
            <div class="flex-shrink-0 px-3 py-2 border-b messenger-border">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="messenger-text-secondary text-[15px]">To:</span>
                    <div class="group-selected-users flex items-center gap-1 flex-wrap"></div>
                    <input type="text" class="group-search-input flex-1 min-w-[80px] bg-transparent border-none outline-none messenger-text text-[15px] placeholder-gray-400 dark:placeholder-[#b0b3b8]" placeholder="">
                </div>
            </div>
            
            <!-- Search Results / User List -->
            <div class="group-search-results flex-1 overflow-y-auto messenger-scroll">
                <!-- Typeahead results will appear here -->
            </div>
            
            <!-- Messages Area (shown after users selected) -->
            <div class="group-messages-area hidden flex-1 overflow-y-auto p-2 messenger-scroll">
                <div class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="group-avatar-container flex items-center justify-center mb-3">
                        <!-- Avatars will be dynamically inserted here -->
                    </div>
                    <p class="messenger-text text-[15px] font-semibold group-info-text">Create a group chat</p>
                    <p class="messenger-text-secondary text-[13px] mt-1">You can message and call each other and see info like active status.</p>
                </div>
            </div>
            
            <!-- Input Area -->
            <div class="flex-shrink-0 px-2 py-1.5 messenger-bg border-t messenger-border">
                <div class="flex items-end gap-1">
                    <div class="flex-1 relative">
                        <input type="text" class="group-message-input w-full messenger-bg-input border-none rounded-full py-2 px-3 pr-10 text-[13px] messenger-text placeholder-gray-400 dark:placeholder-[#b0b3b8] focus:outline-none" placeholder="Aa" disabled>
                        <button class="absolute right-2 top-1/2 -translate-y-1/2 text-[#0084ff] hover:text-[#0073e6] transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                            </svg>
                        </button>
                    </div>
                    <button class="group-send-btn p-2 text-[#0084ff] messenger-bg-hover rounded-full transition-colors opacity-50 cursor-not-allowed" disabled>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        this.element = el;
    }
    
    initTypeahead() {
        this.typeahead = new TypeaheadUserSearch({
            inputElement: this.element.querySelector('.group-search-input'),
            resultsElement: this.element.querySelector('.group-search-results'),
            tagsContainer: this.element.querySelector('.group-selected-users'),
            manager: this.manager,
            mode: 'multi',
            onUpdate: (users) => this.onUsersChanged(users)
        });
        
        // Load initial suggestions
        this.typeahead.loadSuggestions();
    }
    
    onUsersChanged(users) {
        const hasUsers = users.length > 0;
        const searchResults = this.element.querySelector('.group-search-results');
        const messagesArea = this.element.querySelector('.group-messages-area');
        const messageInput = this.element.querySelector('.group-message-input');
        const infoText = this.element.querySelector('.group-info-text');
        const avatarContainer = this.element.querySelector('.group-avatar-container');
        
        if (hasUsers) {
            // Hide search results initially (TypeaheadUserSearch will show them on focus)
            searchResults.classList.add('hidden');
            messagesArea.classList.remove('hidden');
            messageInput.disabled = false;
            messageInput.placeholder = 'Aa';
            
            // Update avatars - include current user + selected users
            const currentUserId = this.manager.config.userId;
            const currentUserInitial = (this.manager.config.userName || 'You')[0].toUpperCase();
            
            // Build all members including current user
            const allMembers = [
                { id: currentUserId, initial: currentUserInitial },
                ...users.map(u => ({
                    id: u.id,
                    initial: (u.displayName || '?').charAt(0).toUpperCase()
                }))
            ];
            
            const avatarsToShow = allMembers.slice(0, 4);
            const avatarSize = 40;
            const overlap = 12;
            const totalWidth = avatarSize + (avatarsToShow.length - 1) * (avatarSize - overlap);
            
            avatarContainer.innerHTML = `
                <div class="relative h-10" style="width: ${totalWidth}px;">
                    ${avatarsToShow.map((m, i) => {
                        const color = getAvatarColor(m.id);
                        const leftOffset = i * (avatarSize - overlap);
                        const zIndex = avatarsToShow.length - i;
                        return `<div class="w-10 h-10 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white text-sm font-semibold absolute top-0 border-2 messenger-online-border" style="left: ${leftOffset}px; z-index: ${zIndex};">${m.initial}</div>`;
                    }).join('')}
                </div>
            `;
            
            // Update info text based on number of users
            const names = users.map(u => u.displayName);
            if (names.length === 1) {
                infoText.textContent = `Chat with ${names[0]}`;
            } else {
                infoText.textContent = `Group with ${names.slice(0, 2).join(', ')}${names.length > 2 ? ` +${names.length - 2}` : ''}`;
            }
        } else {
            searchResults.classList.remove('hidden');
            messagesArea.classList.add('hidden');
            messageInput.disabled = true;
            messageInput.placeholder = 'Aa';
            
            // Reset avatar to default group icon
            avatarContainer.innerHTML = `
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            `;
            this.typeahead.loadSuggestions();
        }
        
        this.updateSendButton();
    }
    
    bindEvents() {
        // Close button
        this.element.querySelector('.group-close-btn').addEventListener('click', () => {
            this.manager.closeGroupComposer();
        });
        
        // Message input
        const messageInput = this.element.querySelector('.group-message-input');
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        messageInput.addEventListener('input', () => {
            this.updateSendButton();
        });
        
        // Send button
        this.element.querySelector('.group-send-btn').addEventListener('click', () => {
            this.sendMessage();
        });
    }
    
    addUserById(userId) {
        if (this.typeahead) {
            this.typeahead.addUserById(userId);
        }
    }
    
    updateSendButton() {
        const messageInput = this.element.querySelector('.group-message-input');
        const sendBtn = this.element.querySelector('.group-send-btn');
        const hasMessage = messageInput.value.trim().length > 0;
        const hasUsers = this.typeahead && this.typeahead.getSelectedUsers().length > 0;
        
        if (hasMessage && hasUsers) {
            sendBtn.disabled = false;
            sendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            sendBtn.disabled = true;
            sendBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    async sendMessage() {
        const messageInput = this.element.querySelector('.group-message-input');
        const message = messageInput.value.trim();
        const users = this.typeahead.getSelectedUsers();
        
        if (!message || users.length === 0) return;
        
        const userIds = users.map(u => u.id);
        
        // If only one user, start a direct conversation
        if (userIds.length === 1) {
            try {
                const response = await fetch('/messenger/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken()
                    },
                    body: JSON.stringify({
                        user_id: userIds[0],
                        message: message,
                        csrf_token: this.getCsrfToken()
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const user = users[0];
                    this.manager.closeGroupComposer();
                    this.manager.openConversation(data.conversation_id, user.displayName, userIds[0], 'direct');
                    this.manager.loadConversations();
                } else {
                    alert(data.error || 'Failed to start conversation');
                }
            } catch (error) {
                alert('Failed to start conversation');
            }
        } else {
            // Create group conversation
            try {
                const response = await fetch('/messenger/create-group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken()
                    },
                    body: JSON.stringify({
                        user_ids: userIds,
                        message: message,
                        csrf_token: this.getCsrfToken()
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.manager.closeGroupComposer();
                    this.manager.openConversation(data.conversation_id, data.group_name || 'Group Chat', null, 'group');
                    this.manager.loadConversations();
                } else {
                    alert(data.error || 'Failed to create group');
                }
            } catch (error) {
                alert('Failed to create group');
            }
        }
    }
    
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('messenger-multi-chat.js loaded, pathname:', window.location.pathname);
    
    const initMessenger = () => {
        console.log('initMessenger called, userId:', window.GINTO_AUTH?.userId);
        // Initialize messenger on / and /chat where script is loaded and user is authenticated
        const allowedPaths = ['/', '/chat'];
        if (window.GINTO_AUTH?.userId && allowedPaths.includes(window.location.pathname)) {
            console.log('Creating MessengerMultiChat...');
            window.messengerMultiChat = new MessengerMultiChat({
                userId: window.GINTO_AUTH.userId,
                csrfToken: window.GINTO_AUTH.csrfToken,
                userName: window.GINTO_AUTH.userDisplayName || 'User'
            });
            console.log('MessengerMultiChat created');
        }
    };
    
    if (window.GINTO_AUTH?.ready) {
        initMessenger();
    } else if (window.GINTO_AUTH_PROMISE) {
        window.GINTO_AUTH_PROMISE.then(initMessenger);
    } else {
        window.addEventListener('gintoAuthReady', initMessenger, { once: true });
    }
});
