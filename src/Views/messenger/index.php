<?php
/**
 * Ginto Messenger - Facebook Messenger-like Interface
 * Member-to-member chat functionality
 */

// Get theme from cookie or default to dark
$theme = $_COOKIE['theme'] ?? 'dark';
$isDark = $theme === 'dark';
?>
<!doctype html>
<html lang="en" class="<?= $isDark ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- PWA / Fullscreen Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Messenger">
    <meta name="theme-color" content="#000000">
    <meta name="msapplication-navbutton-color" content="#000000">
    <meta name="msapplication-starturl" content="/messenger">
    <link rel="manifest" href="/manifest-messenger.json">
    <link rel="apple-touch-icon" href="/assets/images/ginto.png">
    
    <title>Messenger - Ginto</title>
    <link rel="icon" href="/assets/images/ginto.png" type="image/png">
    
    <!-- Tailwind CSS (local) -->
    <script src="/assets/js/tailwindcss.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        messenger: {
                            blue: '#0084ff',
                            hover: '#0073e6',
                            gray: '#65676b',
                            lightgray: '#e4e6eb',
                            darkgray: '#3a3b3c',
                            bubble: '#0084ff',
                            received: '#3a3b3c'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome (local) -->
    <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
    
    <style>
        /* Theme colors */
        :root {
            --messenger-bg: #ffffff;
            --messenger-bg-secondary: #f0f2f5;
            --messenger-bg-hover: #f0f2f5;
            --messenger-border: #e4e6eb;
            --messenger-text: #050505;
            --messenger-text-secondary: #65676b;
            --messenger-text-muted: #8a8d91;
            --messenger-msg-incoming-bg: #e4e6eb;
            --messenger-msg-incoming-text: #050505;
        }
        .dark {
            --messenger-bg: #000000;
            --messenger-bg-secondary: #242526;
            --messenger-bg-hover: #3a3b3c;
            --messenger-border: #3e4042;
            --messenger-text: #e4e6eb;
            --messenger-text-secondary: #b0b3b8;
            --messenger-text-muted: #8a8d91;
            --messenger-msg-incoming-bg: #3a3b3c;
            --messenger-msg-incoming-text: #e4e6eb;
        }
        
        /* Simple approach - let browser handle everything */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: var(--messenger-bg);
            color: var(--messenger-text);
        }
        
        /* Container uses simple 100% height */
        #messenger-container {
            height: 100%;
            display: flex;
            background: var(--messenger-bg);
        }
        
        /* Hide native scrollbar while preserving scroll functionality */
        .messenger-scroll {
            -ms-overflow-style: none; /* IE and Edge */
            scrollbar-width: none; /* Firefox */
        }
        .messenger-scroll::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        /* Message bubble styles */
        .message-sent {
            background: linear-gradient(to right, #0084ff, #0099ff);
            border-radius: 18px 18px 4px 18px;
            color: white;
        }
        .message-received {
            background: var(--messenger-msg-incoming-bg);
            color: var(--messenger-msg-incoming-text);
            border-radius: 18px 18px 18px 4px;
        }
        
        /* Typing indicator animation */
        .typing-dot {
            animation: typingDot 1.4s infinite;
        }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingDot {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-4px); }
        }
        
        /* YouTube preview card styles */
        .youtube-preview {
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 280px;
            max-width: 100%;
        }
        .youtube-preview:hover .youtube-play-overlay {
            background: rgba(0, 0, 0, 0.2);
        }
        .youtube-preview iframe {
            border-radius: 0;
        }
        .message-sent .youtube-preview {
            background: rgba(0, 0, 0, 0.3);
        }
        .message-received .youtube-preview {
            background: rgba(0, 0, 0, 0.2);
        }
        
        /* Online status pulse */
        .online-pulse {
            animation: onlinePulse 2s infinite;
        }
        @keyframes onlinePulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* Conversation hover effect */
        .conversation-item:hover {
            background: var(--messenger-bg-hover);
        }
        .conversation-item.active {
            background: rgba(0, 132, 255, 0.2);
        }
        
        /* Desktop layout */
        @media (min-width: 769px) {
            .messenger-sidebar {
                width: 320px;
                flex-shrink: 0;
            }
            .mobile-back-btn {
                display: none !important;
            }
        }
        
        /* Mobile layout - Simple flexbox approach */
        @media (max-width: 768px) {
            .messenger-sidebar {
                position: absolute;
                inset: 0;
                width: 100% !important;
                z-index: 50;
                background: var(--messenger-bg);
            }
            .messenger-sidebar.hidden-mobile {
                display: none !important;
            }
            .messenger-main {
                width: 100% !important;
                height: 100%;
            }
            #no-conversation {
                display: none !important;
            }
            .mobile-back-btn {
                display: flex !important;
            }
            /* Active conversation - simple flex layout */
            #active-conversation {
                display: flex;
                flex-direction: column;
                height: 100%;
            }
            #active-conversation.hidden {
                display: none !important;
            }
            /* Messages area scrolls */
            #messages-container {
                flex: 1;
                overflow-y: auto;
                min-height: 0;
            }
            /* Input area at bottom */
            #message-input-area {
                flex-shrink: 0;
            }
        }
        
        /* Safe areas for notch/home indicator */
        .safe-top {
            padding-top: env(safe-area-inset-top, 0);
        }
        .safe-bottom {
            padding-bottom: env(safe-area-inset-bottom, 0);
        }

        /* Enable native overscroll/pull-to-refresh on mobile where appropriate */
        html, body {
            overscroll-behavior-y: auto;
        }

        /* Smooth scrolling for overflow areas on iOS/Android WebViews */
        #messages-container {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: auto; /* allow native pull-to-refresh when at top */
            touch-action: pan-y;
        }
    </style>
