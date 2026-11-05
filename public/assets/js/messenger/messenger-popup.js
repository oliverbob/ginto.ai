/**
 * Ginto Messenger Popup Module
 * Facebook Messenger-style popup chat window
 */

class MessengerPopup {
    constructor(config) {
        this.config = config;
        this.isOpen = false;
        this.isMinimized = false;
        this.currentConversation = null;
        this.conversations = [];
        this.messages = [];
        this.unreadCount = 0;
        this.menuOpen = false;
        this.selectedUsers = [];
        
        this.init();
    }
    
    /**
     * Get CSRF token from available sources
     */
    getCsrfToken() {
        return window.GINTO_AUTH?.csrfToken || 
               window.GINTO_CONFIG?.csrfToken || 
               window.CSRF_TOKEN || 
               this.config.csrfToken || 
               '';
    }
    
    init() {
        this.createPopupWindow();
        this.bindEvents();
        // Initialize typeahead if available to match main composer behavior
        if (typeof TypeaheadUserSearch !== 'undefined') {
            const inputEl = document.getElementById('messenger-search-users');
            const resultsEl = document.getElementById('messenger-user-results');
            const tagsEl = document.getElementById('messenger-selected-users');
            if (inputEl && resultsEl) {
                this.typeahead = new TypeaheadUserSearch({
                    inputElement: inputEl,
                    resultsElement: resultsEl,
                    tagsContainer: tagsEl,
                    manager: this,
                    mode: 'multi',
                    onUpdate: (users) => {
                        // `users` is an array of user objects from Typeahead
                        this.selectedUsers = users;
                        this.renderSelectedUsers();
                    }
                });
                // Load initial suggestions
                this.typeahead.loadSuggestions();
            }
        }
        this.loadConversations();
        this.pollUnreadCount();
    }
    
