// smartfed.js
(function(window, document, undefined) {
    'use strict';

    class SmartFedApp {
        constructor() {
            console.log("SmartFedApp constructor: Initializing Core UI...");
            this._cacheDOMElements();
            this._bindGlobalEvents();
            this._initPostModal();
            this._initDropdowns();
            this._initDarkMode();
            this._initStickySidebars();
            this._initReactionTooltipsGlobal();
            this._initMobileMenuAction();
            console.log("SmartFedApp constructor: Core UI Initialization complete.");
        }

        /*********************************************************************
         * NEW HELPER FUNCTION
         * Centralizes getting the CSRF token for this entire class.
         *********************************************************************/
        _getCsrfToken() {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            return csrfMeta ? csrfMeta.getAttribute('content') : null;
        }

        _cacheDOMElements() {
            // ... (this method remains unchanged)
            // Post Modal
            this.postModalEl = document.getElementById('postModal');
            this.openPostModalBtn = document.getElementById('openPostModalBtn');
            this.closePostModalBtn = document.getElementById('closePostModalBtn');
            if (this.postModalEl) {
                this.askSaiBtn = this.postModalEl.querySelector('#askSaiBtn');
                this.postModalTextarea = this.postModalEl.querySelector('#postModalTextarea');
                this.postModalPostBtn = this.postModalEl.querySelector('#postModalPostBtn');
            }

            // Header Dropdown Triggers & Elements
            this.notificationBtn = document.getElementById('notificationBtn');
            this.notificationDropdownEl = document.getElementById('notificationDropdown');
            this.chatNotificationsBtn = document.getElementById('messagesBtn');
            this.chatNotificationsDropdownEl = document.getElementById('messagesDropdown');
            this.userMenuBtn = document.getElementById('userMenuBtn');
            this.userDropdownEl = document.getElementById('userDropdown');
            this.searchInputEl = document.getElementById('searchInput');
            this.searchDropdownEl = document.getElementById('searchDropdown');

            this.allManagedDropdowns = [
                this.notificationDropdownEl,
                this.chatNotificationsDropdownEl,
                this.userDropdownEl,
                this.searchDropdownEl
            ].filter(el => el);

            // Dark Mode
            this.darkModeToggleBtn = document.getElementById('darkModeToggle');
            if (this.darkModeToggleBtn) {
                this.lightModeTextEl = this.darkModeToggleBtn.querySelector('.light-mode-text');
                this.darkModeTextEl = this.darkModeToggleBtn.querySelector('.dark-mode-text');
            }

            // Other UI elements
            this.sidebarContainers = document.querySelectorAll('.sidebar-container');
            this.headerEl = document.querySelector('header.fixed');
            const mobileNav = document.querySelector('nav.lg\\:hidden.fixed.bottom-0');
            this.mobileMenuTriggerIcon = mobileNav ? mobileNav.querySelector('a[aria-label="Menu"]') : null;
        }

        _initMobileMenuAction() {
            // ... (this method remains unchanged)
            if (this.mobileMenuTriggerIcon && this.userMenuBtn) {
                this.mobileMenuTriggerIcon.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (this.userMenuBtn) this.userMenuBtn.click(); 
                });
            }
        }

        _bindGlobalEvents() {
            // ... (this method remains unchanged)
            document.addEventListener('click', (e) => {
                this.allManagedDropdowns.forEach(dropdown => {
                    if (dropdown && !dropdown.classList.contains('hidden')) {
                        const trigger = this._getTriggerForDropdown(dropdown);
                        if (trigger && !trigger.contains(e.target) && !dropdown.contains(e.target)) {
                            dropdown.classList.add('hidden');
                        }
                    }
                });
                if (!e.target.closest('.reaction-btn')) {
                    document.querySelectorAll('.reaction-tooltip.flex').forEach(tooltip => {
                        tooltip.classList.add('hidden');
                        tooltip.classList.remove('flex');
                    });
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.allManagedDropdowns.forEach(dd => { if (dd) dd.classList.add('hidden'); });
                    
                    const isAIGenerating = window.globalPostFeedManager && window.globalPostFeedManager.isAIGenerating;
                    if (this.postModalEl && !this.postModalEl.classList.contains('hidden') && !isAIGenerating) {
                        this.closePostModal();
                    }
                }
            });
            window.addEventListener('scroll', () => this._handleSidebarScroll());
            if (this.headerEl) {
                 window.dispatchEvent(new Event('scroll'));
            } else {
                setTimeout(() => {
                    if (!this.headerEl) this.headerEl = document.querySelector('header.fixed');
                    window.dispatchEvent(new Event('scroll'));
                }, 100);
            }
        }
        
        _getTriggerForDropdown(dropdownElement) {
            // ... (this method remains unchanged)
            if (dropdownElement === this.notificationDropdownEl) return this.notificationBtn;
            if (dropdownElement === this.chatNotificationsDropdownEl) return this.chatNotificationsBtn;
            if (dropdownElement === this.userDropdownEl) return this.userMenuBtn;
            if (dropdownElement === this.searchDropdownEl) return this.searchInputEl;
            return null;
        }

        _initDropdowns() {
            // ... (this method remains unchanged)
            const setupDropdownToggle = (btn, dropdown, isSearchInput = false) => {
                if (btn && dropdown) {
                    const eventType = isSearchInput ? 'focus' : 'click';
                    
                    btn.addEventListener(eventType, (e) => {
                        if (!isSearchInput) e.stopPropagation();
                        const isCurrentlyHidden = dropdown.classList.contains('hidden');
                        this.allManagedDropdowns.forEach(otherDropdown => {
                            if (otherDropdown && otherDropdown !== dropdown && !otherDropdown.classList.contains('hidden')) {
                                otherDropdown.classList.add('hidden');
                            }
                        });
                        if (isCurrentlyHidden) {
                            dropdown.classList.remove('hidden');
                        } else if (!isSearchInput) {
                            dropdown.classList.add('hidden');
                        }
                        if (dropdown === this.chatNotificationsDropdownEl && !dropdown.classList.contains('hidden') &&
                            window.globalChatNotificationManager && typeof window.globalChatNotificationManager.refreshAllTimeAgoToNow === 'function') {
                            window.globalChatNotificationManager.refreshAllTimeAgoToNow();
                        }
                    });
                    if (isSearchInput) {
                        btn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            if (dropdown.classList.contains('hidden')) {
                                 this.allManagedDropdowns.forEach(otherDropdown => {
                                    if (otherDropdown && otherDropdown !== dropdown && !otherDropdown.classList.contains('hidden')) {
                                        otherDropdown.classList.add('hidden');
                                    }
                                });
                                dropdown.classList.remove('hidden');
                            }
                        });
                    }
                }
            };
            if (this.notificationBtn) setupDropdownToggle(this.notificationBtn, this.notificationDropdownEl);
            if (this.chatNotificationsBtn) setupDropdownToggle(this.chatNotificationsBtn, this.chatNotificationsDropdownEl);
            if (this.userMenuBtn) setupDropdownToggle(this.userMenuBtn, this.userDropdownEl);
            if (this.searchInputEl) setupDropdownToggle(this.searchInputEl, this.searchDropdownEl, true);
        }

        /*********************************************************************
         * REFACTORED FUNCTION
         * The `fetch` call inside this method now includes the CSRF token.
         *********************************************************************/
        _initPostModal() {
            if (this.openPostModalBtn) this.openPostModalBtn.addEventListener('click', () => this.openPostModal());
            if (this.closePostModalBtn) this.closePostModalBtn.addEventListener('click', () => this.closePostModal());

            if (this.postModalEl) {
                this.postModalEl.addEventListener('click', (e) => {
                    const isAIGenerating = window.globalPostFeedManager && window.globalPostFeedManager.isAIGenerating;
                    if (e.target === this.postModalEl && !isAIGenerating) {
                        this.closePostModal();
                    }
                });
            }

            if (this.askSaiBtn) {
                this.askSaiBtn.addEventListener('click', () => {
                    if (!window.globalPostFeedManager) {
                        alert("AI features are not available at the moment.");
                        return;
                    }
                    if (window.globalPostFeedManager.isAIGenerating) {
                        window.globalPostFeedManager.stopAIGeneration();
                    } else {
                        const prompt = this.postModalTextarea ? this.postModalTextarea.value.trim() : "";
                        if (!prompt) {
                            alert("Please enter a prompt for Sai.");
                            return;
                        }
                        window.globalPostFeedManager.startAIGeneration(prompt);
                    }
                });
            }

            if (this.postModalPostBtn) {
                this.postModalPostBtn.addEventListener('click', async () => {
                    if (!this.postModalTextarea || !this.postModalEl) return;

                    const content = this.postModalTextarea.value.trim();
                    const visibilitySelect = this.postModalEl.querySelector('select[aria-label="Post audience"]');
                    const visibility = visibilitySelect ? visibilitySelect.value : 'public';
                    const originalPostIdToShare = this.postModalEl.dataset.sharingOriginalPostId;

                    if (!originalPostIdToShare && !content) {
                        alert("Cannot post empty content.");
                        return;
                    }

                    this.postModalPostBtn.disabled = true;
                    this.postModalPostBtn.textContent = 'Posting...';

                    let apiEndpoint;
                    let payload = {
                        content: content,
                        visibility: visibility
                    };

                    // Choose endpoint based on current page. If we're on /social use the social routes.
                    const apiPrefix = window.location.pathname && window.location.pathname.indexOf('/social') === 0 ? '/social' : '';
                    if (originalPostIdToShare) {
                        // Server expects 'shared_post_id' on the standard post endpoint
                        apiEndpoint = apiPrefix + '/post';
                        payload.shared_post_id = originalPostIdToShare;
                        payload.post_type = 'share';
                    } else {
                        apiEndpoint = apiPrefix + '/post';
                        payload.post_type = 'text';
                    }

                    // --- START OF MODIFICATION ---
                    const csrfToken = this._getCsrfToken(); // Get the token
                    if (!csrfToken) {
                        alert('Could not verify request. Please refresh the page.');
                        this.postModalPostBtn.disabled = false;
                        this.postModalPostBtn.textContent = 'Post';
                        return;
                    }
                    // --- END OF MODIFICATION ---

                    try {
                        // Send JSON body (server expects JSON for SocialController::post)
                        // Include the CSRF token both in headers and the JSON payload
                        payload.csrf_token = csrfToken;

                        const response = await fetch(apiEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': csrfToken
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload)
                        });

                        const result = await response.json();

                        if (response.ok && result.success && result.post) {
                            if (window.globalPostFeedManager && typeof window.globalPostFeedManager.prependNewPost === 'function') {
                                window.globalPostFeedManager.prependNewPost(result.post);
                            }
                            // If the original post view modal (PFM) is open behind this share modal, close it.
                            try {
                                const pfmModal = document.getElementById('pfmPostViewModal');
                                if (pfmModal && !pfmModal.classList.contains('hidden')) {
                                    if (window.globalPostFeedManager && typeof window.globalPostFeedManager._closePostViewModal === 'function') {
                                        window.globalPostFeedManager._closePostViewModal();
                                    } else {
                                        pfmModal.classList.add('hidden');
                                        document.body.style.overflow = '';
                                    }
                                }
                            } catch (err) {
                                console.warn('[SmartFed openPostModal] Error closing original post modal:', err);
                            }
                            this.closePostModal();
                        } else {
                            // Log details for debugging
                            console.error('Post API response error:', {
                                status: response.status,
                                statusText: response.statusText,
                                body: result
                            });
                            // Handle CSRF error specifically if possible
                            if (response.status === 403) {
                                alert(result.error || 'Your session may have expired. Please refresh the page and try again.');
                            } else {
                                alert(`Failed to ${originalPostIdToShare ? 'share' : 'create'} post: ${result.message || result.error || 'Unknown server error'}`);
                            }
                        }
                    } catch (error) {
                        console.error(`Error ${originalPostIdToShare ? 'sharing' : 'creating'} post:`, error);
                        alert(`An error occurred while ${originalPostIdToShare ? 'sharing' : 'creating'} the post.`);
                    } finally {
                        this.postModalPostBtn.disabled = false;
                        this.postModalPostBtn.textContent = 'Post';
                    }
                });
            }
        }

        // --- THE REST OF THE FILE REMAINS UNCHANGED ---
        updateModalUIAfterAIStateChange(isAIGenerating) {
            // ... (this method remains unchanged)
            if (this.askSaiBtn && this.postModalPostBtn && this.postModalTextarea) {
                const iconEl = this.askSaiBtn.querySelector('i');
                const textEl = this.askSaiBtn.querySelector('span');
                if (isAIGenerating) {
                    this.askSaiBtn.classList.remove('bg-pink-600', 'hover:bg-pink-700');
                    this.askSaiBtn.classList.add('bg-red-600', 'hover:bg-red-700');
                    if (iconEl) { iconEl.classList.remove('fa-magic'); iconEl.classList.add('fa-stop'); }
                    if (textEl) textEl.textContent = 'Stop Sai';
                    this.postModalPostBtn.disabled = true;
                    this.postModalPostBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    this.postModalTextarea.disabled = true;
                } else {
                    this.askSaiBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    this.askSaiBtn.classList.add('bg-pink-600', 'hover:bg-pink-700');
                    if (iconEl) { iconEl.classList.remove('fa-stop'); iconEl.classList.add('fa-magic'); }
                    if (textEl) textEl.textContent = 'Ask Sai';
                    this.postModalPostBtn.disabled = false;
                    this.postModalPostBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    this.postModalTextarea.disabled = false;
                }
            }
        }
        
        hidePostModalProgrammatically() {
            // ... (this method remains unchanged)
            if (this.postModalEl) {
                this.postModalEl.classList.add('hidden');
            }
        }

        openPostModal(context = {}) {
            // ... (this method remains unchanged)
            console.log('[SmartFed openPostModal] Method called. Received context:', JSON.stringify(context));

            if (window.globalPostFeedManager && typeof window.globalPostFeedManager.cleanupTemporaryAIPost === 'function') {
                window.globalPostFeedManager.cleanupTemporaryAIPost(false);
            }

            if (!this.postModalEl) {
                console.error("[SmartFed openPostModal] Post modal element (this.postModalEl) not found!");
                return;
            }
            // Ensure the modal is placed at the end of <body> and above other elements
            try {
                if (this.postModalEl.parentNode !== document.body) document.body.appendChild(this.postModalEl);
            } catch (e) {
                console.warn('[SmartFed openPostModal] Could not re-append modal to body:', e);
            }
            // Force a high stacking context so modal always appears on top of post elements
            this.postModalEl.style.zIndex = '99999';
            this.postModalEl.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            const sharePreviewContainer = this.postModalEl.querySelector('#sharePreviewContainer');

            if (this.postModalTextarea) {
                this.postModalTextarea.value = '';
                this.postModalTextarea.disabled = false;
                
                if (context && typeof context === 'object' && context.isSharing === true && 
                    context.originalPostId !== null && context.originalPostId !== undefined && 
                    String(context.originalPostId).trim() !== "" && String(context.originalPostId).trim() !== "undefined") {
                    
                    const originalPostIdStr = String(context.originalPostId).trim();
                    this.postModalTextarea.placeholder = "Add your thoughts to this share...";
                    this.postModalEl.dataset.sharingOriginalPostId = originalPostIdStr; 
                    console.log(`[SmartFed openPostModal] SHARING MODE. Modal dataset.sharingOriginalPostId SET TO: "${this.postModalEl.dataset.sharingOriginalPostId}"`);

                    if (sharePreviewContainer) {
                        sharePreviewContainer.innerHTML = '';
                        const originalPostElement = document.querySelector(`.post-item[data-post-id="${originalPostIdStr}"]`);
                        if (originalPostElement) {
                            const originalAuthorEl = originalPostElement.querySelector('a[href^="/profile/"]');
                            const originalAuthorName = originalAuthorEl ? originalAuthorEl.textContent.trim() : 'A user';
                            
                            let originalContentSnippet = 'a post';
                            const originalContentDisplay = originalPostElement.querySelector('.post-content-display:not(.post-share-comment-display)');
                            
                            if (originalContentDisplay && originalContentDisplay.textContent.trim()) {
                                originalContentSnippet = `"${originalContentDisplay.textContent.trim().substring(0, 50)}..."`;
                            } else if (originalPostElement.querySelector('.ai-code-block')) {
                                originalContentSnippet = 'AI generated code';
                            } else if (originalPostElement.querySelector('.post-media-display img, .cloudflare-stream-player-container')) {
                                originalContentSnippet = 'media content';
                            }

                            sharePreviewContainer.innerHTML = `
                                <div class="mt-2 mb-3 p-2 border border-gray-200 dark:border-dark-500 rounded-md bg-gray-50 dark:bg-dark-600">
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        You are sharing ${this._sanitizeHTML(originalContentSnippet)} by 
                                        <strong class="dark:text-gray-200">${this._sanitizeHTML(originalAuthorName)}</strong>.
                                    </p>
                                </div>`;
                        } else {
                            sharePreviewContainer.innerHTML = `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Preparing to share post ID: ${this._sanitizeHTML(originalPostIdStr)}...</p>`;
                            console.warn(`[SmartFed openPostModal] Could not find original post element on page for ID: ${originalPostIdStr} to create preview.`);
                        }
                    } else {
                        console.warn("[SmartFed openPostModal] Share preview container (#sharePreviewContainer) not found in modal.");
                    }

                } else {
                    console.log('[SmartFed openPostModal] REGULAR POST MODE or invalid/missing sharing context. Context was:', JSON.stringify(context));
                    this.postModalTextarea.placeholder = "What's on your mind? Or ask Sai to write for you...";
                    if (this.postModalEl.dataset.sharingOriginalPostId) {
                        delete this.postModalEl.dataset.sharingOriginalPostId;
                        console.log('[SmartFed openPostModal] Cleared modal dataset.sharingOriginalPostId.');
                    }
                    if (sharePreviewContainer) {
                        sharePreviewContainer.innerHTML = '';
                    }
                }
                this.postModalTextarea.focus();
            } else {
                console.error("[SmartFed openPostModal] Post modal textarea (this.postModalTextarea) not found!");
            }

            const isAIGenerating = window.globalPostFeedManager && window.globalPostFeedManager.isAIGenerating;
            this.updateModalUIAfterAIStateChange(!!isAIGenerating);
        }

        closePostModal() {
            // ... (this method remains unchanged)
            if (window.globalPostFeedManager && window.globalPostFeedManager.isAIGenerating) {
                window.globalPostFeedManager.stopAIGeneration(true);
            } else if (window.globalPostFeedManager && typeof window.globalPostFeedManager.cleanupTemporaryAIPost === 'function') {
                window.globalPostFeedManager.cleanupTemporaryAIPost(true);
            }
            if (this.postModalEl) {
                this.postModalEl.classList.add('hidden');
                // Clear inline z-index we set previously so CSS can control it again
                try { this.postModalEl.style.zIndex = ''; } catch (e) { /* ignore */ }
                if (this.postModalEl.dataset.sharingOriginalPostId) {
                    delete this.postModalEl.dataset.sharingOriginalPostId;
                }
                const sharePreviewContainer = this.postModalEl.querySelector('#sharePreviewContainer');
                if(sharePreviewContainer) sharePreviewContainer.innerHTML = '';
            }
            document.body.style.overflow = '';
            this.updateModalUIAfterAIStateChange(false);
            if (window.globalPostFeedManager && window.globalPostFeedManager.currentShareContext) {
                delete window.globalPostFeedManager.currentShareContext;
            }
            sessionStorage.removeItem('pfm_sharing_originalPostId');
        }

        _initDarkMode() {
            // ... (this method remains unchanged)
            this._applyDarkModePreference();
            if (this.darkModeToggleBtn) this.darkModeToggleBtn.addEventListener('click', () => this._toggleDarkMode());
        }

        _applyMonacoTheme(theme) {
            // ... (this method remains unchanged)
            if (typeof monaco !== 'undefined' && monaco.editor) {
                monaco.editor.setTheme(theme);
                monaco.editor.getEditors().forEach(editor => {
                    if (editor && typeof editor.updateOptions === 'function') {
                        editor.updateOptions({ theme: theme });
                    }
                });
            }
        }

        _applyDarkModePreference() {
            // ... (this method remains unchanged)
            const isDark = localStorage.getItem('darkMode') === 'true' ||
                           (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            if (this.lightModeTextEl) this.lightModeTextEl.classList.toggle('hidden', isDark);
            if (this.darkModeTextEl) this.darkModeTextEl.classList.toggle('hidden', !isDark);
            this._applyMonacoTheme(isDark ? 'vs-dark' : 'vs');
        }

        _toggleDarkMode() {
            // ... (this method remains unchanged)
            const isDarkNow = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDarkNow);
            if (this.lightModeTextEl) this.lightModeTextEl.classList.toggle('hidden', isDarkNow);
            if (this.darkModeTextEl) this.darkModeTextEl.classList.toggle('hidden', !isDarkNow);
            this._applyMonacoTheme(isDarkNow ? 'vs-dark' : 'vs');
        }

        _initStickySidebars() {
            // ... (this method remains unchanged)
            if(this.headerEl) {
                this._handleSidebarScroll();
            } else {
                setTimeout(() => {
                    if (!this.headerEl) this.headerEl = document.querySelector('header.fixed'); 
                    this._handleSidebarScroll();
                }, 200);
            }
        }

        _handleSidebarScroll() {
            // ... (this method remains unchanged)
            if (!this.sidebarContainers || this.sidebarContainers.length === 0) return;
            const isMobileView = window.innerWidth < 1024;
            this.sidebarContainers.forEach(s => {
                if (isMobileView) {
                    s.style.position = 'static'; s.style.top = 'auto'; s.style.height = 'auto';
                } else {
                    const headerHeight = (this.headerEl && this.headerEl.offsetHeight > 0) ? this.headerEl.offsetHeight : 64;
                    s.style.position = 'sticky';
                    s.style.top = `${headerHeight}px`;
                    s.style.height = `calc(100vh - ${headerHeight}px)`;
                }
            });
        }

        _initReactionTooltipsGlobal() {
            // ... (this method remains unchanged)
            document.querySelectorAll('.reaction-btn').forEach(b => this._initReactionTooltipEvents(b));
        }

        _initReactionTooltipsForElement(parentElement) {
            // ... (this method remains unchanged)
            parentElement.querySelectorAll('.reaction-btn').forEach(b => this._initReactionTooltipEvents(b));
        }

        _initReactionTooltipEvents(button) {
            // ... (this method remains unchanged)
            let timer;
            const tip = button.querySelector('.reaction-tooltip');
            if (!tip) return;
            button.addEventListener('mouseenter', () => { tip.classList.remove('hidden'); tip.classList.add('flex'); });
            button.addEventListener('mouseleave', () => { tip.classList.add('hidden'); tip.classList.remove('flex'); });
            button.addEventListener('touchstart', (e) => { e.preventDefault(); timer = setTimeout(() => { tip.classList.remove('hidden'); tip.classList.add('flex'); }, 300); });
            button.addEventListener('touchend', () => clearTimeout(timer));
            button.addEventListener('touchmove', () => clearTimeout(timer));
            tip.addEventListener('click', (e) => {
                if (e.target.tagName === 'SPAN' && e.target.closest('.reaction-tooltip')) {
                    tip.classList.add('hidden'); tip.classList.remove('flex');
                    const iconEl = button.querySelector('i');
                    const textSpan = button.querySelector('span.action-text');
                    if (iconEl) iconEl.className = 'mr-2';
                    const reaction = e.target.textContent.trim();
                    let reactionText = 'Like'; let reactionClass = 'fas fa-thumbs-up'; let colorClass = 'text-blue-500';
                    switch (reaction) {
                        case '❤️': reactionText = 'Love'; reactionClass = 'fas fa-heart'; colorClass = 'text-red-500'; break;
                        case '😆': reactionText = 'Haha'; reactionClass = 'fas fa-laugh-squint'; colorClass = 'text-yellow-500'; break;
                        case '😮': reactionText = 'Wow'; reactionClass = 'fas fa-surprise'; colorClass = 'text-yellow-500'; break;
                        case '😢': reactionText = 'Sad'; reactionClass = 'fas fa-sad-tear'; colorClass = 'text-yellow-500'; break;
                        case '😡': reactionText = 'Angry'; reactionClass = 'fas fa-angry'; colorClass = 'text-red-700'; break;
                    }
                    if (iconEl) iconEl.className = `mr-2 ${reactionClass} ${colorClass}`;
                    if (textSpan) textSpan.textContent = reactionText;
                    button.classList.remove('text-blue-500', 'text-red-500', 'text-yellow-500', 'text-red-700', 'text-gray-500', 'dark:text-gray-400');
                    button.classList.add(colorClass);
                }
            });
        }
        
        _formatTimeAgo(dateString) {
            // ... (this method remains unchanged)
            if (!dateString) return 'Some time ago';
            const dateStrFormat = dateString.includes('T') ? dateString : dateString.replace(' ', 'T') + (dateString.endsWith('Z') ? '' : 'Z');
            const date = new Date(dateStrFormat);
            if (isNaN(date.getTime())) { console.warn("Invalid date for _formatTimeAgo:", dateString); return 'Error in date';}
            const now = new Date();
            const seconds = Math.round((now.getTime() - date.getTime()) / 1000);
            const minutes = Math.round(seconds / 60);
            const hours = Math.round(minutes / 60);
            const days = Math.round(hours / 24);

            if (seconds < 5) return `Just now`;
            if (seconds < 60) return `${seconds}s ago`;
            if (minutes < 60) return `${minutes}m ago`;
            if (hours < 24) return `${hours}h ago`;
            if (days === 1) return `1d ago`;
            if (days < 7) return `${days}d ago`;
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        }

        _sanitizeHTML(str, allowBreaks = false) {
            // ... (this method remains unchanged)
            if (typeof str !== 'string') str = String(str || '');
            const temp = document.createElement('div');
            temp.textContent = str;
            let sanitized = temp.innerHTML;
            if (allowBreaks) {
                sanitized = sanitized.replace(/\n/g, '<br>');
            }
            return sanitized;
        }

    } // End of SmartFedApp class

    document.addEventListener('DOMContentLoaded', () => {
        window.SmartFed = new SmartFedApp();

        // The rest of this file (Premium Modal, etc.) remains unchanged
        // ...
    });

})(window, document);