</head>

<body class="overflow-hidden" style="background: var(--messenger-bg); color: var(--messenger-text);">
    <!-- Main Container -->
    <div id="messenger-container" class="flex overflow-hidden" style="background: var(--messenger-bg);">
        
        <!-- Left Sidebar - Conversations List -->
        <aside id="messenger-sidebar" class="messenger-sidebar flex flex-col safe-top" style="background: var(--messenger-bg); border-right: 1px solid var(--messenger-border);">
            <!-- Header -->
            <div class="flex-shrink-0 p-4" style="border-bottom: 1px solid var(--messenger-border);">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <a href="/chat" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full transition-colors" title="Back to Chat">
                            <i class="fas fa-arrow-left" style="color: var(--messenger-text-secondary);"></i>
                        </a>
                        <h1 class="text-2xl font-bold" style="color: var(--messenger-text);">Chats</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="new-chat-btn" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full transition-colors" title="New Message">
                            <i class="fas fa-edit text-[#0084ff]"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="search-conversations" 
                        class="w-full border-none rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-[#0084ff] transition-all"
                        style="background: var(--messenger-bg-secondary); color: var(--messenger-text);"
                        placeholder="Search Messenger">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--messenger-text-muted);"></i>
                </div>
            </div>
            
            <!-- Conversations List -->
            <div id="conversations-list" class="flex-1 overflow-y-auto messenger-scroll p-2 safe-bottom">
                <!-- Loading state -->
                <div id="conversations-loading" class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--messenger-text-muted);"></i>
                </div>
                
                <!-- Empty state -->
                <div id="conversations-empty" class="hidden text-center py-8 px-4">
                    <i class="fas fa-comments text-4xl mb-3" style="color: var(--messenger-text-muted);"></i>
                    <p style="color: var(--messenger-text-secondary);">No conversations yet</p>
                    <p class="text-sm mt-1" style="color: var(--messenger-text-muted);">Start chatting with someone!</p>
                </div>
                
                <!-- Conversations will be loaded here -->
            </div>
        </aside>
        
        <!-- Main Chat Area -->
        <main class="messenger-main flex-1 flex flex-col relative" style="background: var(--messenger-bg);">
            <!-- No conversation selected (desktop only) -->
            <div id="no-conversation" class="flex-1 flex flex-col items-center justify-center">
                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-[#0084ff] to-purple-500 flex items-center justify-center mb-4">
                    <i class="fas fa-comments text-4xl text-white"></i>
                </div>
                <h2 class="text-xl font-semibold mb-2" style="color: var(--messenger-text);">Your Messages</h2>
                <p class="text-center max-w-md" style="color: var(--messenger-text-secondary);">
                    Send private messages to members on Ginto
                </p>
                <button onclick="document.getElementById('new-chat-btn').click()" 
                    class="mt-4 px-6 py-2 bg-[#0084ff] hover:bg-[#0073e6] text-white rounded-full font-medium transition-colors">
                    Send Message
                </button>
            </div>
            
            <!-- Active conversation - FULL SCREEN on mobile -->
            <div id="active-conversation" class="hidden flex flex-col h-full">
                <!-- Chat Header - STICKY TOP -->
                <div class="chat-header flex-shrink-0 flex items-center justify-between px-3 py-2 safe-top" style="background: var(--messenger-bg); border-bottom: 1px solid var(--messenger-border);">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <button id="mobile-back-btn" class="mobile-back-btn p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full flex-shrink-0" title="Back">
                            <i class="fas fa-arrow-left text-[#0084ff] text-lg"></i>
                        </button>
                        <div class="relative flex-shrink-0">
                            <div id="chat-avatar" class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold overflow-hidden">
                                <span id="chat-avatar-text">?</span>
                            </div>
                            <span id="chat-online-status" class="hidden absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full" style="border: 2px solid var(--messenger-bg);"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 id="chat-name" class="font-semibold truncate" style="color: var(--messenger-text);">Select a conversation</h3>
                            <p id="chat-status" class="text-xs text-green-500">Active Now</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button id="voice-call-btn" class="p-2.5 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff]" title="Call">
                            <i class="fas fa-phone text-lg"></i>
                        </button>
                        <button id="video-call-btn" class="p-2.5 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff]" title="Video Call">
                            <i class="fas fa-video text-lg"></i>
                        </button>
                        <button id="chat-info-btn" class="p-2.5 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff]" title="Info">
                            <i class="fas fa-info-circle text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Messages Area - SCROLLABLE -->
                <div id="messages-container" class="flex-1 overflow-y-auto messenger-scroll px-3 py-2 min-h-0">
                    <!-- Messages will be loaded here -->
                </div>
                
                <!-- Typing Indicator -->
                <div id="typing-indicator" class="hidden px-3 pb-1">
                    <div class="inline-flex items-center gap-1 rounded-full px-3 py-1.5" style="background: var(--messenger-bg-hover);">
                        <span class="typing-dot w-2 h-2 rounded-full" style="background: var(--messenger-text-muted);"></span>
                        <span class="typing-dot w-2 h-2 rounded-full" style="background: var(--messenger-text-muted);"></span>
                        <span class="typing-dot w-2 h-2 rounded-full" style="background: var(--messenger-text-muted);"></span>
                    </div>
                </div>
                
                <!-- Message Input - STICKY BOTTOM -->
                <div id="message-input-area" class="flex-shrink-0 px-2 py-2 safe-bottom" style="background: var(--messenger-bg); border-top: 1px solid var(--messenger-border);">
                    <div class="flex items-center gap-1">
                        <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff] flex-shrink-0" title="More">
                            <i class="fas fa-plus-circle text-xl"></i>
                        </button>
                        <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff] flex-shrink-0" title="Camera">
                            <i class="fas fa-camera text-xl"></i>
                        </button>
                        <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff] flex-shrink-0" title="Photo">
                            <i class="fas fa-image text-xl"></i>
                        </button>
                        <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full text-[#0084ff] flex-shrink-0" title="Voice">
                            <i class="fas fa-microphone text-xl"></i>
                        </button>
                        <div class="flex-1 relative">
                            <input type="text" id="message-input" 
                                class="w-full rounded-full py-2.5 px-4 pr-10 text-base focus:outline-none focus:ring-1 focus:ring-[#0084ff]"
                                style="background: var(--messenger-bg-secondary); color: var(--messenger-text); border: 1px solid var(--messenger-border);"
                                placeholder="Message" autocomplete="off">
                            <button class="absolute right-3 top-1/2 -translate-y-1/2 text-[#0084ff]" title="Emoji">
                                <i class="fas fa-smile text-lg"></i>
                            </button>
                        </div>
                        <button id="send-btn" class="p-2 text-[#0084ff] flex-shrink-0" title="Send">
                            <i id="send-icon-like" class="fas fa-thumbs-up text-xl"></i>
                            <i id="send-icon-send" class="fas fa-paper-plane text-xl hidden"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- New Chat Modal -->
    <div id="new-chat-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="rounded-xl w-full max-w-md mx-4 shadow-2xl" style="background: var(--messenger-bg-secondary);">
            <div class="flex items-center justify-between p-4" style="border-bottom: 1px solid var(--messenger-border);">
                <h3 class="text-lg font-semibold" style="color: var(--messenger-text);">New Message</h3>
                <button id="close-new-chat" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-full">
                    <i class="fas fa-times" style="color: var(--messenger-text);"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="relative mb-4">
                    <span style="color: var(--messenger-text-muted);" class="mr-2">To:</span>
                    <input type="text" id="search-users" 
                        class="flex-1 bg-transparent border-none focus:outline-none"
                        style="color: var(--messenger-text);"
                        placeholder="Search for people">
                </div>
                <div id="user-search-results" class="max-h-64 overflow-y-auto messenger-scroll">
                    <!-- Search results will appear here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Config -->
    <script>
        window.MESSENGER_CONFIG = {
            csrfToken: '<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES) ?>',
            userId: <?= (int)($userId ?? 0) ?>,
            username: '<?= htmlspecialchars($currentUser['username'] ?? '', ENT_QUOTES) ?>',
            userName: '<?= htmlspecialchars(($currentUser['firstname'] ?? '') ?: ($currentUser['username'] ?? ''), ENT_QUOTES) ?>',
            wsPort: null, // WebSocket disabled - using HTTP polling
            disableWebSocket: true,
            initialConversation: <?= json_encode($_GET['conversation'] ?? null) ?>
        };
        
        // Send button icon toggle (thumbs-up ↔ paper-plane)
        (function() {
            const messageInput = document.getElementById('message-input');
            const likeIcon = document.getElementById('send-icon-like');
            const sendIcon = document.getElementById('send-icon-send');
            
            if (!messageInput || !likeIcon || !sendIcon) return;
            
            function updateSendIcon() {
                const hasText = messageInput.value.trim().length > 0;
                if (hasText) {
                    likeIcon.classList.add('hidden');
                    sendIcon.classList.remove('hidden');
                } else {
                    likeIcon.classList.remove('hidden');
                    sendIcon.classList.add('hidden');
                }
            }
            
            messageInput.addEventListener('input', updateSendIcon);
            messageInput.addEventListener('change', updateSendIcon);
            // Initial state
            updateSendIcon();
        })();
        
        // Mobile keyboard handling - simple scrollIntoView approach
        (function() {
            const messageInput = document.getElementById('message-input');
            const messagesContainer = document.getElementById('messages-container');
            
            if (!messageInput) return;
            
            // When input is focused, scroll messages to bottom after keyboard opens
            messageInput.addEventListener('focus', () => {
                setTimeout(() => {
                    if (messagesContainer) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }, 300);
            });
        })();
        
        // Mobile fullscreen mode - try to hide address bar on Android
        (function() {
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (!isMobile) return;
            
            // Helper to try entering fullscreen
            function tryFullscreen() {
                const el = document.documentElement;
                if (el.requestFullscreen) {
                    el.requestFullscreen().catch(() => {});
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
            }
            
            // On first user interaction, try to enter fullscreen
            let fullscreenAttempted = false;
            function attemptFullscreenOnce() {
                if (fullscreenAttempted) return;
                fullscreenAttempted = true;
                tryFullscreen();
            }
            
            // Listen for first touch/click to trigger fullscreen
            document.addEventListener('touchstart', attemptFullscreenOnce, { once: true, passive: true });
            document.addEventListener('click', attemptFullscreenOnce, { once: true });
            
            // Also try on scroll (helps trigger immersive mode on some browsers)
            window.addEventListener('scroll', function() {
                // Scroll to top trick to hide address bar
                if (window.scrollY === 0) {
                    window.scrollTo(0, 1);
                }
            }, { once: true, passive: true });
            
            // Initial scroll attempt
            setTimeout(() => {
                window.scrollTo(0, 1);
            }, 100);
        })();

        // Allow native pull-to-refresh on Android by removing global overflow hidden
        (function() {
            try {
                if (/Android/i.test(navigator.userAgent)) {
                    document.body.classList.remove('overflow-hidden');
                    // also ensure the body/document can overscroll and messages container allows it
                    try { document.body.style.overflow = ''; } catch (e) {}
                    try { document.documentElement.style.overscrollBehavior = 'auto'; } catch (e) {}
                    const mc = document.getElementById('messages-container');
                    if (mc) {
                        mc.style.overscrollBehavior = 'auto';
                        mc.style.touchAction = 'pan-y';
                    }
                }
            } catch (e) {}
        })();
    </script>
    
    <!-- Messenger JavaScript -->
    <script src="/assets/js/messenger/call-manager.js"></script>
    <script src="/assets/js/messenger/messenger.js"></script>
</body>
</html>