    createPopupWindow() {
        const container = document.createElement('div');
        container.id = 'messenger-popup-container';
        container.innerHTML = `
            <!-- Main Messenger Popup Window -->
            <div id="messenger-popup-window" class="hidden fixed bottom-4 right-4 w-[328px] h-[455px] bg-[#242526] rounded-lg shadow-2xl flex flex-col overflow-hidden z-[100]" style="font-family: Segoe UI, Helvetica, Arial, sans-serif;">
                
                <!-- Header - Chat View Style (Facebook exact) -->
                <div id="messenger-popup-header" class="flex items-center justify-between px-2 py-1.5 bg-[#242526] border-b border-[#3e4042]">
                    <div class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer" id="messenger-header-user">
                        <div class="relative flex-shrink-0">
                            <div id="messenger-popup-avatar" class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold overflow-hidden">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2z"/>
                                </svg>
                            </div>
                            <span id="messenger-popup-status" class="hidden absolute bottom-0 right-0 w-2.5 h-2.5 bg-[#31a24c] border-2 border-[#242526] rounded-full"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <span id="messenger-popup-name" class="font-semibold text-[#e4e6eb] text-[13px] truncate">Messenger</span>
                                <svg class="w-3 h-3 text-[#e4e6eb] flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7 10l5 5 5-5z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <button id="messenger-new-chat-header-btn" class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors" title="New message">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button id="messenger-call-btn" class="hidden p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors" title="Start a voice call">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                            </svg>
                        </button>
                        <button id="messenger-video-btn" class="hidden p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors" title="Start a video chat">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                            </svg>
                        </button>
                        <button id="messenger-popup-minimize" class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors" title="Minimize chat">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 13H5v-2h14v2z"/>
                            </svg>
                        </button>
                        <button id="messenger-popup-close" class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors" title="Close chat">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- User Menu Dropdown -->
                <div id="messenger-user-menu" class="hidden absolute top-12 left-2 w-64 bg-[#242526] rounded-lg shadow-xl border border-[#3e4042] z-[110] py-2">
                    <a href="/messenger" class="flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-white text-[15px]">
                        <svg class="w-5 h-5 text-[#0084ff]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Open in Messenger
                    </a>
                    <button id="menu-view-profile" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-[#e4e6eb] text-[15px] text-left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        View profile
                    </button>
                    <div class="border-t border-[#3e4042] my-1"></div>
                    <button id="menu-change-theme" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-[#e4e6eb] text-[15px] text-left">
                        <div class="w-5 h-5 rounded-full bg-[#0084ff]"></div>
                        Change theme
                    </button>
                    <button id="menu-emoji" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-[#e4e6eb] text-[15px] text-left">
                        <span class="text-xl">👍</span>
                        Emoji
                    </button>
                    <button id="menu-nicknames" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-[#e4e6eb] text-[15px] text-left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Nicknames
                    </button>
                    <div class="border-t border-[#3e4042] my-1"></div>
                    <button id="menu-mute" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-[#e4e6eb] text-[15px] text-left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Mute notifications
                    </button>
                    <button id="menu-block" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-[#3a3b3c] text-[#e4e6eb] text-[15px] text-left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Block
                    </button>
                </div>
                
                <!-- Content Area -->
                <div id="messenger-popup-content" class="flex-1 flex flex-col overflow-hidden bg-[#242526]">
                    
                    <!-- Conversations List View -->
                    <div id="messenger-list-view" class="flex-1 flex flex-col">
                        <!-- Search -->
                        <div class="p-2">
                            <div class="relative">
                                <input type="text" id="messenger-popup-search" 
                                    class="w-full bg-[#3a3b3c] border-none rounded-full py-2 pl-9 pr-3 text-[13px] text-[#e4e6eb] placeholder-[#b0b3b8] focus:outline-none"
                                    placeholder="Search Messenger">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#b0b3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Conversations -->
                        <div id="messenger-conversations-list" class="flex-1 overflow-y-auto">
                            <div class="flex items-center justify-center py-8 text-[#b0b3b8]">
                                <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Loading...
                            </div>
                        </div>
                        
                        <!-- New Chat Button -->
                        <div class="p-2 border-t border-[#3e4042]">
                            <button id="messenger-new-chat-btn" class="w-full flex items-center justify-center gap-2 py-2 bg-[#0084ff] hover:bg-[#0073e6] text-white rounded-lg text-[13px] font-semibold transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                                </svg>
                                New Message
                            </button>
                        </div>
                    </div>
                    
                    <!-- Chat View -->
                    <div id="messenger-chat-view" class="hidden flex-1 flex flex-col">
                        <!-- Messages -->
                        <div id="messenger-messages" class="flex-1 overflow-y-auto p-2 space-y-1">
                            <!-- Messages will be inserted here -->
                        </div>
                        
                        <!-- Typing indicator -->
                        <div id="messenger-typing" class="hidden px-3 pb-1">
                            <div class="inline-flex items-center gap-1 bg-[#3a3b3c] rounded-full px-3 py-2">
                                <span class="w-2 h-2 bg-[#b0b3b8] rounded-full animate-bounce"></span>
                                <span class="w-2 h-2 bg-[#b0b3b8] rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                                <span class="w-2 h-2 bg-[#b0b3b8] rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                            </div>
                        </div>
                        
                        <!-- Input Area (Facebook exact style) -->
                        <div class="px-2 py-1.5 bg-[#242526]">
                            <div class="flex items-end gap-1">
                                <!-- Expand button (shown when focused/typing) -->
                                <button id="messenger-expand-btn" class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-all duration-200 ease-out w-0 opacity-0 overflow-hidden" title="Open more actions">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                    </svg>
                                </button>
                                
                                <!-- Left icons (hidden when focused/typing) -->
                                <div id="messenger-left-icons" class="flex items-center overflow-hidden transition-all duration-200 ease-out" style="max-width: 160px; opacity: 1;">
                                    <button class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Voice clip">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                                        </svg>
                                    </button>
                                    <button class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Attach a photo or video">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                                        </svg>
                                    </button>
                                    <button id="messenger-emoji-btn" class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Choose an emoji">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                                        </svg>
                                    </button>
                                    <button class="p-2 hover:bg-[#3a3b3c] rounded-full text-[#0084ff] transition-colors flex-shrink-0" title="Choose a GIF">
                                        <svg class="w-5 h-5 font-bold" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.5 9H13v6h-1.5zM9 9H6c-.6 0-1 .5-1 1v4c0 .5.4 1 1 1h3c.6 0 1-.5 1-1v-2H8.5v1.5h-2v-3H10V10c0-.5-.4-1-1-1zm10 1.5V9h-4.5v6H16v-2h2v-1.5h-2v-1z"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                <!-- Input field -->
                                <div class="flex-1 relative">
                                    <textarea id="messenger-input" 
                                        class="w-full bg-[#3a3b3c] border-none rounded-full py-2 px-3 pr-10 text-[13px] text-[#e4e6eb] placeholder-[#b0b3b8] focus:outline-none resize-none max-h-[80px] transition-all duration-200"
                                        placeholder="Aa" rows="1"></textarea>
                                    <!-- Emoji button inside input (shown when focused/typing) -->
                                    <button id="messenger-emoji-btn-inline" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-[#0084ff] hover:text-[#0073e6] transition-opacity duration-200 opacity-0 pointer-events-none" title="Choose an emoji">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                <!-- Send / Like button -->
                                <button id="messenger-send-btn" class="p-2 text-[#0084ff] hover:bg-[#3a3b3c] rounded-full transition-colors" title="Send a like">
                                    <svg id="messenger-like-icon" class="w-5 h-5 transition-all duration-200" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
                                    </svg>
                                    <svg id="messenger-send-icon" class="w-5 h-5 hidden transition-all duration-200" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- New Chat Modal (supports multi-select for group creation) -->
            <div id="messenger-new-chat-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[110]">
                <div class="bg-[#242526] rounded-xl w-full max-w-sm mx-4 shadow-2xl border border-[#3e4042]">
                    <div class="flex items-center justify-between p-3 border-b border-[#3e4042]">
                        <h3 class="font-semibold text-[#e4e6eb]">New Message</h3>
                        <button id="messenger-close-new-chat" class="p-1 hover:bg-[#3a3b3c] rounded-full text-[#b0b3b8]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="p-3">
                        <input type="text" id="messenger-search-users" 
                            class="w-full bg-[#3a3b3c] border border-[#3e4042] rounded-lg py-2 px-3 text-[13px] text-[#e4e6eb] placeholder-[#b0b3b8] focus:outline-none focus:border-[#0084ff]"
                            placeholder="Search for people...">
                    </div>
                    <div class="px-3">
                        <div id="messenger-selected-users" class="flex gap-2 flex-wrap mb-2 px-1"></div>
                    </div>
                    <div id="messenger-user-results" class="max-h-60 overflow-y-auto px-2 pb-2">
                        <p class="text-center text-[#b0b3b8] text-[13px] py-4">Type to search for users</p>
                    </div>
                    <div class="flex items-center gap-2 p-3 border-t border-[#3e4042]">
                        <button id="messenger-create-group-btn" class="flex-1 py-2 bg-[#0084ff] hover:bg-[#0073e6] text-white rounded-lg text-[13px] font-semibold transition-colors">Create Group</button>
                        <button id="messenger-start-chat-btn" class="py-2 px-3 bg-transparent text-[#0084ff] border border-[#0084ff] rounded-lg text-[13px] font-semibold">Start</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(container);
        this.container = container;
        this.window = document.getElementById('messenger-popup-window');

        // Ensure links inside popup are visible on dark backgrounds
        try {
            const style = document.createElement('style');
            style.id = 'messenger-popup-link-style';
            style.textContent = `
                /* Make links readable inside popup bubbles and menus */
                #messenger-popup-window a { color: #ffffff !important; font-weight: 600 !important; }
                #messenger-popup-window a:hover { color: #ffffff !important; text-decoration: underline; font-weight: 600 !important; }
                #messenger-popup-window .text-blue-400 { color: #ffffff !important; font-weight: 600 !important; }
                #messenger-popup-window .text-[#e4e6eb] a { color: #ffffff !important; font-weight: 600 !important; }
            `;
            document.head.appendChild(style);
        } catch (e) { console.debug('Failed to inject messenger popup link styles', e); }

        // Typeahead is initialized in `init()` after the popup DOM is created.
    }
    
    bindEvents() {
        // Header messenger icon click
        const headerLink = document.getElementById('header-messenger-link');
        if (headerLink) {
            headerLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        }
        
        // Close button — if minimized, restore instead of closing
        document.getElementById('messenger-popup-close').addEventListener('click', () => {
            if (this.isMinimized) {
                this.expand();
            } else {
                this.close();
            }
        });
        
        // Minimize button
        document.getElementById('messenger-popup-minimize').addEventListener('click', () => this.minimize());
        
        // Header user click - toggle menu
        document.getElementById('messenger-header-user').addEventListener('click', (e) => {
            if (this.currentConversation) {
                this.toggleUserMenu();
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('messenger-user-menu');
            const headerUser = document.getElementById('messenger-header-user');
            if (this.menuOpen && !menu.contains(e.target) && !headerUser.contains(e.target)) {
                this.closeUserMenu();
            }
        });
        
        // Search conversations
        document.getElementById('messenger-popup-search').addEventListener('input', (e) => {
            this.filterConversations(e.target.value);
        });
        
        // Voice and Video call buttons - delegate to a single call manager (prefer main `messenger` if present)
        const startCallFromPopup = (callType) => {
            if (!this.currentConversation) return;
            const convo = this.currentConversation;
            // Prefer the main page messenger instance if available to ensure a single call manager
            const manager = window.messenger || window.messengerMultiChat;
            if (!manager) {
                alert('Call service unavailable');
                return;
            }

            // Defensive: avoid starting a call if manager already has one
            if (manager.currentCall) {
                alert('You are already in a call');
                return;
            }

            const convId = convo.id || null;
            const otherUserId = convo.otherUserId || null;
            const displayName = convo.name || convo.display_name || convo.displayName || '';

            try {
                if (typeof manager.startCall === 'function') {
                    // Detect group calls when possible and delegate appropriately
                    const isGroup = (convo.type === 'group') || (convo.otherUserId === 'group') || (Array.isArray(convo.participants) && convo.participants.length > 1);
                    if (manager === window.messengerMultiChat) {
                        manager.startCall(convId, otherUserId, displayName, callType, isGroup, convo.participants || null);
                    } else {
                        // Legacy messenger API - ignore isGroup param
                        manager.startCall(convId, otherUserId, displayName, callType);
                    }
                } else {
                    alert('Call manager not available');
                }
            } catch (e) {
                console.error('Failed to start call from popup:', e);
                alert('Failed to start call');
            }
        };

        document.getElementById('messenger-call-btn').addEventListener('click', () => startCallFromPopup('audio'));
        document.getElementById('messenger-video-btn').addEventListener('click', () => startCallFromPopup('video'));
        
        // New chat
        document.getElementById('messenger-new-chat-btn').addEventListener('click', () => this.openNewChatModal());
        document.getElementById('messenger-new-chat-header-btn').addEventListener('click', () => this.openNewChatModal());
        document.getElementById('messenger-close-new-chat').addEventListener('click', () => this.closeNewChatModal());
        document.getElementById('messenger-new-chat-modal').addEventListener('click', (e) => {
            if (e.target.id === 'messenger-new-chat-modal') this.closeNewChatModal();
        });

        // New chat modal actions
        const createGroupBtn = document.getElementById('messenger-create-group-btn');
        const startChatBtn = document.getElementById('messenger-start-chat-btn');
        if (createGroupBtn) createGroupBtn.addEventListener('click', () => this.createGroupFromModal());
        if (startChatBtn) startChatBtn.addEventListener('click', () => this.startChatFromModal());
        
        // User search
        let searchTimeout;
        document.getElementById('messenger-search-users').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.searchUsers(e.target.value), 300);
        });
        
        // Message input
        const input = document.getElementById('messenger-input');
        const sendBtn = document.getElementById('messenger-send-btn');
        const likeIcon = document.getElementById('messenger-like-icon');
        const sendIcon = document.getElementById('messenger-send-icon');
        const leftIcons = document.getElementById('messenger-left-icons');
        const expandBtn = document.getElementById('messenger-expand-btn');
        const emojiBtn = document.getElementById('messenger-emoji-btn');
        const emojiBtnInline = document.getElementById('messenger-emoji-btn-inline');
        
        // Function to collapse/expand input area like Facebook with smooth animation
        const setInputExpanded = (expanded) => {
            if (expanded) {
                // Collapse left icons, show + button with animation
                leftIcons.style.maxWidth = '0px';
                leftIcons.style.opacity = '0';
                expandBtn.style.width = '36px';
                expandBtn.style.opacity = '1';
                emojiBtnInline.style.opacity = '1';
                emojiBtnInline.style.pointerEvents = 'auto';
            } else {
                // Expand left icons, hide + button with animation
                leftIcons.style.maxWidth = '160px';
                leftIcons.style.opacity = '1';
                expandBtn.style.width = '0px';
                expandBtn.style.opacity = '0';
                emojiBtnInline.style.opacity = '0';
                emojiBtnInline.style.pointerEvents = 'none';
            }
        };
        
        // Expand button click - show left icons again
        expandBtn.addEventListener('click', () => {
            setInputExpanded(false);
            input.focus();
        });
        
        input.addEventListener('focus', () => {
            setInputExpanded(true);
        });
        
        input.addEventListener('blur', () => {
            // Only collapse if empty
            if (!input.value.trim()) {
                setInputExpanded(false);
            }
        });
        
        input.addEventListener('input', () => {
            const hasText = input.value.trim().length > 0;
            if (hasText) {
                likeIcon.classList.add('hidden');
                sendIcon.classList.remove('hidden');
                sendBtn.title = 'Press Enter to send';
                setInputExpanded(true);
            } else {
                likeIcon.classList.remove('hidden');
                sendIcon.classList.add('hidden');
                sendBtn.title = 'Send a like';
            }
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
    
    toggleUserMenu() {
        const menu = document.getElementById('messenger-user-menu');
        this.menuOpen = !this.menuOpen;
        menu.classList.toggle('hidden', !this.menuOpen);
    }
    
    closeUserMenu() {
        const menu = document.getElementById('messenger-user-menu');
        menu.classList.add('hidden');
        this.menuOpen = false;
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        this.window.classList.remove('hidden');
        this.isOpen = true;
        this.isMinimized = false;
        this.loadConversations();
    }
    
    close() {
        this.window.classList.add('hidden');
        this.isOpen = false;
        this.isMinimized = false;
        this.closeUserMenu();
    }
    
    minimize() {
        const content = document.getElementById('messenger-popup-content');
        content.classList.add('hidden');
        this.window.style.height = 'auto';
        this.isMinimized = true;
        
        // Click header to expand
        const header = document.getElementById('messenger-popup-header');
        header.onclick = () => {
            if (this.isMinimized) {
                this.expand();
            }
        };
    }
    
    expand() {
        const content = document.getElementById('messenger-popup-content');
        content.classList.remove('hidden');
        this.window.style.height = '455px';
        this.isMinimized = false;
    }
    
    async loadConversations() {
        const container = document.getElementById('messenger-conversations-list');
        
        try {
            const response = await fetch('/messenger/conversations');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.conversations = data.conversations;
                this.renderConversations();
            } else {
                if (container) {
                    container.innerHTML = `
                        <div class="text-center py-8 text-[#b0b3b8]">
                            <p class="text-[13px]">Failed to load</p>
                            <button onclick="window.messengerPopup?.loadConversations()" class="mt-2 text-[#0084ff] text-[13px] hover:underline">Retry</button>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Failed to load conversations:', error);
            if (container) {
                container.innerHTML = `
                    <div class="text-center py-8 text-[#b0b3b8]">
                        <p class="text-[13px]">Failed to load</p>
                        <button onclick="window.messengerPopup?.loadConversations()" class="mt-2 text-[#0084ff] text-[13px] hover:underline">Retry</button>
                    </div>
                `;
            }
        }
    }
    
    renderConversations() {
        const container = document.getElementById('messenger-conversations-list');
        
        if (this.conversations.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 px-4">
                    <svg class="w-16 h-16 mx-auto text-[#3a3b3c] mb-3" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2z"/>
                    </svg>
                    <p class="text-[#e4e6eb] text-[15px] font-semibold">No conversations yet</p>
                    <p class="text-[#b0b3b8] text-[13px] mt-1">Start chatting with someone!</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.conversations.map(conv => {
            const initial = (conv.display_name || '?')[0].toUpperCase();
            const unreadClass = conv.unread_count > 0 ? 'font-semibold' : '';
            const textColor = conv.unread_count > 0 ? 'text-[#e4e6eb]' : 'text-[#b0b3b8]';
            
            return `
                <div class="messenger-conv-item flex items-center gap-3 px-2 py-2 hover:bg-[#3a3b3c] cursor-pointer transition-colors rounded-lg mx-1"
                     data-conversation-id="${conv.id}"
                     data-display-name="${this.escapeHtml(conv.display_name)}"
                     data-is-online="${conv.is_online ? '1' : '0'}"
                     data-other-user-id="${conv.other_user_id || ''}">
                    <div class="relative flex-shrink-0">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-xl">
                            ${initial}
                        </div>
                        ${conv.is_online ? '<span class="absolute bottom-0.5 right-0.5 w-3.5 h-3.5 bg-[#31a24c] border-2 border-[#242526] rounded-full"></span>' : ''}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="${unreadClass} text-[#e4e6eb] text-[15px] truncate block">${this.escapeHtml(conv.display_name)}</span>
                        <div class="flex items-center gap-1">
                            <span class="${textColor} text-[13px] truncate">${this.escapeHtml(conv.last_message || 'No messages yet')}</span>
                            <span class="text-[#b0b3b8] text-[13px] flex-shrink-0">· ${this.formatTime(conv.last_message_at)}</span>
                        </div>
                    </div>
                    ${conv.unread_count > 0 ? '<span class="w-3 h-3 bg-[#0084ff] rounded-full flex-shrink-0"></span>' : ''}
                </div>
            `;
        }).join('');
        
        // Bind click events (defensive: prevent default and guard DOM manipulations)
        container.querySelectorAll('.messenger-conv-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const convId = parseInt(item.dataset.conversationId);
                const displayName = item.dataset.displayName;
                const isOnline = item.dataset.isOnline === '1';
                const otherUserId = parseInt(item.dataset.otherUserId) || null;
                console.log('messenger-popup: conversation clicked', { convId, displayName, isOnline, otherUserId });
                this.openConversation(convId, displayName, isOnline, otherUserId);
            });
        });
    }
    
    filterConversations(query) {
        const items = document.querySelectorAll('.messenger-conv-item');
        const q = query.toLowerCase();
        
        items.forEach(item => {
            const name = item.dataset.displayName?.toLowerCase() || '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    }
    
    openConversation(conversationId, displayName, isOnline = false, otherUserId = null) {
        this.currentConversation = { id: conversationId, name: displayName, isOnline, otherUserId };

        console.log('messenger-popup: openConversation called', { conversationId, displayName, isOnline, otherUserId });
        try {
            // Update header (guard DOM operations)
            const initial = (displayName || '?')[0].toUpperCase();
            const avatarEl = document.getElementById('messenger-popup-avatar');
            const nameEl = document.getElementById('messenger-popup-name');
            if (avatarEl) avatarEl.textContent = initial;
            if (nameEl) nameEl.textContent = displayName;

            const statusDot = document.getElementById('messenger-popup-status');
            if (statusDot) {
                if (isOnline) {
                    statusDot.classList.remove('hidden');
                } else {
                    statusDot.classList.add('hidden');
                }
            }

            // Show call/video buttons if present
            const callBtn = document.getElementById('messenger-call-btn');
            const videoBtn = document.getElementById('messenger-video-btn');
            if (callBtn) callBtn.classList.remove('hidden');
            if (videoBtn) videoBtn.classList.remove('hidden');

            // Switch views (guarded)
            const listView = document.getElementById('messenger-list-view');
            const chatView = document.getElementById('messenger-chat-view');
            if (listView) listView.classList.add('hidden');
            if (chatView) chatView.classList.remove('hidden');
        } catch (err) {
            console.error('openConversation error (non-fatal):', err);
        }

        // Load messages - always attempt this even if DOM updates failed
        try {
            this.loadMessages(conversationId);
        } catch (err) {
            console.error('loadMessages failed:', err);
        }

        // Focus input if present
        setTimeout(() => {
            try { document.getElementById('messenger-input')?.focus(); } catch (e) {}
        }, 100);
    }
    
    showConversationList() {
        this.currentConversation = null;
        this.closeUserMenu();
        
        // Reset header
        document.getElementById('messenger-popup-name').textContent = 'Messenger';
        document.getElementById('messenger-popup-avatar').innerHTML = `
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2z"/>
            </svg>
        `;
        document.getElementById('messenger-popup-status').classList.add('hidden');
        
        // Hide call/video buttons
        document.getElementById('messenger-call-btn').classList.add('hidden');
        document.getElementById('messenger-video-btn').classList.add('hidden');
        
        // Switch views
        document.getElementById('messenger-chat-view').classList.add('hidden');
        document.getElementById('messenger-list-view').classList.remove('hidden');
        
        this.loadConversations();
    }
    
    async loadMessages(conversationId) {
        const container = document.getElementById('messenger-messages');
        container.innerHTML = `
            <div class="flex justify-center py-4">
                <svg class="w-5 h-5 animate-spin text-[#b0b3b8]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        `;
        
        try {
            const response = await fetch(`/messenger/messages?conversation_id=${conversationId}&limit=50`);
            const data = await response.json();
            
            if (data.success) {
                this.messages = data.messages;
                this.renderMessages();
                
                // Mark as read
                fetch('/messenger/mark-read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: conversationId, csrf_token: this.getCsrfToken() })
                });
            }
        } catch (error) {
            container.innerHTML = '<p class="text-center text-red-500 text-[13px] py-4">Failed to load messages</p>';
        }
    }
    
    renderMessages() {
        const container = document.getElementById('messenger-messages');
        
        if (this.messages.length === 0) {
            const initial = (this.currentConversation?.name || '?')[0].toUpperCase();
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-2xl mb-3">
                        ${initial}
                    </div>
                    <p class="text-[#e4e6eb] text-[15px] font-semibold">${this.escapeHtml(this.currentConversation?.name)}</p>
                    <p class="text-[#b0b3b8] text-[13px] mt-1">Say hi to start the conversation! 👋</p>
                </div>
            `;
            return;
        }
        
        let lastDate = null;
        container.innerHTML = this.messages.map((msg, idx) => {
            const isSent = msg.sender_id === this.config.userId;
            const msgDate = new Date(msg.created_at).toDateString();
            let dateHeader = '';
            
            if (msgDate !== lastDate) {
                lastDate = msgDate;
                dateHeader = `<div class="text-center text-[#b0b3b8] text-[11px] py-2">${this.formatDateHeader(msg.created_at)}</div>`;
            }
            
            // Check if message is a like (👍)
            const isLike = msg.content === '👍';
            
            return `
                ${dateHeader}
                <div class="flex ${isSent ? 'justify-end' : 'justify-start'} group">
                    ${isLike 
                        ? `<span class="text-4xl">${msg.content}</span>`
                        : `<div class="${isSent ? 'bg-[#0084ff]' : 'bg-[#3a3b3c]'} text-[#e4e6eb] max-w-[65%] px-3 py-2 rounded-[18px] text-[15px] break-words">
                            ${this.escapeHtml(msg.content)}
                        </div>`
                    }
                </div>
            `;
        }).join('');
        
        // Add timestamp for last message
        if (this.messages.length > 0) {
            const lastMsg = this.messages[this.messages.length - 1];
            if (lastMsg.sender_id === this.config.userId) {
                container.innerHTML += `<div class="text-right text-[#b0b3b8] text-[11px] pr-1">Sent ${this.formatTime(lastMsg.created_at)}</div>`;
            }
        }
        
        container.scrollTop = container.scrollHeight;
    }
    
    async sendMessage() {
        if (!this.currentConversation) return;
        
        const input = document.getElementById('messenger-input');
        const content = input.value.trim();
        
        if (!content) return;
        
        // Clear input and reset UI state
        input.value = '';
        input.style.height = 'auto';
        document.getElementById('messenger-like-icon').classList.remove('hidden');
        document.getElementById('messenger-send-icon').classList.add('hidden');
        
        // Collapse input area back to show left icons with animation
        const leftIcons = document.getElementById('messenger-left-icons');
        const expandBtn = document.getElementById('messenger-expand-btn');
        const emojiBtnInline = document.getElementById('messenger-emoji-btn-inline');
        
        leftIcons.style.maxWidth = '160px';
        leftIcons.style.opacity = '1';
        expandBtn.style.width = '0px';
        expandBtn.style.opacity = '0';
        emojiBtnInline.style.opacity = '0';
        emojiBtnInline.style.pointerEvents = 'none';
        
        // Optimistic UI update
        const tempMsg = {
            id: Date.now(),
            sender_id: this.config.userId,
            content: content,
            created_at: new Date().toISOString()
        };
        this.messages.push(tempMsg);
        this.renderMessages();
        
        try {
            await fetch('/messenger/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    conversation_id: this.currentConversation.id,
                    content: content,
                    csrf_token: this.getCsrfToken()
                })
            });
        } catch (error) {
            console.error('Send message error:', error);
        }
    }
    
    sendLike() {
        if (!this.currentConversation) return;
        
        // Send 👍 as message
        const tempMsg = {
            id: Date.now(),
            sender_id: this.config.userId,
            content: '👍',
            created_at: new Date().toISOString()
        };
        this.messages.push(tempMsg);
        this.renderMessages();
        
        fetch('/messenger/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            body: JSON.stringify({
                conversation_id: this.currentConversation.id,
                content: '👍',
                csrf_token: this.getCsrfToken()
            })
        });
    }
    
    // New chat modal
    openNewChatModal() {
        document.getElementById('messenger-new-chat-modal').classList.remove('hidden');
        document.getElementById('messenger-search-users').focus();
    }
    
    closeNewChatModal() {
        document.getElementById('messenger-new-chat-modal').classList.add('hidden');
        document.getElementById('messenger-search-users').value = '';
        document.getElementById('messenger-user-results').innerHTML = '<p class="text-center text-[#b0b3b8] text-[13px] py-4">Type to search for users</p>';
        this.selectedUsers = [];
        const sel = document.getElementById('messenger-selected-users');
        if (sel) sel.innerHTML = '';
    }
    
    async searchUsers(query) {
        const container = document.getElementById('messenger-user-results');

        // Prefer the shared Typeahead (initialized in init)
        if (this.typeahead) {
            if (!query || query.trim().length < 2) {
                return this.typeahead.loadSuggestions();
            }
            return this.typeahead.search(query.trim());
        }

        // Fallback simple fetch when Typeahead isn't available
        if (!query || query.length < 2) {
            container.innerHTML = '<p class="text-center text-[#b0b3b8] text-[13px] py-4">Type to search for users</p>';
            return;
        }

        try {
            const response = await fetch(`/messenger/search-users?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.users && data.users.length > 0) {
                container.innerHTML = data.users.map(user => {
                    const initial = (user.display_name || user.username || '?')[0].toUpperCase();
                    return `
                        <div class="messenger-user-item flex items-center gap-3 p-2 rounded-lg hover:bg-[#3a3b3c] cursor-pointer transition-colors"
                             data-user-id="${user.id}"
                             data-display-name="${this.escapeHtml(user.display_name || user.username)}">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold">
                                ${initial}
                            </div>
                            <div class="flex-1">
                                <span class="text-[#e4e6eb] font-medium text-[15px]">${this.escapeHtml(user.display_name || user.username)}</span>
                                <span class="text-[#b0b3b8] text-[13px] block">@${this.escapeHtml(user.username)}</span>
                            </div>
                            <div class="text-sm text-[#b0b3b8] select-none">Add</div>
                        </div>
                    `;
                }).join('');

                container.querySelectorAll('.messenger-user-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const uid = parseInt(item.dataset.userId);
                        const name = item.dataset.displayName;
                        this.toggleSelectUser({ id: uid, display_name: name });
                    });
                });
            } else {
                container.innerHTML = '<p class="text-center text-[#b0b3b8] text-[13px] py-4">No users found</p>';
            }
        } catch (error) {
            container.innerHTML = '<p class="text-center text-red-500 text-[13px] py-4">Search failed</p>';
        }
    }
    
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
                this.closeNewChatModal();
                this.openConversation(data.conversation_id, displayName, false, userId);
            } else {
                alert(data.error || 'Failed to start conversation');
            }
        } catch (error) {
            alert('Failed to start conversation');
        }
    }

    // Toggle select/unselect user in new chat modal
    toggleSelectUser(user) {
        console.debug('popup.toggleSelectUser called with', user);
        const exists = this.selectedUsers.findIndex(u => u.id === user.id);
        if (exists >= 0) {
            this.selectedUsers.splice(exists, 1);
        } else {
            this.selectedUsers.push(user);
        }
        this.renderSelectedUsers();
        // Clear search input and keep focus so tagging additional users works
        const searchInput = document.getElementById('messenger-search-users');
        const results = document.getElementById('messenger-user-results');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        if (results) {
            results.innerHTML = '<p class="text-center text-[#b0b3b8] text-[13px] py-4">Type to search for users</p>';
        }
    }

    renderSelectedUsers() {
        const container = document.getElementById('messenger-selected-users');
        if (!container) return;
        console.debug('popup.renderSelectedUsers current selectedUsers:', this.selectedUsers);
        container.innerHTML = this.selectedUsers.map(u => {
            const name = this.escapeHtml(u.display_name || u.username || `User ${u.id}`);
            return `
                <div class="flex items-center gap-2 bg-[#323335] text-[#e4e6eb] px-2 py-1 rounded-full text-[13px]" data-user-id="${u.id}">
                    <span class="font-medium">${name}</span>
                    <button class="ml-1 text-[#b0b3b8] remove-selected-user">✕</button>
                </div>
            `;
        }).join('');

        container.querySelectorAll('.remove-selected-user').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const parent = e.target.closest('[data-user-id]');
                if (!parent) return;
                const uid = parseInt(parent.dataset.userId);
                this.selectedUsers = this.selectedUsers.filter(u => u.id !== uid);
                this.renderSelectedUsers();
            });
        });
    }

    // Create group from modal selection
    async createGroupFromModal() {
        // Prefer typeahead-selected users if available
        const selected = this.typeahead ? this.typeahead.getSelectedUsers() : this.selectedUsers;
        console.debug('popup.createGroupFromModal selected users:', selected);
        if (!selected || selected.length === 0) {
            alert('Select at least one user to create a group');
            return;
        }

        const userIds = selected.map(u => u.id);
        try {
            const response = await fetch('/messenger/create-group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({ user_ids: userIds, csrf_token: this.getCsrfToken() })
            });
            const data = await response.json();
            if (data.success) {
                this.closeNewChatModal();
                this.openConversation(data.conversation_id, data.group_name || 'Group Chat', null, 'group');
                this.loadConversations();
            } else {
                alert(data.error || 'Failed to create group');
            }
        } catch (e) {
            alert('Failed to create group');
        }
    }

    // Start chat: if one user selected start direct, otherwise create group
    startChatFromModal() {
        const selected = this.typeahead ? this.typeahead.getSelectedUsers() : this.selectedUsers;
        if (selected.length === 1) {
            const u = selected[0];
            this.startConversation(u.id, u.displayName || u.display_name || u.username);
        } else if (selected.length > 1) {
            this.createGroupFromModal();
        } else {
            alert('Select at least one user to start chat');
        }
    }
    
    // Polling
    pollUnreadCount() {
        const updateBadge = async () => {
            try {
                const response = await fetch('/messenger/unread-count');
                const data = await response.json();
                
                if (data.success) {
                    this.unreadCount = data.unread_count;
                    
                    const badges = [
                        document.getElementById('header-messenger-badge'),
                        document.getElementById('messenger-unread-badge'),
                        document.getElementById('mobile-messenger-badge')
                    ];
                    
                    badges.forEach(badge => {
                        if (!badge) return;
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                            badge.classList.remove('hidden');
                            badge.classList.add('flex');
                        } else {
                            badge.classList.add('hidden');
                            badge.classList.remove('flex');
                        }
                    });
                }
            } catch (error) {
                // Silently fail
            }
        };
        
        updateBadge();
        setInterval(updateBadge, 15000);
    }
    
    // Utilities
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatTime(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
        if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
        
        return date.toLocaleDateString();
    }
    
    formatDateHeader(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffDays = Math.floor((now - date) / 86400000);
        
        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Yesterday';
        
        return date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait for auth to be ready before initializing messenger
    const initMessenger = () => {
        const allowedPaths = ['/', '/chat'];
        const headerLink = document.getElementById('header-messenger-link');
        if (window.GINTO_AUTH?.userId && (headerLink || allowedPaths.includes(window.location.pathname))) {
            window.messengerPopup = new MessengerPopup({
                userId: window.GINTO_AUTH.userId,
                csrfToken: window.GINTO_AUTH.csrfToken
            });
        }
    };
    
    // If auth is already ready, init immediately; otherwise wait for the promise
    if (window.GINTO_AUTH?.ready) {
        initMessenger();
    } else if (window.GINTO_AUTH_PROMISE) {
        window.GINTO_AUTH_PROMISE.then(initMessenger);
    } else {
        // Fallback: listen for the custom event
        window.addEventListener('gintoAuthReady', initMessenger, { once: true });
    }
});
