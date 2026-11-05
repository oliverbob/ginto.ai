/**
 * Ginto Messenger - Facebook Messenger-like Interface
 * Real-time messaging with WebSocket support
 */

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

function getAvatarColor(userId) {
    const id = parseInt(userId) || 0;
    return AVATAR_COLORS[id % AVATAR_COLORS.length];
}

function generateGroupAvatar(participants, size = 12) {
    if (!participants || participants.length === 0) {
        const sizeClass = size === 12 ? 'w-12 h-12' : 'w-10 h-10';
        return `<div class="${sizeClass} rounded-full bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center text-white font-semibold text-lg">G</div>`;
    }
    
    const toShow = participants.slice(0, 2);
    const sizeClass = size === 12 ? 'w-12 h-12' : 'w-10 h-10';
    const innerSize = size === 12 ? 'w-8 h-8' : 'w-6 h-6';
    const textSize = size === 12 ? 'text-xs' : 'text-[10px]';
    
    let html = `<div class="${sizeClass} relative">`;
    toShow.forEach((p, idx) => {
        const name = p.fullname || p.display_name || p.firstname || p.username || '?';
        const initial = name.charAt(0).toUpperCase();
        const color = getAvatarColor(p.id || p.user_id);
        const position = idx === 0 ? 'top-0 left-0' : 'bottom-0 right-0';
        html += `<div class="${innerSize} rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white font-semibold ${textSize} absolute ${position}" style="border: 2px solid var(--messenger-bg);">${initial}</div>`;
    });
    html += '</div>';
    return html;
}

class GintoMessenger {
    constructor(config) {
        this.config = config;
        this.currentConversationId = null;
        this.currentConversation = null;
        this.conversations = [];
        this.messages = {};
        this.ws = null;
        this.typingTimeout = null;
        this.isTyping = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        // WebRTC call state
        // `currentCall` will hold call metadata. For group calls we keep
        // `peerConnections` as a map keyed by remote user id and
        // `pendingIceCandidates` as a map of arrays per remote user.
        this.currentCall = null;
        this.callTimerInterval = null;
        this.ringtoneContext = null;
        this.ringtoneInterval = null;
        this.connectionTimeout = null;
        this.pendingIceCandidates = {}; // per-remote-user queued ICE
        
        // Preload audio files for instant playback
        this.sounds = {
            ding: new Audio('/assets/audio/ding.mp3'),
            pop: new Audio('/assets/audio/pop.mp3'),
            ring: new Audio('/assets/audio/ring.mp3')
        };
        // Preload
        Object.values(this.sounds).forEach(audio => {
            audio.preload = 'auto';
            audio.volume = 0.5;
        });
        
        this.init();
    }
    
    init() {
        this.bindElements();
        this.bindEvents();
        this.loadConversations().then(() => {
            // Check for initial conversation to open
            if (this.config.initialConversation) {
                const convId = parseInt(this.config.initialConversation);
                if (convId) {
                    // Try to find conversation in loaded list
                    const conv = this.conversations.find(c => c.id === convId);
                    if (conv) {
                        this.selectConversation(conv);
                    } else {
                        // Create minimal conversation object for direct navigation
                        this.selectConversation({
                            id: convId,
                            display_name: 'Chat',
                            is_online: false,
                            other_user_id: null,
                            type: 'direct'
                        });
                    }
                }
            }
        });
        this.connectWebSocket();
        this.updateOnlineStatus(true);
        
        // Heartbeat for online status
        setInterval(() => this.updateOnlineStatus(true), 30000);
        
        // Auto-resize textarea
        this.setupTextareaAutoResize();
    }
    
    bindElements() {
        this.elements = {
            sidebar: document.getElementById('messenger-sidebar'),
            conversationsList: document.getElementById('conversations-list'),
            conversationsLoading: document.getElementById('conversations-loading'),
            conversationsEmpty: document.getElementById('conversations-empty'),
            noConversation: document.getElementById('no-conversation'),
            activeConversation: document.getElementById('active-conversation'),
            messagesContainer: document.getElementById('messages-container'),
            messageInput: document.getElementById('message-input'),
            sendBtn: document.getElementById('send-btn'),
            chatAvatar: document.getElementById('chat-avatar'),
            chatName: document.getElementById('chat-name'),
            chatStatus: document.getElementById('chat-status'),
            chatOnlineStatus: document.getElementById('chat-online-status'),
            typingIndicator: document.getElementById('typing-indicator'),
            searchConversations: document.getElementById('search-conversations'),
            newChatBtn: document.getElementById('new-chat-btn'),
            newChatModal: document.getElementById('new-chat-modal'),
            closeNewChat: document.getElementById('close-new-chat'),
            searchUsers: document.getElementById('search-users'),
            userSearchResults: document.getElementById('user-search-results'),
            mobileBackBtn: document.getElementById('mobile-back-btn'),
            voiceCallBtn: document.getElementById('voice-call-btn'),
            videoCallBtn: document.getElementById('video-call-btn')
        };
    }
    
    bindEvents() {
        // Send message
        this.elements.sendBtn.addEventListener('click', () => this.sendMessage());
        this.elements.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Typing indicator
        this.elements.messageInput.addEventListener('input', () => this.handleTyping());
        
        // Search conversations
        this.elements.searchConversations.addEventListener('input', (e) => {
            this.filterConversations(e.target.value);
        });
        
        // New chat modal
        this.elements.newChatBtn.addEventListener('click', () => this.openNewChatModal());
        this.elements.closeNewChat.addEventListener('click', () => this.closeNewChatModal());
        this.elements.newChatModal.addEventListener('click', (e) => {
            if (e.target === this.elements.newChatModal) this.closeNewChatModal();
        });
        
        // User search
        let searchTimeout;
        this.elements.searchUsers.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.searchUsers(e.target.value), 300);
        });
        
        // Mobile back button - go back to conversation list
        if (this.elements.mobileBackBtn) {
            this.elements.mobileBackBtn.addEventListener('click', () => {
                this.showConversationList();
            });
        }
        
        // Call buttons
        if (this.elements.voiceCallBtn) {
            this.elements.voiceCallBtn.addEventListener('click', () => {
                if (this.currentConversation) {
                    const other = this.currentConversation.other_user_id || null;
                    this.startCall(this.currentConversationId, other, this.currentConversation.display_name, 'audio');
                }
            });
        }
        if (this.elements.videoCallBtn) {
            this.elements.videoCallBtn.addEventListener('click', () => {
                if (this.currentConversation) {
                    const other = this.currentConversation.other_user_id || null;
                    this.startCall(this.currentConversationId, other, this.currentConversation.display_name, 'video');
                }
            });
        }
        
        // Window focus - mark as online
        window.addEventListener('focus', () => this.updateOnlineStatus(true));
        window.addEventListener('blur', () => this.updateOnlineStatus(false));
        
        // Before unload - mark as offline
        window.addEventListener('beforeunload', () => this.updateOnlineStatus(false));
    }
    
    // Mobile: Show conversation list, hide chat
    showConversationList() {
        this.elements.sidebar.classList.remove('hidden-mobile');
        this.elements.activeConversation.classList.add('hidden');
        this.elements.noConversation.classList.remove('hidden');
        this.currentConversationId = null;
    }
    
    // Mobile: Show chat, hide conversation list
    showChat() {
        this.elements.sidebar.classList.add('hidden-mobile');
        this.elements.noConversation.classList.add('hidden');
        this.elements.activeConversation.classList.remove('hidden');
    }
    
    setupTextareaAutoResize() {
        // No longer needed - using single-line input on mobile
        // Keeping method for compatibility
        const input = this.elements.messageInput;
        if (input && input.tagName === 'TEXTAREA') {
            input.addEventListener('input', () => {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 128) + 'px';
            });
        }
    }
    
    // WebSocket Connection (optional - real-time updates)
    connectWebSocket() {
        // Connect through Caddy proxy (same as chatbox)
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.host;
        const wsUrl = `${protocol}//${host}/messenger-ws/`;
        
        console.log('Connecting to messenger WebSocket:', wsUrl);
        
        try {
            this.ws = new WebSocket(wsUrl);
            
            this.ws.onopen = () => {
                console.log('Messenger WebSocket connected');
                this.reconnectAttempts = 0;
                
                // Authenticate
                this.ws.send(JSON.stringify({
                    type: 'auth',
                    userId: this.config.userId,
                    token: this.config.csrfToken
                }));
                // Ensure we subscribe to the active conversation so the server tracks our presence
                try {
                    const convId = this.currentConversationId || this.currentConversation?.id || null;
                    if (convId) {
                        this.ws.send(JSON.stringify({ type: 'subscribe', conversation_id: convId }));
                        console.log('Sent subscribe for conversation', convId);
                    }
                } catch (e) {
                    console.warn('Failed to send subscribe after WS auth', e);
                }
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                } catch (e) {
                    console.error('WS message parse error:', e);
                }
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket closed, falling back to polling');
                this.ws = null;
                this.startPolling();
            };
            
            this.ws.onerror = (error) => {
                // Silent - onclose will handle fallback
            };
        } catch (e) {
            console.log('WebSocket unavailable, using polling');
            this.startPolling();
        }
    }
    
    // Polling fallback when WebSocket unavailable
    startPolling() {
        if (this.pollingInterval) return; // Already polling
        
        this.pollingInterval = setInterval(() => {
            if (this.currentConversationId) {
                this.loadMessages(this.currentConversationId, true); // silent refresh
            }
            this.loadConversations(true); // silent refresh
        }, 5000); // Poll every 5 seconds
    }
    
    scheduleReconnect() {
        // Don't reconnect - just use polling
        this.startPolling();
    }
    
    handleWebSocketMessage(data) {
        console.log('📨 WS Message received:', data.type, data);
        switch (data.type) {
            case 'message':
                this.handleIncomingMessage(data);
                break;
            case 'typing':
                this.handleTypingIndicator(data);
                break;
            case 'read':
                this.handleReadReceipt(data);
                break;
            case 'online':
                this.handleOnlineStatus(data);
                break;
            // Call signaling
            case 'call_offer':
                console.log('📞 Processing call_offer...');
                this.handleIncomingCall(data);
                break;
            case 'call_join':
                console.log('📞 Processing call_join...');
                this.handleCallJoin(data);
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
        }
    }
    
    handleIncomingMessage(data) {
        const { conversation_id, message } = data;
        
        // Add to messages if conversation is active
        if (conversation_id === this.currentConversationId) {
            this.appendMessage(message);
            this.scrollToBottom();
            this.markAsRead(conversation_id);
        }
        
        // Update conversation list
        this.loadConversations();
        
        // Play notification sound
        this.playNotificationSound();
    }
    
    handleTypingIndicator(data) {
        if (data.conversation_id === this.currentConversationId && data.user_id !== this.config.userId) {
            if (data.is_typing) {
                this.elements.typingIndicator.classList.remove('hidden');
            } else {
                this.elements.typingIndicator.classList.add('hidden');
            }
        }
    }
    
    handleReadReceipt(data) {
        // Update UI to show message was read
        // Could add checkmarks like WhatsApp
    }
    
    handleOnlineStatus(data) {
        // Update online indicators
        const convItem = document.querySelector(`[data-user-id="${data.user_id}"]`);
        if (convItem) {
            const dot = convItem.querySelector('.online-dot');
            if (dot) {
                dot.classList.toggle('hidden', !data.is_online);
            }
        }
        
        // Update chat header if current conversation
        if (this.currentConversation?.other_user_id === data.user_id) {
            this.elements.chatOnlineStatus.classList.toggle('hidden', !data.is_online);
            this.elements.chatStatus.textContent = data.is_online ? 'Active now' : 'Offline';
        }
    }
    
    // API Calls
    async loadConversations(silent = false) {
        try {
            const response = await fetch('/messenger/conversations');
            const data = await response.json();
            
            if (!silent) {
                this.elements.conversationsLoading.classList.add('hidden');
            }
            
            if (data.success && data.conversations.length > 0) {
                this.conversations = data.conversations;
                this.renderConversations();
                this.elements.conversationsEmpty.classList.add('hidden');
            } else if (!silent) {
                this.elements.conversationsEmpty.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Failed to load conversations:', error);
            if (!silent) {
                this.elements.conversationsLoading.classList.add('hidden');
            }
        }
    }
    
    renderConversations() {
        const container = this.elements.conversationsList;
        const loading = this.elements.conversationsLoading;
        const empty = this.elements.conversationsEmpty;
        
        // Clear existing
        container.querySelectorAll('.conversation-item').forEach(el => el.remove());
        
        if (this.conversations.length === 0) {
            empty.classList.remove('hidden');
            return;
        }
        
        empty.classList.add('hidden');
        
        this.conversations.forEach(conv => {
            const div = document.createElement('div');
            div.className = `conversation-item flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors ${
                conv.id === this.currentConversationId ? 'active' : ''
            }`;
            div.dataset.conversationId = conv.id;
            if (conv.other_user_id) div.dataset.userId = conv.other_user_id;
            
            const isGroup = conv.type === 'group' || (conv.participants && conv.participants.length > 0);
            const initial = (conv.display_name || '?')[0].toUpperCase();
            const avatarColor = getAvatarColor(conv.other_user_id || conv.id);
            const unreadBadge = conv.unread_count > 0 
                ? `<span class="bg-[#0084ff] text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">${conv.unread_count}</span>`
                : '';
            
            // Generate avatar - group or individual
            let avatarHtml;
            if (isGroup && conv.participants && conv.participants.length > 0) {
                avatarHtml = generateGroupAvatar(conv.participants, 12);
            } else {
                avatarHtml = `
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white font-semibold text-lg">
                        ${initial}
                    </div>
                `;
            }
            
            div.innerHTML = `
                <div class="relative flex-shrink-0">
                    ${avatarHtml}
                    <span class="online-dot ${conv.is_online ? '' : 'hidden'} absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full" style="border: 2px solid var(--messenger-bg);"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold truncate" style="color: var(--messenger-text);">${this.escapeHtml(conv.display_name)}</h4>
                        <span class="text-xs" style="color: var(--messenger-text-muted);">${this.formatTime(conv.last_message_at)}</span>
                    </div>
                    <p class="text-sm truncate ${conv.unread_count > 0 ? 'font-medium' : ''}" style="color: var(--messenger-text-secondary);">${this.escapeHtml(conv.last_message || 'No messages yet')}</p>
                </div>
                ${unreadBadge}
            `;
            
            div.addEventListener('click', () => this.selectConversation(conv));
            container.appendChild(div);
        });
    }
    
    async selectConversation(conv) {
        this.currentConversationId = conv.id;
        this.currentConversation = conv;
        
        // Update UI - show chat view (handles mobile too)
        this.showChat();
        
        // Update header
        const initial = (conv.display_name || '?')[0].toUpperCase();
        const isGroup = conv.type === 'group' || (conv.participants && conv.participants.length > 0);
        const avatarColor = getAvatarColor(conv.other_user_id || conv.id);
        
        // Update avatar with proper color or group avatar
        if (isGroup && conv.participants && conv.participants.length > 0) {
            this.elements.chatAvatar.innerHTML = generateGroupAvatar(conv.participants, 10);
            this.elements.chatAvatar.className = 'w-10 h-10 flex-shrink-0';
        } else {
            this.elements.chatAvatar.innerHTML = `<span id="chat-avatar-text">${initial}</span>`;
            this.elements.chatAvatar.className = `w-10 h-10 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white font-semibold overflow-hidden`;
        }
        
        this.elements.chatName.textContent = conv.display_name;
        this.elements.chatStatus.textContent = conv.is_online ? 'Active now' : (isGroup ? `${(conv.participants?.length || 0) + 1} members` : 'Offline');
        this.elements.chatOnlineStatus.classList.toggle('hidden', !conv.is_online || isGroup);
        
        // Mark active in list
        document.querySelectorAll('.conversation-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.conversationId) === conv.id);
        });
        
        // Load messages
        await this.loadMessages(conv.id);
        
        // Mark as read
        this.markAsRead(conv.id);
        
        // Focus input
        this.elements.messageInput.focus();
    }
    
    async loadMessages(conversationId, silent = false) {
        if (!silent) {
            this.elements.messagesContainer.innerHTML = '<div class="flex justify-center py-4"><i class="fas fa-spinner fa-spin" style="color: var(--messenger-text-muted);"></i></div>';
        }
        
        try {
            const response = await fetch(`/messenger/messages?conversation_id=${conversationId}`);
            const data = await response.json();
            
            if (data.success) {
                // Only re-render if messages changed
                const oldCount = this.messages[conversationId]?.length || 0;
                const newCount = data.messages.length;
                const oldMessages = this.messages[conversationId] || [];
                
                this.messages[conversationId] = data.messages;
                
                if (!silent || newCount !== oldCount) {
                    this.renderMessages(data.messages);
                    
                    // Play ding if new message received from someone else (during polling)
                    if (silent && newCount > oldCount) {
                        const lastMsg = data.messages[data.messages.length - 1];
                        if (lastMsg && lastMsg.sender_id !== this.config.userId) {
                            this.playNotificationSound();
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load messages:', error);
            if (!silent) {
                this.elements.messagesContainer.innerHTML = '<div class="text-center text-red-500 py-4">Failed to load messages</div>';
            }
        }
    }
    
    generatePrivateChatIntroHeader(conv) {
        if (!conv) return '';
        
        const initial = (conv.display_name || '?')[0].toUpperCase();
        const color = getAvatarColor(conv.other_user_id || conv.id);
        
        // Format member since date
        let memberSinceHtml = '';
        if (conv.member_since) {
            const date = new Date(conv.member_since);
            const options = { year: 'numeric', month: 'long' };
            const formattedDate = date.toLocaleDateString('en-US', options);
            memberSinceHtml = `<p class="text-xs mt-1" style="color: var(--messenger-text-muted);">Member since ${formattedDate}</p>`;
        }
        
        return `
            <div class="text-center py-6 mb-4">
                <div class="flex justify-center mb-3">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white font-semibold text-2xl">${initial}</div>
                </div>
                <h3 class="font-semibold text-lg mb-1" style="color: var(--messenger-text);">${this.escapeHtml(conv.display_name)}</h3>
                ${memberSinceHtml}
                <p class="text-xs max-w-xs mx-auto mt-2" style="color: var(--messenger-text-muted);">You can message and call each other and see info like active status.</p>
            </div>
        `;
    }
    
    generateGroupIntroHeader(conv) {
        if (!conv || !conv.participants || conv.participants.length === 0) return '';
        
        // If only 1 participant (plus current user = 2 people), it's actually a private chat
        if (conv.participants.length <= 1) {
            return this.generatePrivateChatIntroHeader(conv);
        }
        
        // Include current user in the participants display
        const allParticipants = [...conv.participants];
        const currentUserName = this.config.userName || this.config.username || 'You';
        allParticipants.push({
            id: this.config.userId,
            firstname: currentUserName,
            username: this.config.username
        });
        
        // Generate stacked avatars for all participants (max 4 shown)
        const maxShow = Math.min(allParticipants.length, 4);
        let avatarsHtml = '<div class="flex justify-center mb-3"><div class="flex -space-x-3">';
        
        for (let i = 0; i < maxShow; i++) {
            const p = allParticipants[i];
            const name = p.fullname || p.display_name || p.firstname || p.username || '?';
            const initial = name.charAt(0).toUpperCase();
            const color = getAvatarColor(p.id || p.user_id);
            avatarsHtml += `<div class="w-12 h-12 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-white font-semibold text-lg" style="border: 3px solid var(--messenger-bg);">${initial}</div>`;
        }
        
        if (allParticipants.length > maxShow) {
            avatarsHtml += `<div class="w-12 h-12 rounded-full bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center text-white font-semibold text-sm" style="border: 3px solid var(--messenger-bg);">+${allParticipants.length - maxShow}</div>`;
        }
        
        avatarsHtml += '</div></div>';
        
        const memberCount = allParticipants.length;
        const memberText = memberCount === 1 ? '1 member' : `${memberCount} members`;
        
        return `
            <div class="text-center py-6 mb-4">
                ${avatarsHtml}
                <h3 class="font-semibold text-lg mb-1" style="color: var(--messenger-text);">${this.escapeHtml(conv.display_name)}</h3>
                <p class="text-sm mb-2" style="color: var(--messenger-text-secondary);">${memberText}</p>
                <p class="text-xs max-w-xs mx-auto" style="color: var(--messenger-text-muted);">You can message and call each other and see info like active status.</p>
            </div>
        `;
    }
    
    renderMessages(messages) {
        const container = this.elements.messagesContainer;
        container.innerHTML = '';
        
        // Add intro header - group for groups, private for 1-on-1
        const conv = this.currentConversation;
        if (conv) {
            const isGroup = conv.type === 'group' || (conv.participants && conv.participants.length > 1);
            if (isGroup) {
                container.innerHTML = this.generateGroupIntroHeader(conv);
            } else {
                container.innerHTML = this.generatePrivateChatIntroHeader(conv);
            }
        }
        
        if (messages.length === 0) {
            container.innerHTML += `
                <div class="flex flex-col items-center justify-center py-8" style="color: var(--messenger-text-muted);">
                    <i class="fas fa-comments text-4xl mb-2"></i>
                    <p>No messages yet. Say hi! 👋</p>
                </div>
            `;
            return;
        }
        
        let lastSenderId = null;
        let lastDate = null;
        
        messages.forEach((msg, index) => {
            const isSent = msg.sender_id === this.config.userId;
            const isConsecutive = lastSenderId === msg.sender_id;
            const msgDate = new Date(msg.created_at).toDateString();
            
            // Date separator
            if (msgDate !== lastDate) {
                const dateDiv = document.createElement('div');
                dateDiv.className = 'text-center text-xs py-4';
                dateDiv.style.color = 'var(--messenger-text-muted)';
                dateDiv.textContent = this.formatDate(msg.created_at);
                container.appendChild(dateDiv);
                lastDate = msgDate;
            }
            
            const div = document.createElement('div');
            div.className = `flex ${isSent ? 'justify-end' : 'justify-start'} ${isConsecutive ? 'mt-0.5' : 'mt-3'}`;
            
            const bubbleClass = isSent ? 'message-sent' : 'message-received';
            
            const hasYouTube = this.hasYouTubeLink(msg.content);
            const messageContent = this.processMessageContent(msg.content, msg.is_deleted);
            
            div.innerHTML = `
                <div class="${hasYouTube ? 'max-w-[85%]' : 'max-w-[70%]'} group relative">
                    ${!isSent && !isConsecutive ? `<span class="text-xs ml-2 mb-1 block" style="color: var(--messenger-text-muted);">${this.escapeHtml(msg.sender_firstname || msg.sender_username)}</span>` : ''}
                    <div class="${bubbleClass} px-4 py-2 break-words">
                        ${messageContent}
                    </div>
                    <span class="text-xs ${isSent ? 'text-right' : 'text-left'} block mt-1 opacity-0 group-hover:opacity-100 transition-opacity" style="color: var(--messenger-text-muted);">
                        ${this.formatMessageTime(msg.created_at)}
                        ${msg.is_edited ? ' · Edited' : ''}
                    </span>
                </div>
            `;
            
            // Bind YouTube play events
            this.bindYouTubeEvents(div);
            
            container.appendChild(div);
            lastSenderId = msg.sender_id;
        });
        
        this.scrollToBottom();
    }
    
    appendMessage(message) {
        const container = this.elements.messagesContainer;
        const isSent = message.sender_id === this.config.userId;
        const hasYouTube = this.hasYouTubeLink(message.content);
        const messageContent = this.processMessageContent(message.content);
        
        const div = document.createElement('div');
        div.className = `flex ${isSent ? 'justify-end' : 'justify-start'} mt-1`;
        
        const bubbleClass = isSent ? 'message-sent' : 'message-received';
        
        div.innerHTML = `
            <div class="${hasYouTube ? 'max-w-[85%]' : 'max-w-[70%]'} group">
                <div class="${bubbleClass} px-4 py-2 break-words">
                    ${messageContent}
                </div>
                <span class="text-xs ${isSent ? 'text-right' : 'text-left'} block mt-1 opacity-0 group-hover:opacity-100 transition-opacity" style="color: var(--messenger-text-muted);">
                    ${this.formatMessageTime(message.created_at)}
                </span>
            </div>
        `;
        
        // Bind YouTube play events
        this.bindYouTubeEvents(div);
        
        container.appendChild(div);
    }
    
    // Bind click events to YouTube preview cards
    bindYouTubeEvents(container) {
        const previews = container.querySelectorAll('.youtube-preview');
        previews.forEach(preview => {
            preview.addEventListener('click', (e) => {
                // Don't trigger if clicking on an already playing video
                if (e.target.closest('iframe')) return;
                this.playYouTubeVideo(preview);
            });
        });
    }
    
    async sendMessage() {
        const content = this.elements.messageInput.value.trim();
        if (!content || !this.currentConversationId) return;
        
        // Clear input immediately
        this.elements.messageInput.value = '';
        this.elements.messageInput.style.height = 'auto';
        
        // Reset send icon to thumbs-up
        const likeIcon = document.getElementById('send-icon-like');
        const sendIcon = document.getElementById('send-icon-send');
        if (likeIcon && sendIcon) {
            likeIcon.classList.remove('hidden');
            sendIcon.classList.add('hidden');
        }
        
        // Play send sound
        this.playSentSound();
        
        // Optimistic UI update
        const tempMessage = {
            id: Date.now(),
            sender_id: this.config.userId,
            content: content,
            created_at: new Date().toISOString()
        };
        this.appendMessage(tempMessage);
        this.scrollToBottom();
        
        try {
            const response = await fetch('/messenger/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({
                    conversation_id: this.currentConversationId,
                    content: content,
                    csrf_token: this.config.csrfToken
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                console.error('Failed to send message:', data.error);
                // Could show error UI here
            }
            
            // Broadcast via WebSocket
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({
                    type: 'message',
                    conversation_id: this.currentConversationId,
                    message: data.message
                }));
            }
        } catch (error) {
            console.error('Send message error:', error);
        }
    }
    
    handleTyping() {
        if (!this.currentConversationId) return;
        
        // Send typing indicator via WebSocket
        if (this.ws && this.ws.readyState === WebSocket.OPEN && !this.isTyping) {
            this.isTyping = true;
            this.ws.send(JSON.stringify({
                type: 'typing',
                conversation_id: this.currentConversationId,
                is_typing: true
            }));
        }
        
        // Clear previous timeout
        clearTimeout(this.typingTimeout);
        
        // Stop typing after 2 seconds of inactivity
        this.typingTimeout = setTimeout(() => {
            this.isTyping = false;
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({
                    type: 'typing',
                    conversation_id: this.currentConversationId,
                    is_typing: false
                }));
            }
        }, 2000);
    }
    
    async markAsRead(conversationId) {
        try {
            await fetch('/messenger/read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({ 
                    conversation_id: conversationId,
                    csrf_token: this.config.csrfToken
                })
            });
        } catch (error) {
            console.error('Mark as read error:', error);
        }
    }
    
    async updateOnlineStatus(isOnline) {
        try {
            await fetch('/messenger/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({ 
                    is_online: isOnline,
                    csrf_token: this.config.csrfToken
                })
            });
        } catch (error) {
            // Silently fail
        }
    }
    
    // New Chat Modal
    openNewChatModal() {
        this.elements.newChatModal.classList.remove('hidden');
        this.elements.searchUsers.focus();
    }
    
    closeNewChatModal() {
        this.elements.newChatModal.classList.add('hidden');
        this.elements.searchUsers.value = '';
        this.elements.userSearchResults.innerHTML = '';
    }
    
    async searchUsers(query) {
        if (query.length < 2) {
            this.elements.userSearchResults.innerHTML = '';
            return;
        }
        
        try {
            const response = await fetch(`/messenger/search-users?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.users.length > 0) {
                this.renderUserSearchResults(data.users);
            } else {
                this.elements.userSearchResults.innerHTML = '<div class="text-center py-4" style="color: var(--messenger-text-muted);">No users found</div>';
            }
        } catch (error) {
            console.error('Search users error:', error);
        }
    }
    
    renderUserSearchResults(users) {
        const container = this.elements.userSearchResults;
        container.innerHTML = '';
        
        users.forEach(user => {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-3 p-3 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-lg cursor-pointer transition-colors';
            
            const initial = (user.display_name || user.username || '?')[0].toUpperCase();
            const avatarColor = getAvatarColor(user.id);
            
            div.innerHTML = `
                <div class="w-10 h-10 rounded-full bg-gradient-to-br ${avatarColor} flex items-center justify-center text-white font-semibold">
                    ${initial}
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--messenger-text);">${this.escapeHtml(user.display_name || user.username)}</h4>
                    <p class="text-sm" style="color: var(--messenger-text-muted);">@${this.escapeHtml(user.username)}</p>
                </div>
            `;
            
            div.addEventListener('click', () => this.startConversation(user));
            container.appendChild(div);
        });
    }
    
    async startConversation(user) {
        try {
            const response = await fetch('/messenger/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({
                    user_id: user.id,
                    csrf_token: this.config.csrfToken
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.closeNewChatModal();
                await this.loadConversations();
                
                // Find and select the conversation
                const conv = this.conversations.find(c => c.id === data.conversation_id);
                if (conv) {
                    this.selectConversation(conv);
                } else {
                    // Create a minimal conv object
                    this.selectConversation({
                        id: data.conversation_id,
                        display_name: user.display_name || user.username,
                        is_online: false,
                        other_user_id: user.id
                    });
                }
            } else {
                alert(data.error || 'Failed to start conversation');
            }
        } catch (error) {
            console.error('Start conversation error:', error);
            alert('Failed to start conversation');
        }
    }
    
    filterConversations(query) {
        const items = document.querySelectorAll('.conversation-item');
        const q = query.toLowerCase();
        
        items.forEach(item => {
            const name = item.querySelector('h4')?.textContent?.toLowerCase() || '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    }
    
    // Utilities
    scrollToBottom() {
        const container = this.elements.messagesContainer;
        container.scrollTop = container.scrollHeight;
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
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
    
    // Check if text contains a YouTube link
    hasYouTubeLink(text) {
        return this.extractYouTubeId(text) !== null;
    }
    
    // Render YouTube preview card (Facebook-style attachment)
    renderYouTubePreview(videoId, originalUrl) {
        const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
        const fallbackThumbnail = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
        
        return `
            <div class="youtube-preview mt-2 rounded-xl overflow-hidden cursor-pointer" style="background: var(--messenger-bg-secondary);" data-video-id="${videoId}">
                <div class="youtube-thumbnail relative">
                    <img src="${thumbnailUrl}" 
                         onerror="this.src='${fallbackThumbnail}'" 
                         alt="Video thumbnail" 
                         class="w-full aspect-video object-cover">
                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/20 transition-colors youtube-play-overlay">
                        <div class="w-16 h-16 rounded-full bg-red-600 flex items-center justify-center shadow-lg transform hover:scale-110 transition-transform">
                            <i class="fas fa-play text-white text-2xl ml-1"></i>
                        </div>
                    </div>
                </div>
                <div class="youtube-embed hidden">
                    <!-- Iframe will be inserted here when clicked -->
                </div>
                <div class="px-3 py-2 flex items-center gap-2">
                    <i class="fab fa-youtube text-red-500 text-lg"></i>
                    <span class="text-xs truncate" style="color: var(--messenger-text-muted);">YouTube</span>
                </div>
            </div>
        `;
    }
    
    // Handle YouTube play - embed the video
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
                    <iframe 
                        src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen
                        class="w-full h-full">
                    </iframe>
                </div>
            `;
        }
    }
    
    // Process message content - detect links and format
    processMessageContent(content, isDeleted = false) {
        if (isDeleted) {
            return '<em class="text-gray-400">Message deleted</em>';
        }
        
        const escaped = this.escapeHtml(content);
        const videoId = this.extractYouTubeId(content);
        
        if (videoId) {
            // Find the YouTube URL in the content and make it a link
            const urlPattern = /(https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\/[^\s]+)/gi;
            const textWithLink = escaped.replace(urlPattern, '<a href="$1" target="_blank" class="text-messenger-blue hover:underline">$1</a>');
            
            return textWithLink + this.renderYouTubePreview(videoId, content);
        }
        
        // Convert other URLs to clickable links
        const linkPattern = /(https?:\/\/[^\s]+)/gi;
        return escaped.replace(linkPattern, '<a href="$1" target="_blank" class="text-messenger-blue hover:underline">$1</a>');
    }
    
    formatTime(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
        if (diff < 604800000) return Math.floor(diff / 86400000) + 'd';
        
        return date.toLocaleDateString();
    }
    
    formatMessageTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    formatDate(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 86400000);
        
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        
        return date.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' });
    }
    
    playNotificationSound() {
        // Play ding sound for received messages
        try {
            this.sounds.ding.currentTime = 0;
            this.sounds.ding.play().catch(() => {});
        } catch (e) {
            // Audio might be blocked by browser
        }
    }
    
    playSentSound() {
        // Play pop sound for sent messages
        try {
            this.sounds.pop.currentTime = 0;
            this.sounds.pop.play().catch(() => {});
        } catch (e) {
            // Audio might be blocked by browser
        }
    }
    
    // ============ WebRTC Calling Methods ============
    
    wsSend(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            try {
                console.debug('📤 WS send', data.type || '(unknown)', data);
            } catch (e) {}
            this.ws.send(JSON.stringify(data));
            return true;
        }
        return false;
    }
    
    // Start a call (audio or video)
    async startCall(conversationId, otherUserId, displayName, callType = 'audio') {
        if (this.currentCall) {
            alert('Already in a call');
            return;
        }

        // Determine whether this is a group call (conversation has participants)
        let conv = this.currentConversation || {};

        // If conversation metadata doesn't include participants (e.g. small popup),
        // try to refresh conversations to obtain participant list before calling.
        if (conversationId && (!conv.participants || conv.participants.length === 0)) {
            try {
                await this.loadConversations(true);
                const found = this.conversations.find(c => c.id === conversationId);
                if (found) {
                    conv = found;
                    this.currentConversation = found;
                }
            } catch (e) {
                console.warn('Failed to refresh conversations before starting call:', e);
            }
        }
        const isGroup = conv.type === 'group' || (conv.participants && conv.participants.length > 1);

        // Build participant list (exclude self)
        let targets = [];
        if (isGroup && conv.participants) {
            targets = conv.participants.map(p => p.id).filter(id => id && id !== this.config.userId);
        } else if (otherUserId) {
            targets = [otherUserId];
        }

        // Enforce maximum concurrent participants (including you)
        const MAX_PARTICIPANTS = 10;
        if (targets.length + 1 > MAX_PARTICIPANTS) {
            // Trim to max-1 others
            targets = targets.slice(0, MAX_PARTICIPANTS - 1);
        }

        // If no targets were found, but an otherUserId was provided, call that user.
        if (targets.length === 0 && otherUserId) {
            targets = [otherUserId];
        }

        // If still no targets, there's nobody to call
        if (targets.length === 0) {
            alert('No participants available to call in this conversation');
            return;
        }

        // Store call info with per-remote peerConnections
        this.currentCall = {
            conversationId,
            isGroup: !!isGroup,
            participants: targets, // remote ids
            displayName,
            callType,
            isOutgoing: true,
            peerConnections: {}, // targetUserId -> RTCPeerConnection
            localStream: null,
            remoteStreams: {} // targetUserId -> MediaStream
        };

        // Create call UI (tell UI it's a group if applicable)
        this.createCallUI(displayName, callType, true, isGroup, targets);
        this.updateCallStatus('Requesting media...');

        try {
            const constraints = { audio: true, video: callType === 'video' };
            this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);

            // Show local video if video call
            if (callType === 'video') {
                const localVideo = document.getElementById('call-local-video');
                if (localVideo) localVideo.srcObject = this.currentCall.localStream;
            }

            this.updateCallStatus('Calling...');

            // For each target, create a peer connection, add tracks, and send offer
            for (const targetId of targets) {
                try {
                    const pc = await this.createPeerConnectionFor(targetId);
                    // remember under a normalized string key
                    this.currentCall.peerConnections[String(targetId)] = pc;

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

            // Play ringtone while outgoing
            this.playRingtone();

            // Timeout for call setup
            this.connectionTimeout = setTimeout(() => {
                // if none connected after timeout, end call
                const anyConnected = Object.values(this.currentCall.peerConnections || {}).some(pc => pc && pc.connectionState === 'connected');
                if (!anyConnected) {
                    console.log('📞 Group call timeout - no answers');
                    this.updateCallStatus('No answer');
                    setTimeout(() => this.endCall(), 1500);
                }
            }, 45000);

        } catch (error) {
            console.error('Error starting group call:', error);
            this.updateCallStatus('Failed to access media');
            setTimeout(() => this.endCall(), 2000);
        }
    }
    
    // Create WebRTC peer connection
    // Create a peer connection for a specific remote user (targetUserId)
    async createPeerConnectionFor(targetUserId) {
        const tid = String(targetUserId);
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

        // Handle ICE candidates per-target
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                console.log('📞 Sending ICE candidate to', tid);
                this.wsSend({
                    type: 'call_ice',
                    targetUserId: tid,
                    candidate: event.candidate
                });
            }
        };

        pc.oniceconnectionstatechange = () => {
            const state = pc.iceConnectionState;
            console.log('📞 ICE Connection state for', tid, state);
            if (state === 'connected') {
                // Clear timeout if any peer connected
                if (this.connectionTimeout) {
                    clearTimeout(this.connectionTimeout);
                    this.connectionTimeout = null;
                }
                // Clear any pending per-peer disconnect timer
                try { if (pc._disconnectTimer) { clearTimeout(pc._disconnectTimer); pc._disconnectTimer = null; } } catch (e) {}
                this.stopRingtone();
                this.updateCallStatus('Connected');
                this.startCallTimer();
            }
            if (state === 'disconnected' || state === 'failed') {
                // Transient disconnects are possible during accept/offers; wait a short grace period
                try { if (pc._disconnectTimer) clearTimeout(pc._disconnectTimer); } catch (e) {}
                pc._disconnectTimer = setTimeout(() => {
                    try { pc.close(); } catch (e) {}
                    if (this.currentCall && this.currentCall.peerConnections) delete this.currentCall.peerConnections[tid];
                }, 3000);
            }
        };

        pc.ontrack = (event) => {
            console.log('📞 Remote track received from', tid, event.track.kind);
            const remoteStream = event.streams[0];
            this.currentCall.remoteStreams[tid] = remoteStream;

            // Attach to per-target remote element
            const remoteEl = document.getElementById(`call-remote-video-${tid}`) || document.getElementById(`call-remote-audio-${tid}`) || null;
            if (remoteEl) {
                remoteEl.srcObject = remoteStream;
                // Clear waiting placeholder inside the tile if present (non-destructive)
                try {
                    const tile = remoteEl.closest && remoteEl.closest('.call-tile, .relative');
                    if (tile) {
                        const waiting = tile.querySelector('.call-waiting');
                        if (waiting) waiting.remove();
                    }
                } catch (e) { /* ignore */ }

                remoteEl.play?.().catch(e => console.log('Playback blocked', e));
                try { this.updateCallDebugPanel(); } catch (e) { console.debug('updateCallDebugPanel failed', e); }
                console.debug('📞 Attached stream for', tid);
            } else {
                // No per-user element found — create a tile dynamically so the stream is visible
                console.warn('📞 No remote element found for', tid, '- creating UI tile and attaching stream');
                try {
                    const grid = document.querySelector('#call-modal .grid');
                    if (this.currentCall && this.currentCall.callType === 'video' && grid) {
                        const tid = String(tid);
                        const wrapper = document.createElement('div');
                        wrapper.className = 'relative rounded-lg bg-black overflow-hidden';
                        wrapper.dataset.userId = tid;
                        wrapper.innerHTML = `
                            <video id="call-remote-video-${tid}" class="w-full h-40 object-cover rounded-lg bg-black" autoplay playsinline></video>
                            <div class="absolute top-2 right-2 flex gap-2">
                                <button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Expand"><i class="fas fa-expand"></i></button>
                                <button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Mute"><i class="fas fa-volume-up"></i></button>
                            </div>
                        `;
                        grid.appendChild(wrapper);
                        const vid = document.getElementById(`call-remote-video-${tid}`);
                        if (vid) {
                            vid.srcObject = remoteStream;
                            vid.play?.().catch(() => {});
                        }
                    } else if (this.currentCall && this.currentCall.callType === 'audio') {
                        const audioContainer = document.querySelector('#call-modal .space-y-2') || document.querySelector('#call-modal');
                        const tid = String(tid);
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-2 bg-[#1f1f1f] rounded-lg';
                        div.setAttribute('data-user-id', tid);
                        div.innerHTML = `
                            <div class="text-sm text-white">Participant ${tid}</div>
                            <div class="flex items-center gap-2">
                                <audio id="call-remote-audio-${tid}" autoplay playsinline></audio>
                                <button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Mute"><i class="fas fa-volume-up"></i></button>
                                <button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Expand"><i class="fas fa-expand"></i></button>
                            </div>
                        `;
                        (audioContainer || document.getElementById('call-modal')).appendChild(div);
                        const aud = document.getElementById(`call-remote-audio-${tid}`);
                        if (aud) {
                            aud.srcObject = remoteStream;
                            aud.play?.().catch(() => {});
                        }
                    }
                    try { this.updateCallDebugPanel(); } catch (e) { console.debug('updateCallDebugPanel failed', e); }
                } catch (e) {
                    console.warn('Failed to create UI tile for remote stream', e);
                }
            }
        };

        return pc;
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
    
    // Handle incoming call or incoming per-participant offer while already in a group call
    async handleIncomingCall(data) {
        console.log('📞 Incoming call received:', data);
        const { fromUserId, callerName, offer, callType, isGroup, participants, conversationId } = data;

        // If we're already in a call, but this is a group call offer for the same conversation,
        // accept it and create a peer connection for the new participant (join flow).
        if (this.currentCall && this.currentCall.isGroup && isGroup && conversationId && this.currentCall.conversationId === conversationId && offer && fromUserId) {
            console.log('📞 Incoming participant offer during group call from', fromUserId);
            try {
                // Ensure we have local media
                if (!this.currentCall.localStream) {
                    const constraints = { audio: true, video: this.currentCall.callType === 'video' };
                    this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                    // attach local preview if exists
                    const localVid = document.getElementById('call-local-video');
                    if (localVid) localVid.srcObject = this.currentCall.localStream;
                }
                    // Mark that we've received an offer from this participant so we
                    // don't later proactively create a duplicate offer.
                    const tid = String(fromUserId);
                    this.currentCall.offersReceived = this.currentCall.offersReceived || new Set();
                    this.currentCall.offersReceived.add(tid);

                    // Create peer connection for this new participant
                    const pc = await this.createPeerConnectionFor(tid);
                    if (!this.currentCall.peerConnections) this.currentCall.peerConnections = {};
                    this.currentCall.peerConnections[tid] = pc;

                // Add local tracks
                this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

                // Set remote offer and answer
                await pc.setRemoteDescription(new RTCSessionDescription(offer));
                await this.processPendingIceCandidates(tid);
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);

                // Send answer back to the offerer
                this.wsSend({ type: 'call_answer', targetUserId: tid, answer: answer });
                console.log('📞 Sent answer to new participant', tid);
            } catch (e) {
                console.error('Error handling incoming participant offer:', e);
            }
            return;
        }

        // Otherwise, treat as a fresh incoming call
        // Handle race: both sides may create outgoing offers simultaneously.
        // If we already have an outgoing call to the same participant and
        // we receive their offer, treat it as a normal participant offer
        // (create/ensure PC, set remote desc and send answer) instead of
        // immediately rejecting with 'busy'. This lets simultaneous callers
        // connect rather than aborting each other.
        if (this.currentCall && this.currentCall.isOutgoing && offer && fromUserId) {
            const tid = String(fromUserId);
            console.log('📞 Incoming offer matches existing outgoing call — answering for', tid);
            try {
                if (!this.currentCall.localStream) {
                    const constraints = { audio: true, video: this.currentCall.callType === 'video' };
                    try { this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints); } catch (e) { console.debug('Failed to get local media during race handling', e); }
                    if (this.currentCall.callType === 'video') {
                        const lv = document.getElementById('call-local-video'); if (lv) lv.srcObject = this.currentCall.localStream;
                    }
                }

                this.currentCall.offersReceived = this.currentCall.offersReceived || new Set();
                this.currentCall.offersReceived.add(tid);

                // Ensure peer connection exists for this participant
                let pc = (this.currentCall.peerConnections || {})[tid] || null;
                if (!pc) {
                    pc = await this.createPeerConnectionFor(tid);
                    if (!this.currentCall.peerConnections) this.currentCall.peerConnections = {};
                    this.currentCall.peerConnections[tid] = pc;
                    try { if (this.currentCall.localStream) this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream)); } catch (e) { /* ignore */ }
                }

                await pc.setRemoteDescription(new RTCSessionDescription(offer));
                await this.processPendingIceCandidates(tid);
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);

                this.wsSend({ type: 'call_answer', targetUserId: tid, answer: answer });
                console.log('📞 Sent answer (race) to', tid);

                // Defensive retries: remote track/stream attachment may lag slightly
                try {
                    setTimeout(() => { try { /* nothing to do - ontrack handler will attach stream */ } catch (e) {} }, 250);
                    setTimeout(() => { try { } catch (e) {} }, 1000);
                    setTimeout(() => { try { } catch (e) {} }, 3000);
                } catch (e) { /* ignore */ }
            } catch (e) {
                console.error('Error handling incoming offer during race:', e);
            }
            return;
        }

        if (this.currentCall) {
            this.wsSend({ type: 'call_end', targetUserId: fromUserId, reason: 'busy' });
            return;
        }

        this.currentCall = {
            conversationId: conversationId || null,
            otherUserId: fromUserId,
            displayName: callerName,
            callType,
            isOutgoing: false,
            isGroup: !!isGroup,
            participants: Array.isArray(participants) ? participants.map(p => p.id || p).filter(id => id !== undefined && id !== null && String(id) !== String(this.config.userId)) : [],
            peerConnection: null,
            peerConnections: {},
            localStream: null,
            remoteStream: null,
            remoteStreams: {},
            offer: offer
        };

        if (document.hidden || !document.hasFocus()) {
            this.showCallNotification(callerName, callType);
        }

        // Remove any accidental tiles for ourselves if present
        const selfVid = document.getElementById(`call-remote-video-${this.config.userId}`);
        const selfAud = document.getElementById(`call-remote-audio-${this.config.userId}`);
        if (selfVid) selfVid.remove();
        if (selfAud) selfAud.remove();

        this.createIncomingCallUI(callerName, callType);
        this.playRingtone();
    }
    
    // Show browser notification for incoming call
    showCallNotification(callerName, callType) {
        if (Notification.permission === 'granted') {
            this.callNotification = new Notification('Incoming Call', {
                body: `${callerName} is calling you (${callType === 'video' ? 'Video' : 'Voice'} call)`,
                icon: '/assets/images/logo.png',
                requireInteraction: true
            });
        }
    }
    
    closeCallNotification() {
        if (this.callNotification) {
            this.callNotification.close();
            this.callNotification = null;
        }
    }
    
    // Accept incoming call
    async acceptCall() {
        if (!this.currentCall || !this.currentCall.offer || !this.currentCall.otherUserId) return;

        const callerId = this.currentCall.otherUserId;

        this.closeCallNotification();
        const modal = document.getElementById('call-modal'); if (modal) modal.remove();
        // For group calls, build a deduplicated list including the caller and all known participants.
        // To ensure the acceptor sees tiles for everyone, fetch the canonical group member list
        // from the server (same source used by callers) and normalize IDs.
        let targetsForUI = [];
        if (this.currentCall && this.currentCall.isGroup) {
            try {
                const resp = await fetch(`/messenger/group-members/${this.currentCall.conversationId}`);
                const data = await resp.json();
                if (data && data.success && Array.isArray(data.members)) {
                    const me = String(this.config.userId || (window.GINTO_CONFIG && window.GINTO_CONFIG.userId) || '');
                    const set = new Set();
                    data.members.forEach(m => { const id = String(m.id || m.user_id || m.member_id || m); if (id && id !== me) set.add(id); });
                    if (callerId) set.add(String(callerId));
                    targetsForUI = Array.from(set);
                } else {
                    // Fallback to whatever metadata we already have
                    const set = new Set();
                    if (Array.isArray(this.currentCall.participants)) this.currentCall.participants.forEach(p => { if (p != null) set.add(String(p)); });
                    if (callerId) set.add(String(callerId));
                    set.delete(String(this.config.userId));
                    targetsForUI = Array.from(set);
                }
            } catch (e) {
                console.warn('Failed to fetch group members for acceptor UI, falling back to local metadata', e);
                const set = new Set();
                if (Array.isArray(this.currentCall.participants)) this.currentCall.participants.forEach(p => { if (p != null) set.add(String(p)); });
                if (callerId) set.add(String(callerId));
                set.delete(String(this.config.userId));
                targetsForUI = Array.from(set);
            }
        } else {
            targetsForUI = [callerId];
        }

        // Update participants list and create the UI with a complete targets list
        this.currentCall.participants = targetsForUI;
        this.createCallUI(this.currentCall.displayName, this.currentCall.callType, false, this.currentCall.isGroup, targetsForUI);
        this.updateCallStatus('Connecting...');

        this.connectionTimeout = setTimeout(() => {
            const pc = (this.currentCall.peerConnections || {})[String(callerId)];
            const state = pc?.connectionState || pc?.iceConnectionState;
            if (state && state !== 'connected') {
                console.log('📞 Connection timeout - state:', state);
                this.updateCallStatus('Connection failed');
                setTimeout(() => this.endCall(), 1500);
            }
        }, 30000);

        try {
            const constraints = { audio: true, video: this.currentCall.callType === 'video' };
            this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            if (this.currentCall.callType === 'video') {
                const localVideo = document.getElementById('call-local-video'); if (localVideo) localVideo.srcObject = this.currentCall.localStream;
            }

            // Create per-caller peer connection and add tracks
            const cid = String(callerId);
            const pc = await this.createPeerConnectionFor(cid);
            if (!this.currentCall.peerConnections) this.currentCall.peerConnections = {};
            this.currentCall.peerConnections[cid] = pc;

            this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

            await pc.setRemoteDescription(new RTCSessionDescription(this.currentCall.offer));

            // Process any queued ICE for this caller
            await this.processPendingIceCandidates(cid);

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            console.log('📞 Sending call_answer to:', cid);
            this.wsSend({ type: 'call_answer', targetUserId: cid, answer: answer });

            // Notify other participants that we've joined so they can establish peer connections
            if (this.currentCall?.conversationId) {
                this.wsSend({
                    type: 'call_join',
                    conversationId: this.currentCall.conversationId,
                    joiningUserId: this.config.userId,
                    userId: this.config.userId,
                    user_id: this.config.userId,
                    joining_user_id: this.config.userId
                });
            } else {
                // best-effort: send without conversationId if missing
                this.wsSend({
                    type: 'call_join',
                    joiningUserId: this.config.userId,
                    userId: this.config.userId,
                    user_id: this.config.userId,
                    joining_user_id: this.config.userId
                });
            }

            // Help refresh debug UI so callers/participants can see updated state
            try { this.updateCallDebugPanel(); } catch (e) { console.debug('updateCallDebugPanel failed', e); }

            // Do not proactively create offers here; existing participants
            // will send offers to us after they receive our `call_join`.

            this.stopRingtone();

        } catch (error) {
            console.error('Error accepting call:', error);
            this.updateCallStatus('Failed to connect');
            setTimeout(() => this.endCall(), 2000);
        }
    }
    
    // Handle call answer (for caller)
    async handleCallAnswer(data) {
        const from = data.fromUserId || data.sourceUserId || null;
        console.log('📞 Received call_answer from:', from);
        if (!this.currentCall) return;

        // locate peer connection for this responder (normalize key)
        const fid = from != null ? String(from) : from;
        const pc = (this.currentCall.peerConnections || {})[fid] || this.currentCall.peerConnection;
        if (!pc) {
            console.log('📞 No peer connection found for answer from', from);
            return;
        }

        try {
            console.log('📞 Setting remote description from answer for', fid);
            await pc.setRemoteDescription(new RTCSessionDescription(data.answer));
            await this.processPendingIceCandidates(fid);
            this.stopRingtone();
        } catch (error) {
            console.error('Error handling call answer:', error);
        }
    }

    // Handle notification that a participant joined the call
    async handleCallJoin(data) {
        console.log('📞 call_join received:', data);
        let joiningUserId = data.joiningUserId || data.userId || data.user_id || null;
        const conversationId = data.conversationId || data.conversation_id || null;
        console.debug('call_join details', { joiningUserId, conversationId, currentCallConversationId: this.currentCall?.conversationId, participants: this.currentCall?.participants });
        if (joiningUserId != null) joiningUserId = String(joiningUserId);
        if (!joiningUserId || joiningUserId === 'undefined' || joiningUserId === 'null') return;

        // If we're not in a call or not part of the same conversation, ignore
        if (!this.currentCall) return;
        if (conversationId && this.currentCall.conversationId && conversationId !== this.currentCall.conversationId) return;

        // mark user as joined (for UI/offer logic)
        this.currentCall.joinedUsers = this.currentCall.joinedUsers || new Set();
        this.currentCall.joinedUsers.add(joiningUserId);

        // Create UI tile for new participant if not present
        const existingVid = document.getElementById(`call-remote-video-${joiningUserId}`) || document.getElementById(`call-remote-audio-${joiningUserId}`);
        if (!existingVid) {
            // Add element to modal
            const modal = document.getElementById('call-modal');
            if (modal) {
                const container = modal.querySelector('.w-full') || modal;
                if (this.currentCall.callType === 'video') {
                    const grid = modal.querySelector('.grid') || null;
                    if (grid) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'relative rounded-lg bg-black overflow-hidden';
                        wrapper.dataset.userId = joiningUserId;
                        // Add a non-destructive waiting overlay that stays until a remote track attaches
                        const initialChar = (joiningUserId && joiningUserId.toString().charAt(0)) || '?';
                        wrapper.innerHTML = `
                            <div class="call-waiting absolute inset-0 flex flex-col items-center justify-center bg-black/50 text-white">
                                <div class="w-12 h-12 rounded-full bg-gray-400 flex items-center justify-center text-lg font-bold">${initialChar}</div>
                                <div class="mt-2 text-xs opacity-90">Connecting...</div>
                            </div>
                            <video id="call-remote-video-${joiningUserId}" class="w-full h-40 object-cover rounded-lg bg-black" autoplay playsinline></video>
                            <div class="absolute top-2 right-2 flex gap-2">
                                <button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${joiningUserId}" title="Expand"><i class="fas fa-expand"></i></button>
                                <button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${joiningUserId}" title="Mute"><i class="fas fa-volume-up"></i></button>
                            </div>
                        `;
                        grid.appendChild(wrapper);
                    }
                } else {
                    const audDiv = document.createElement('div');
                    audDiv.className = 'flex items-center justify-between p-2 bg-[#1f1f1f] rounded-lg';
                    audDiv.dataset.userId = joiningUserId;
                    const initialChar = (joiningUserId && joiningUserId.toString().charAt(0)) || '?';
                    audDiv.innerHTML = `<div class="flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-gray-400 flex items-center justify-center text-white font-semibold">${initialChar}</div><div class="text-sm text-white">Participant ${joiningUserId}</div></div>` +
                                      `<div class="flex items-center gap-2"><audio id="call-remote-audio-${joiningUserId}" autoplay playsinline></audio>` +
                                      `<button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${joiningUserId}" title="Mute"><i class="fas fa-volume-up"></i></button>` +
                                      `<button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${joiningUserId}" title="Expand"><i class="fas fa-expand"></i></button></div>`;
                    // Insert audio tile at top so it matches grid ordering
                    container.insertBefore(audDiv, container.firstChild);
                }
            }
        }

        // If we're an existing participant in the group call, proactively create
        // a peer connection and send an offer to the joining user so they receive
        // our stream. This runs only after the joining user has signalled `call_join`.
        try {
            const me = String(this.config.userId);
            if (this.currentCall && this.currentCall.isGroup && joiningUserId && joiningUserId !== me) {
                const jtid = String(joiningUserId);
                // don't duplicate
                if (!this.currentCall.peerConnections) this.currentCall.peerConnections = {};
                if (!this.currentCall.peerConnections[jtid]) {
                    // ensure we have local media to share
                    if (!this.currentCall.localStream) {
                        const constraints = { audio: true, video: this.currentCall.callType === 'video' };
                        try {
                            this.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                            const localVid = document.getElementById('call-local-video'); if (localVid) localVid.srcObject = this.currentCall.localStream;
                        } catch (e) {
                            console.warn('Could not get local media when creating offer to joining user', e);
                        }
                    }

                    try {
                        const pc = await this.createPeerConnectionFor(jtid);
                        this.currentCall.peerConnections[jtid] = pc;
                        if (this.currentCall.localStream) this.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, this.currentCall.localStream));

                        const offer = await pc.createOffer();
                        await pc.setLocalDescription(offer);

                        this.wsSend({ type: 'call_offer', targetUserId: jtid, conversationId: this.currentCall.conversationId, offer: offer, callType: this.currentCall.callType, callerName: this.config.userName || 'Someone', isGroup: true });
                        console.log('📞 Sent proactive offer to joining user', jtid);
                    } catch (e) {
                        console.warn('Failed to create/send offer to joining user', joiningUserId, e);
                    }
                }
            }
        } catch (e) {
            console.debug('handleCallJoin proactive-offer flow failed', e);
        }

        // NOTE: Do NOT proactively create peer connections or add local tracks here.
        // Creating offers from existing participants when a user appears in the roster
        // can cause that user's camera to be visible before they accept the call.
        // Instead, keep the UI tile and wait for the joining participant to accept
        // and perform the offer/answer handshake (the joining client will initiate
        // or respond as part of the accept flow).
        const modal = document.getElementById('call-modal');
        if (modal) {
            modal.querySelectorAll('.remote-expand-btn').forEach(btn => {
                btn.removeEventListener('click', this._remoteExpandHandler);
                btn.addEventListener('click', (e) => { const uid = btn.dataset.userId; if (uid) this.openExpandedView(uid, false, this.currentCall.callType); });
            });
            modal.querySelectorAll('.remote-mute-btn').forEach(btn => {
                btn.removeEventListener('click', this._remoteMuteHandler);
                btn.addEventListener('click', (e) => {
                    const uid = btn.dataset.userId;
                    if (!uid) return;
                    const videoEl = document.getElementById(`call-remote-video-${uid}`);
                    const audioEl = document.getElementById(`call-remote-audio-${uid}`) || document.getElementById('call-remote-audio');
                    const el = videoEl || audioEl;
                    if (!el) return;
                    el.muted = !el.muted;
                    const icon = btn.querySelector('i');
                    if (el.muted) { icon.className = 'fas fa-volume-mute'; } else { icon.className = 'fas fa-volume-up'; }
                });
            });
        }
    }
    
    // Handle ICE candidate
    async handleCallIce(data) {
        const from = data.fromUserId || data.sourceUserId || null;
        console.log('📞 Received ICE candidate from:', from);
        if (!this.currentCall) { console.log('📞 No current call for ICE candidate - ignoring'); return; }

        const pc = (this.currentCall.peerConnections || {})[from] || this.currentCall.peerConnection;

        if (!pc || !pc.remoteDescription) {
            console.log('📞 Queuing ICE candidate for', from, '- remote description not set yet');
            if (!this.pendingIceCandidates[from]) this.pendingIceCandidates[from] = [];
            this.pendingIceCandidates[from].push(data.candidate);
            return;
        }

        try {
            await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
            console.log('📞 ICE candidate added successfully for', from);
        } catch (error) {
            console.error('Error adding ICE candidate:', error);
        }
    }
    
    // Process queued ICE candidates
    async processPendingIceCandidates(forUserId = null) {
        if (!this.currentCall) return;

        if (forUserId) {
            const list = this.pendingIceCandidates[forUserId] || [];
            if (!list.length) return;
            const pc = (this.currentCall.peerConnections || {})[forUserId] || this.currentCall.peerConnection;
            while (list.length > 0) {
                const candidate = list.shift();
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(candidate));
                    console.log('📞 Queued ICE candidate added for', forUserId);
                } catch (error) { console.error('Error adding queued ICE candidate for', forUserId, error); }
            }
            return;
        }

        // process all pending
        for (const uid of Object.keys(this.pendingIceCandidates)) {
            await this.processPendingIceCandidates(uid);
        }
    }
    
    // Handle call ended by other party
    handleCallEnded(data) {
        // Only handle call_end if we have an active currentCall and the event
        // relates to that call (by participant or conversation). Ignore stray
        // server messages which can cause the UI to show 'Call ended' when
        // no call is active.
        if (!this.currentCall) {
            console.debug('Ignored call_end: no active call', data);
            return;
        }

        // If the server sent a specific target or fromUserId and it doesn't
        // match our current call participants/otherUser, ignore it.
        const target = data.targetUserId || data.target || data.fromUserId || null;
        if (target) {
            const matchesTarget = String(target) === String(this.currentCall.otherUserId) || String(target) === String(this.currentCall.conversationId) || (this.currentCall.participants && this.currentCall.participants.includes(target));
            if (!matchesTarget) {
                console.debug('Ignored call_end: not relevant to current call', data);
                return;
            }
        }

        this.stopRingtone();
        this.updateCallStatus(data.reason === 'busy' ? 'User is busy' : 'Call ended');
        setTimeout(() => this.endCall(), 1500);
    }
    
    // Handle call unavailable (user offline)
    handleCallUnavailable(data) {
        this.stopRingtone();

        // If this is a group call and the unavailable notice is for a single participant,
        // remove that participant from the call instead of ending the whole call.
        const target = data.targetUserId || data.target || null;
        if (this.currentCall && this.currentCall.isGroup && target) {
            // Remove participant from participants list
            if (Array.isArray(this.currentCall.participants)) {
                this.currentCall.participants = this.currentCall.participants.filter(id => id !== target && id !== String(target) && id !== Number(target));
            }

            // Close and remove peer connection if exists (normalize key)
            const tkey = String(target);
            if (this.currentCall.peerConnections && this.currentCall.peerConnections[tkey]) {
                try { this.currentCall.peerConnections[tkey].close(); } catch (e) {}
                delete this.currentCall.peerConnections[tkey];
            }

            // Remove remote media elements
            const vid = document.getElementById(`call-remote-video-${target}`);
            const aud = document.getElementById(`call-remote-audio-${target}`);
            if (vid) vid.remove();
            if (aud) aud.remove();

            // Update status text briefly
            this.updateCallStatus('Participant offline');

            // If no participants left, end the call
            const remaining = (this.currentCall.participants || []).length;
            if (remaining === 0) {
                setTimeout(() => this.endCall(), 1200);
            }
            return;
        }

        // Default behaviour: end call (for direct calls)
        this.updateCallStatus('User is offline');
        setTimeout(() => this.endCall(), 2000);
    }
    
    // Create call UI
    createCallUI(displayName, callType, isOutgoing, isGroup = false, targets = []) {
        const existingModal = document.getElementById('call-modal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/90 flex items-center justify-center z-[1000]';
        modal.id = 'call-modal';
        
        let videoSection = '';
        // For group calls where this client is the caller (outgoing), do not
        // pre-create per-participant tiles. Tiles will be added dynamically
        // as participants join (via `handleCallJoin` / ontrack).
        if (isGroup && isOutgoing) {
            targets = [];
        }
        if (callType === 'video') {
            if (isGroup && Array.isArray(targets) && targets.length > 0) {
                // Filter out the local user (sometimes present) and normalize IDs
                const filteredTargets = targets
                    .map(t => String(t))
                    .filter(t => t && t !== String(this.config.userId) && t !== 'undefined' && t !== 'null');

                // grid of remote videos with controls per tile
                let remoteTiles = '<div class="grid grid-cols-2 gap-2 mb-4 w-full">';
                filteredTargets.forEach(tid => {
                    remoteTiles += `
                        <div class="relative rounded-lg bg-black overflow-hidden" data-user-id="${tid}">
                            <video id="call-remote-video-${tid}" class="w-full h-40 object-cover rounded-lg bg-black" autoplay playsinline></video>
                            <div class="absolute inset-0 flex items-center justify-center call-waiting">
                                <div class="text-sm text-white call-waiting-label">Waiting for video…</div>
                            </div>
                            <div class="call-play-overlay absolute inset-0 flex items-center justify-center hidden">
                                <button class="play-btn bg-white/10 text-white py-2 px-4 rounded">Click to enable</button>
                            </div>

                            <div class="absolute top-2 right-2 flex gap-2">
                                <button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Expand"><i class="fas fa-expand"></i></button>
                                <button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Mute"><i class="fas fa-volume-up"></i></button>
                            </div>
                        </div>
                    `;
                });
                remoteTiles += '</div>';
                videoSection = `
                    <div class="w-full">${remoteTiles}
                        <div class="mt-2 flex justify-end">
                            <video id="call-local-video" class="w-24 h-18 rounded-lg object-cover border-2 border-white/30" autoplay playsinline muted></video>
                        </div>
                    </div>
                `;
            } else {
                // Non-group (1:1) view — if a single target id is provided, create a per-user tile
                const singleTarget = Array.isArray(targets) && targets.length === 1 ? String(targets[0]) : null;
                if (singleTarget) {
                    videoSection = `
                        <div class="relative w-full h-64 bg-black rounded-lg overflow-hidden mb-4" data-user-id="${singleTarget}">
                            <video id="call-remote-video-${singleTarget}" class="w-full h-full object-cover" autoplay playsinline></video>
                            <div class="absolute inset-0 flex items-center justify-center call-waiting">
                                <div class="text-sm text-white call-waiting-label">Waiting for video…</div>
                            </div>
                            <div class="call-play-overlay absolute inset-0 flex items-center justify-center hidden">
                                <button class="play-btn bg-white/10 text-white py-2 px-4 rounded">Click to enable</button>
                            </div>
                            <video id="call-local-video" class="absolute bottom-2 right-2 w-24 h-18 rounded-lg object-cover border-2 border-white/30" autoplay playsinline muted></video>
                        </div>
                    `;
                } else {
                    videoSection = `
                        <div class="relative w-full h-64 bg-black rounded-lg overflow-hidden mb-4">
                            <video id="call-remote-video" class="w-full h-full object-cover" autoplay playsinline></video>
                            <div class="absolute inset-0 flex items-center justify-center call-waiting">
                                <div class="text-sm text-white call-waiting-label">Waiting for video…</div>
                            </div>
                            <div class="call-play-overlay absolute inset-0 flex items-center justify-center hidden">
                                <button class="play-btn bg-white/10 text-white py-2 px-4 rounded">Click to enable</button>
                            </div>
                            <video id="call-local-video" class="absolute bottom-2 right-2 w-24 h-18 rounded-lg object-cover border-2 border-white/30" autoplay playsinline muted></video>
                        </div>
                    `;
                }
            }
        } else {
            if (isGroup && Array.isArray(targets) && targets.length > 0) {
                // Filter out the local user when building audio list
                const filteredTargets = targets
                    .map(t => String(t))
                    .filter(t => t && t !== String(this.config.userId) && t !== 'undefined' && t !== 'null');

                // audio elements per participant with mute controls
                let audios = '<div class="space-y-2 mb-4">';
                filteredTargets.forEach(tid => {
                    audios += `
                        <div class="flex items-center justify-between p-2 bg-[#1f1f1f] rounded-lg" data-user-id="${tid}">
                            <div class="text-sm text-white">Participant ${tid}</div>
                            <div class="flex items-center gap-2">
                                <audio id="call-remote-audio-${tid}" autoplay playsinline></audio>
                                <button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Mute"><i class="fas fa-volume-up"></i></button>
                                <button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Expand"><i class="fas fa-expand"></i></button>
                            </div>
                        </div>
                    `;
                });
                audios += '</div>';
                videoSection = audios + `
                    <div class="flex items-center justify-center gap-1 my-4">
                        <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 8px;"></div>
                        <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 12px;"></div>
                        <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 16px;"></div>
                        <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 12px;"></div>
                        <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 8px;"></div>
                    </div>
                `;
            } else {
                // Non-group audio: create per-user audio element if single target known
                const singleTargetA = Array.isArray(targets) && targets.length === 1 ? String(targets[0]) : null;
                if (singleTargetA) {
                    videoSection = `
                        <div class="flex items-center justify-center gap-1 my-4" data-user-id="${singleTargetA}">
                            <audio id="call-remote-audio-${singleTargetA}" autoplay playsinline></audio>
                        </div>
                    `;
                } else {
                    videoSection = `
                        <audio id="call-remote-audio" autoplay playsinline></audio>
                        <div class="flex items-center justify-center gap-1 my-4">
                            <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 8px;"></div>
                            <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 12px;"></div>
                            <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 16px;"></div>
                            <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 12px;"></div>
                            <div class="audio-bar w-1 bg-green-500 rounded-full transition-all" style="height: 8px;"></div>
                        </div>
                    `;
                }
            }
        }
        
        modal.innerHTML = `
            <div class="bg-[#242526] rounded-2xl w-[360px] shadow-2xl overflow-hidden">
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

        
        
        modal.querySelector('.call-end-btn').addEventListener('click', () => this.endCall());
        modal.querySelector('.call-mute-btn').addEventListener('click', () => this.toggleMute());
        
        const cameraBtn = modal.querySelector('.call-camera-btn');
        if (cameraBtn) {
            cameraBtn.addEventListener('click', () => this.toggleCamera());
        }

        // Mirror local preview for caller / local preview (standard UX)
        const localVideo = modal.querySelector('#call-local-video');
        if (localVideo) {
            localVideo.style.transform = 'scaleX(-1)';
            localVideo.style.webkitTransform = 'scaleX(-1)';
            localVideo.style.objectFit = 'cover';
            // allow clicking local preview to expand
            localVideo.addEventListener('click', () => this.openExpandedView(this.config.userId, true, callType));
        }

        // Remote tile controls: expand and per-tile mute (do not disconnect)
        modal.querySelectorAll('.remote-expand-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const uid = btn.dataset.userId;
                if (uid) this.openExpandedView(uid, false, callType);
            });
        });

        modal.querySelectorAll('.remote-mute-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const uid = btn.dataset.userId;
                if (!uid) return;
                const videoEl = document.getElementById(`call-remote-video-${uid}`);
                const audioEl = document.getElementById(`call-remote-audio-${uid}`) || document.getElementById('call-remote-audio');
                const el = videoEl || audioEl;
                if (!el) return;
                el.muted = !el.muted;
                const icon = btn.querySelector('i');
                if (el.muted) {
                    icon.className = 'fas fa-volume-mute';
                } else {
                    icon.className = 'fas fa-volume-up';
                }
            });
        });

        // Debug: build a small panel showing participant ids and whether we have a stream for them
        try { this.updateCallDebugPanel(); } catch (e) { console.debug('updateCallDebugPanel failed', e); }
    }
    
    // Debug: show quick diagnostic panel inside the call modal that lists participants and stream status
    updateCallDebugPanel() {
        const modal = document.getElementById('call-modal');
        if (!modal || !this.currentCall) return;
        let panel = modal.querySelector('#call-debug-panel');
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'call-debug-panel';
            panel.style.position = 'absolute';
            panel.style.top = '8px';
            panel.style.right = '8px';
            panel.style.background = 'rgba(0,0,0,0.6)';
            panel.style.color = 'white';
            panel.style.fontSize = '12px';
            panel.style.padding = '6px 8px';
            panel.style.borderRadius = '6px';
            panel.style.zIndex = '2000';
            panel.style.maxWidth = '160px';
            panel.style.lineHeight = '1.2';
            modal.appendChild(panel);
        }

        const peerIds = new Set([...(this.currentCall.participants || []), ...Object.keys(this.currentCall.peerConnections || {})]);
        const lines = [];
        for (const id of peerIds) {
            const sid = String(id);
            const hasStream = !!(this.currentCall.remoteStreams && this.currentCall.remoteStreams[sid]);
            lines.push(`${sid}: ${hasStream ? 'stream' : 'no-stream'}`);
        }
        const local = this.currentCall.localStream ? 'local: yes' : 'local: no';
        panel.innerHTML = `<div style="font-weight:600;margin-bottom:4px">Call debug</div><div>${local}</div>` + lines.map(l => `<div>${l}</div>`).join('');
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

    // Open an expanded overlay view for a participant (or local preview)
    openExpandedView(userId, isLocal = false, callType = 'video') {
        // Remove existing overlay if any
        const existing = document.getElementById('call-expand-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'call-expand-overlay';
        overlay.className = 'fixed inset-0 bg-black/90 z-[1100] flex items-center justify-center';

        const container = document.createElement('div');
        container.className = 'relative w-[90%] max-w-4xl rounded-lg overflow-hidden';

        if (callType === 'video') {
            const bigVideo = document.createElement('video');
            bigVideo.autoplay = true;
            bigVideo.playsInline = true;
            bigVideo.controls = false;
            bigVideo.className = 'w-full h-auto max-h-[80vh] object-contain bg-black';

            // Find source element and copy its stream
            const srcEl = isLocal ? document.getElementById('call-local-video') : document.getElementById(`call-remote-video-${userId}`) || document.getElementById('call-remote-video');
            if (srcEl && srcEl.srcObject) {
                bigVideo.srcObject = srcEl.srcObject;
            }

            // Mirror local preview in overlay as well
            if (isLocal) {
                bigVideo.style.transform = 'scaleX(-1)';
                bigVideo.style.webkitTransform = 'scaleX(-1)';
            }

            container.appendChild(bigVideo);
        } else {
            const bigAudio = document.createElement('audio');
            bigAudio.autoplay = true;
            bigAudio.controls = true;
            const srcElA = document.getElementById(`call-remote-audio-${userId}`) || document.getElementById('call-remote-audio');
            if (srcElA && srcElA.srcObject) bigAudio.srcObject = srcElA.srcObject;
            container.appendChild(bigAudio);
        }

        const closeBtn = document.createElement('button');
        closeBtn.className = 'absolute top-3 right-3 bg-black/60 text-white p-2 rounded';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.addEventListener('click', () => overlay.remove());

        overlay.appendChild(container);
        overlay.appendChild(closeBtn);
        document.body.appendChild(overlay);

        // close on background click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.remove();
        });
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
        this.closeCallNotification();
        if (this.connectionTimeout) { clearTimeout(this.connectionTimeout); this.connectionTimeout = null; }

        if (this.currentCall) {
            // Notify all remote participants that call ended
            if (this.currentCall.isGroup && Array.isArray(this.currentCall.participants)) {
                for (const t of this.currentCall.participants) {
                    this.wsSend({ type: 'call_end', targetUserId: t, reason: 'ended' });
                }
            } else if (this.currentCall.otherUserId) {
                this.wsSend({ type: 'call_end', targetUserId: this.currentCall.otherUserId, reason: 'ended' });
            }

            if (this.currentCall.localStream) {
                this.currentCall.localStream.getTracks().forEach(track => track.stop());
            }

            // Close all peer connections
            if (this.currentCall.peerConnections) {
                for (const pc of Object.values(this.currentCall.peerConnections)) {
                    try { pc.close(); } catch (e) {}
                }
            }

            this.currentCall = null;
        }

        if (this.callTimerInterval) { clearInterval(this.callTimerInterval); this.callTimerInterval = null; }

        const modal = document.getElementById('call-modal'); if (modal) modal.remove();

        this.stopRingtone();
        this.pendingIceCandidates = {};
    }
    
    // Play ringtone
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
            console.log('Could not play ringtone:', e);
        }
    }
    
    // Stop ringtone
    stopRingtone() {
        if (this.ringtoneInterval) {
            clearInterval(this.ringtoneInterval);
            this.ringtoneInterval = null;
        }
        if (this.ringtoneContext) {
            this.ringtoneContext.close();
            this.ringtoneContext = null;
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (window.MESSENGER_CONFIG) {
        window.messenger = new GintoMessenger(window.MESSENGER_CONFIG);
    }
});
