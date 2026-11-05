// header-manager-refactored.js
(function(window, document, undefined) {
    'use strict';

    // --- UTILITIES (unchanged) ---
    function _sanitizeText(str) {
        if (str === null || typeof str === 'undefined') str = '';
        if (typeof str !== 'string') str = String(str);
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    function _generateClientFallbackSVG(name, size = 32) {
        if (typeof name !== 'string' || name.trim() === '') name = '?';
        let initial = '?';
        const trimmedName = name.trim();
        if (trimmedName) {
            const parts = trimmedName.split(/\s+/);
            initial = parts[0].charAt(0).toUpperCase();
            if (parts.length > 1 && parts[parts.length - 1]) {
                const lastInitialChar = parts[parts.length - 1].charAt(0).toUpperCase();
                if (initial !== lastInitialChar && /[A-Z\d]/i.test(lastInitialChar)) initial += lastInitialChar;
            }
            if (!/^[A-Z\d]{1,2}$/i.test(initial)) {
                initial = trimmedName.charAt(0).toUpperCase();
                if (!/^[A-Z\d]$/i.test(initial)) initial = '?';
            }
        }
        const colors = ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#34495e', '#7f8c8d'];
        const charCodeSum = Array.from(name).reduce((sum, char) => sum + char.charCodeAt(0), 0);
        const bgColor = colors[charCodeSum % colors.length];
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${bgColor}"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="${initial.length > 1 ? 40 : 50}" fill="white" font-weight="bold">${_sanitizeText(initial)}</text></svg>`;
        return "data:image/svg+xml;base64," + btoa(svg);
    }

    function _timeAgo(dateString) {
        if (!dateString) return 'Some time ago';
        const dateStrNormalized = dateString.includes('T') ? dateString : dateString.replace(' ', 'T');
        const date = new Date(dateStrNormalized.endsWith('Z') ? dateStrNormalized : dateStrNormalized + 'Z');
        if (isNaN(date.getTime())) {
             console.warn(`[HeaderManagerR] _timeAgo: Could not parse date: ${dateString}.`);
             return 'Invalid date';
        }
        const now = new Date();
        const seconds = Math.round((now.getTime() - date.getTime()) / 1000);
        if (seconds < 5) return `Just now`;
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.round(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.round(hours / 24);
        if (days === 1) return `1d ago`;
        if (days < 7) return `${days}d ago`;
        const options = { month: 'short', day: 'numeric' };
        if (date.getFullYear() !== now.getFullYear()) options.year = 'numeric';
        return date.toLocaleDateString(undefined, options);
    }
    // --- END UTILITIES ---

    const API_ENDPOINTS = {
        HEADER_DATA: '/post/header-data', // GET
        MODAL_POST_DATA: (postId) => `/post/${postId}`, // GET
        TOGGLE_POST_LIKE: '/post/like', // POST
        ADD_POST_COMMENT: '/post/comment', // POST
        POST_COMMENTS: (postId, page = 1) => `/post/${postId}/comments?page=${page}`, // GET
        DELETE_COMMENT: (commentId) => `/post/comments/${commentId}/delete`, // POST
        EDIT_COMMENT: (commentId) => `/post/comments/${commentId}/edit` // POST (unused in this script)
    };

    class HeaderManager {
        constructor() {
            // ... (constructor is unchanged, so it is omitted here for brevity)
            console.log("HeaderManagerRefactored: Initializing...");
            this.isContentModalOpen = false;
            this.currentPostAuthorIdInModal = null; // For comment deletion logic in modal

            // Initialize currentUserData from global or set defaults
            if (window.currentUserData) {
                this.currentUserId = (window.currentUserData.id !== null && typeof window.currentUserData.id !== 'undefined')
                                     ? String(window.currentUserData.id)
                                     : null;
                this.currentUserFullName = window.currentUserData.fullName || 'Guest';
                this.currentUserAvatar = window.currentUserData.profilePicture || _generateClientFallbackSVG(this.currentUserFullName, 32);
            } else {
                this.currentUserId = null;
                this.currentUserFullName = 'Guest';
                this.currentUserAvatar = _generateClientFallbackSVG('Guest', 32);
                console.warn("[HeaderManagerRefactored] window.currentUserData is not defined. Assuming guest.");
            }

            this._cacheDOMElements();

            if (!this._areCoreElementsPresent()) {
                console.log("HeaderManagerRefactored: Core header elements (user menu or content modal) not found. Halting initialization.");
                return;
            }

            // Initialize NotificationManager
            if (window.NotificationManager) {
                this.notificationManager = new window.NotificationManager({
                    currentUserData: { // Pass current snapshot of user data
                        id: this.currentUserId,
                        fullName: this.currentUserFullName,
                    },
                    onNotificationClickCallback: this.handleNotificationContentRequest.bind(this),
                    elements: { // Pass only notification-specific DOM elements
                        notificationBtnEl: document.getElementById('notificationBtn'),
                        notificationUnreadBadgeEl: document.getElementById('notificationUnreadBadge'),
                        notificationListContainerEl: document.getElementById('notificationListContainer'),
                        notificationEmptyStateEl: document.getElementById('notificationEmptyState'),
                        notificationLoadingStateEl: document.getElementById('notificationLoadingState'),
                        markAllNotificationsReadBtnEl: document.getElementById('markAllNotificationsReadBtn'),
                        notificationViewAllContainerEl: document.getElementById('notificationViewAllContainer')
                    }
                });
            } else {
                console.error("HeaderManagerRefactored: NotificationManager class not found. Notifications will not work.");
                this.notificationManager = null;
            }

            this._bindStaticEvents();
            this._bindDelegatedModalEvents();
            this._fetchHeaderData();
        }

        /**
         * REFACTORED: Centralized fetch utility for API calls.
         * Automatically handles CSRF tokens for POST requests and standardizes error handling.
         * @param {string} endpoint The API endpoint to call.
         * @param {object} options Standard fetch options (method, body, etc.).
         * @returns {Promise<any>} A promise that resolves with the JSON response.
         * @throws {Error} Throws an error for network issues or non-ok HTTP responses.
         */
        async _apiFetch(endpoint, options = {}) {
            const headers = {
                'Accept': 'application/json',
                ...options.headers,
            };

            const method = options.method ? options.method.toUpperCase() : 'GET';

            if (method === 'POST') {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                } else {
                    console.warn(`[HeaderManager] CSRF token meta tag not found for POST request to ${endpoint}`);
                }
            }
            
            // Auto-stringify body if it's a plain object, but not if it's FormData.
            if (options.body && !(options.body instanceof FormData)) {
                 headers['Content-Type'] = 'application/json';
                 options.body = JSON.stringify(options.body);
            }

            const response = await fetch(endpoint, {
                ...options,
                method: method,
                headers: headers,
            });

            if (!response.ok) {
                let errorMsg = `HTTP error ${response.status} for ${method} ${endpoint}`;
                try {
                    const errData = await response.json();
                    errorMsg = errData.message || errData.error || errorMsg;
                } catch (e) {
                    // Response was not JSON, stick with the original HTTP error.
                }
                throw new Error(errorMsg);
            }

            // Return parsed JSON, or handle empty responses.
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            }
            return null; // For 204 No Content or non-JSON responses
        }
        
        _cacheDOMElements() {
            // ... (method is unchanged, so it is omitted here for brevity)
            this.userMenuAvatarEl = document.getElementById('userMenuAvatar');
            this.userDropdownProfileLinkEl = document.getElementById('userDropdownProfileLink');
            this.contentModalEl = document.getElementById('notificationContentModal');
            this.contentModalTitleEl = document.getElementById('notificationModalTitle');
            this.contentModalBodyEl = document.getElementById('notificationModalBody');
            this.contentModalCloseBtnEl = document.getElementById('notificationModalCloseBtn');
            this.contentModalViewLinkEl = document.getElementById('notificationModalViewLink');
            this.contentModalSecondaryActionBtnEl = document.getElementById('notificationModalSecondaryActionBtn');
            this.deleteConfirmModalEl = document.getElementById('deleteConfirmationModal');
            this.deleteConfirmModalTitleEl = document.getElementById('deleteConfirmModalTitle');
            this.deleteConfirmModalMessageEl = document.getElementById('deleteConfirmModalMessage');
            this.deleteConfirmModalConfirmBtnEl = document.getElementById('deleteConfirmModalConfirmBtn');
            this.deleteConfirmModalCancelBtnEl = document.getElementById('deleteConfirmModalCancelBtn');
        }

        _areCoreElementsPresent() {
            // ... (method is unchanged)
            return this.userMenuAvatarEl || this.contentModalEl;
        }

        _bindStaticEvents() {
            // ... (method is unchanged)
            if (this.contentModalEl) {
                if (this.contentModalCloseBtnEl) {
                    this.contentModalCloseBtnEl.addEventListener('click', () => this._closeContentModal());
                }
                this.contentModalEl.addEventListener('click', (event) => {
                    if (event.target === this.contentModalEl) this._closeContentModal();
                });
            }
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (this.isContentModalOpen && 
                        (!this.deleteConfirmModalEl || this.deleteConfirmModalEl.classList.contains('hidden'))) {
                        this._closeContentModal();
                    }
                }
            });
        }

        _bindDelegatedModalEvents() {
            // ... (method is unchanged)
            if (!this.contentModalBodyEl) {
                console.warn("[HeaderManagerRefactored] contentModalBodyEl not found. Cannot bind delegated modal events.");
                return;
            }
             this.contentModalBodyEl.addEventListener('click', (event) => {
                const target = event.target;
                let eventHandled = false;
                const L_LOG_STUB = () => { eventHandled = true; }; // Placeholder for removed L_LOG

                const likeButton = target.closest('.modal-like-button');
                if (likeButton) { event.preventDefault(); L_LOG_STUB(); this._handleModalLikeClick(likeButton); }

                const commentActionButton = target.closest('.modal-comment-action-button');
                if (commentActionButton && !eventHandled) { event.preventDefault(); L_LOG_STUB(); this._toggleModalCommentInput(commentActionButton, true); }

                const viewCommentsButton = target.closest('.modal-view-comments-button');
                if (viewCommentsButton && !eventHandled) { event.preventDefault(); L_LOG_STUB(); this._handleModalViewComments(viewCommentsButton); }
                
                const commentStatsTrigger = target.closest('.modal-comment-stats-trigger');
                if (commentStatsTrigger && !eventHandled) { event.preventDefault(); L_LOG_STUB(); this._toggleModalCommentInput(commentStatsTrigger, true); }

                const commentSubmitButton = target.closest('.modal-comment-submit-button');
                if (commentSubmitButton && !eventHandled) { event.preventDefault(); L_LOG_STUB(); this._handleModalCommentSubmit(commentSubmitButton); }

                const shareButton = target.closest('.modal-share-button');
                if (shareButton && !eventHandled) { event.preventDefault(); L_LOG_STUB(); this._handleModalShareClick(shareButton); }

                const deleteCommentBtn = target.closest('.modal-delete-comment-button');
                if (deleteCommentBtn && !eventHandled) { event.preventDefault(); L_LOG_STUB(); this._handleModalDeleteCommentClick(deleteCommentBtn); }
            });
            this.contentModalBodyEl.addEventListener('keypress', (event) => {
                const target = event.target;
                if (target.matches('.modal-comment-input') && event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    const commentSection = target.closest('.modal-comment-input-section');
                    if (commentSection) {
                        const submitButton = commentSection.querySelector('.modal-comment-submit-button');
                        if (submitButton) this._handleModalCommentSubmit(submitButton);
                    }
                }
            });
        }

        async _fetchHeaderData() {
            if (!this._areCoreElementsPresent() && !this.notificationManager) return;

            try {
                // REFACTORED: Use the centralized fetch utility
                const data = await this._apiFetch(API_ENDPOINTS.HEADER_DATA);

                if (data.success) {
                    if (data.isLoggedIn && data.user) {
                        this.currentUserId = String(data.user.id || this.currentUserId);
                        this.currentUserFullName = data.user.fullName || this.currentUserFullName;
                        this.currentUserAvatar = data.user.avatarUrl || data.user.avatarFallback || this.currentUserAvatar || _generateClientFallbackSVG(this.currentUserFullName, 32);
                        
                        if (this.userMenuAvatarEl) this._updateUserMenu({ id: this.currentUserId, fullName: this.currentUserFullName, avatarUrl: this.currentUserAvatar });
                        
                        if (this.notificationManager) {
                            this.notificationManager.updateUserData({ id: this.currentUserId, fullName: this.currentUserFullName });
                            this.notificationManager.updateNotificationsDisplay(data.notifications, data.unreadNotificationCount, data.hasMoreNotifications);
                        }
                    } else {
                        this._handleLoggedOutState();
                    }
                } else {
                    throw new Error(data.message || "Failed to process header data.");
                }
            } catch (error) {
                console.error("HeaderManagerRefactored: Error fetching header data:", error);
                this._handleFetchErrorState();
            }
        }

        _updateUserMenu(userData) {
            // ... (method is unchanged)
            if (!this.userMenuAvatarEl || !userData) return;
            const avatarSrc = userData.avatarUrl || userData.avatarFallback || _generateClientFallbackSVG(userData.fullName || 'User', 32);
            this.userMenuAvatarEl.src = avatarSrc;
            this.userMenuAvatarEl.alt = (_sanitizeText(userData.fullName) || 'User') + " Profile";
            if (this.userDropdownProfileLinkEl) {
                this.userDropdownProfileLinkEl.href = userData.id ? `/profile/${userData.id}` : '/login';
            }
        }
        
        handleNotificationContentRequest(notification, clickedItemElement) {
            // ... (method is unchanged)
            console.log("[HeaderManagerRefactored] Received request to display content for notification:", notification);
            this._openContentModal(notification);
        }
        
        _openContentModal(notificationContext) {
            // ... (method is unchanged)
            if (!this.contentModalEl || !this.contentModalTitleEl || !this.contentModalBodyEl || !this.contentModalViewLinkEl) return;
            
            this.contentModalTitleEl.textContent = `Notification from ${_sanitizeText(notificationContext.actor_name_parsed || 'System')}`;
            this.contentModalBodyEl.innerHTML = `
                <div id="modalPostContentArea" class="mb-4">
                    <p class="text-center text-gray-500 dark:text-gray-400 py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading related content...</p>
                </div>
                <div class="notification-message-details p-3 bg-gray-100 dark:bg-dark-700 rounded-md">
                    <p class="text-md dark:text-gray-200 text-gray-800">${_sanitizeText(notificationContext.message)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Received: ${_timeAgo(notificationContext.created_at)}</p>
                </div>`;

            const viewOriginalLink = notificationContext.context?.post_id 
                ? `/post/${notificationContext.context.post_id}${notificationContext.context.comment_id ? '#comment-' + notificationContext.context.comment_id : ''}`
                : (notificationContext.context?.link || '#');

            this.contentModalViewLinkEl.href = (viewOriginalLink && viewOriginalLink !== '#') ? viewOriginalLink : '#';
            this.contentModalViewLinkEl.classList.toggle('hidden', this.contentModalViewLinkEl.href === '#');
            this.contentModalViewLinkEl.textContent = 'View Original Context';
            this.contentModalViewLinkEl.target = '_blank';


            if (this.contentModalSecondaryActionBtnEl) {
                this.contentModalSecondaryActionBtnEl.classList.add('hidden'); // Reset
                this.contentModalSecondaryActionBtnEl.href = '#';
                this.contentModalSecondaryActionBtnEl.textContent = 'View Post Page';
            }
            
            this.contentModalEl.classList.remove('hidden');
            this.isContentModalOpen = true;
            document.body.classList.add('overflow-hidden');
            this.contentModalCloseBtnEl?.focus();
            
            this._loadPostIntoModal(notificationContext.context?.post_id);
        }

        _closeContentModal() {
            // ... (method is unchanged)
            if (!this.contentModalEl || !this.isContentModalOpen) return;
            this.contentModalEl.classList.add('hidden');
            this.isContentModalOpen = false;
            document.body.classList.remove('overflow-hidden');
            const postContentArea = this.contentModalBodyEl.querySelector('#modalPostContentArea');
            if (postContentArea) postContentArea.innerHTML = '';
            this.currentPostAuthorIdInModal = null;
        }

        _renderPostForModal(post) {
            // ... (method is unchanged, it's a pure renderer)
             if (!post) return '<p class="text-red-500 p-4 dark:text-red-400">Error: Post data is missing.</p>';

            const sanitizedPostAuthorFullName = _sanitizeText(post.full_name || post.username || 'User');
            const postAuthorProfileLink = `/profile/${post.user_id}`;
            const postLink = `/post/${post.id}`;
            const postAuthorAvatar = _sanitizeText(post.user_avatar || _generateClientFallbackSVG(sanitizedPostAuthorFullName, 40));

            let visibilityIcon = 'fa-globe-americas';
            let visibilityTitle = 'Public';
            if (post.visibility === 'friends') { visibilityIcon = 'fa-user-friends'; visibilityTitle = 'Friends'; }
            else if (post.visibility === 'private') { visibilityIcon = 'fa-lock'; visibilityTitle = 'Only Me'; }

            const postOptionsHTML = `
                <div class="relative post-options-dropdown-container">
                    <a href="${postLink}" target="_blank" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-full p-2" aria-label="Post options" title="View post and options">
                        <i class="fas fa-ellipsis-h"></i>
                    </a>
                </div>`;

            let mainContentHTML = '';
            if (post.post_type === 'ai_code' && post.code_language) {
                mainContentHTML = `<div class="post-content-display mb-3 dark:text-gray-200 whitespace-pre-wrap"><p class="text-xs text-gray-500 dark:text-gray-400 mb-1">AI Code (${_sanitizeText(post.code_language)})</p><pre class="bg-gray-100 dark:bg-dark-800 text-gray-800 dark:text-gray-200 p-3 rounded-md overflow-x-auto text-sm"><code class="language-${_sanitizeText(post.code_language)}">${_sanitizeText(post.content)}</code></pre>${post.original_prompt ? `<p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">Prompt: ${_sanitizeText(post.original_prompt)}</p>` : ''}</div>`;
            } else if (post.content) {
                mainContentHTML = `<div class="post-content-display mb-3 text-gray-800 dark:text-gray-200 whitespace-pre-wrap">${_sanitizeText(post.content)}</div>`;
            } else {
                 mainContentHTML = `<div class="post-content-display mb-3 text-gray-800 dark:text-gray-200"><p><em>No content available.</em></p></div>`;
            }

            let mediaHTML = '';
            if (post.is_live_stream && post.stream_playback_uid) {
                mediaHTML = `<div class="my-3 bg-black rounded-lg overflow-hidden aspect-video flex items-center justify-center"><a href="${postLink}" target="_blank" class="block w-full h-full" title="View live stream">${post.image ? `<img src="${_sanitizeText(post.image)}" alt="Live stream thumbnail" class="w-full h-full object-contain">` : `<div class="text-white p-4">View Live Stream</div>`}</a></div>`;
            } else if (post.image && post.post_type !== 'ai_code') {
                mediaHTML = `<div class="my-3"><img src="${_sanitizeText(post.image)}" alt="Post image" class="max-w-full h-auto rounded-lg mx-auto"></div>`;
            }

            const likeIconClass = post.is_liked_by_current_user ? 'fas fa-thumbs-up text-blue-600 dark:text-blue-400' : 'far fa-thumbs-up';
            const likeButtonText = post.is_liked_by_current_user ? 'Unlike' : 'Like';
            const activeLikeClasses = post.is_liked_by_current_user ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-400';

            const likeStatsIcon = `<i class="fas fa-thumbs-up text-blue-600 dark:text-blue-400 mr-1"></i>`;
            const likeCount = Number(post.like_count) || 0;
            const commentCount = Number(post.comment_count) || 0;

            const likeStatsHTML = `<div>${likeStatsIcon}<span class="like-count-display" data-post-id="${post.id}">${likeCount}</span> Like${likeCount !== 1 ? 's' : ''}</div>`;
            const commentStatsHTML = `<div><span class="comment-count-display-text hover:underline cursor-pointer modal-comment-stats-trigger" data-post-id="${post.id}">${commentCount} comment${commentCount !== 1 ? 's' : ''}</span></div>`;

            const likeButtonModal = `<button class="modal-like-button flex-1 flex items-center justify-center py-2 px-3 ${activeLikeClasses} hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${post.id}" data-liked="${post.is_liked_by_current_user ? 'true' : 'false'}"><i class="${likeIconClass} mr-2"></i> <span class="modal-like-button-text">${likeButtonText}</span></button>`;
            const commentButtonModal = `<button class="modal-comment-action-button flex-1 flex items-center justify-center py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${post.id}"><i class="far fa-comment-alt mr-2"></i> Comment</button>`;
            const shareButtonModal = `<button class="modal-share-button flex-1 flex items-center justify-center py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${post.id}" data-post-link="${postLink}"><i class="fas fa-share mr-2"></i> Share</button>`;

            const viewCommentsButtonText = `View ${commentCount} comment${commentCount !== 1 ? 's' : ''}`;
            const viewCommentsTriggerHTML = commentCount > 0 ? `
                <div class="view-comments-trigger-modal p-3 pt-1">
                    <button class="modal-view-comments-button text-sm text-gray-600 dark:text-gray-400 hover:underline" data-post-id="${post.id}" data-original-text="${viewCommentsButtonText}">
                        ${viewCommentsButtonText}
                    </button>
                </div>
            ` : '<div class="view-comments-trigger-modal p-3 pt-1 text-sm text-gray-500 dark:text-gray-400">No comments yet.</div>';

            const commentInputUserAvatar = _sanitizeText(this.currentUserAvatar || _generateClientFallbackSVG(this.currentUserFullName || 'You', 32));

            return `
                <div class="post-item-modal-view bg-white dark:bg-dark-700 rounded-lg shadow mb-4" data-post-id="${post.id}">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <img src="${postAuthorAvatar}" alt="${sanitizedPostAuthorFullName}" class="w-10 h-10 rounded-full mr-3 object-cover flex-shrink-0">
                                <div>
                                    <a href="${postAuthorProfileLink}" target="_blank" class="font-semibold text-gray-900 dark:text-white hover:underline">${sanitizedPostAuthorFullName}</a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="post-timeago" data-timestamp="${_sanitizeText(post.created_at)}">${_timeAgo(post.created_at)}</span> ·
                                        <i class="fas ${visibilityIcon} ml-1" title="${visibilityTitle}"></i>
                                    </p>
                                </div>
                            </div>
                            ${postOptionsHTML}
                        </div>
                        
                        ${mainContentHTML}
                        ${mediaHTML}
                        
                        <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 my-2 pt-2 border-t border-gray-200 dark:border-dark-600">
                            ${likeStatsHTML}
                            ${commentStatsHTML}
                        </div>
                    </div>
                    
                    <div class="post-actions flex justify-around border-t border-gray-200 dark:border-dark-600">
                        ${likeButtonModal}
                        ${commentButtonModal}
                        ${shareButtonModal}
                    </div>

                    ${viewCommentsTriggerHTML}
                    
                    <div class="comments-list-area-modal p-3 pt-0 space-y-2 hidden" data-post-id="${post.id}" data-current-page="1">
                    </div>
                    
                    ${this.currentUserId ? `
                    <div class="modal-comment-input-section p-3 border-t border-gray-200 dark:border-dark-600" data-post-id="${post.id}">
                        <div class="flex items-start space-x-2">
                            <img src="${commentInputUserAvatar}" alt="${_sanitizeText(this.currentUserFullName || 'Your avatar')}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            <textarea class="modal-comment-input flex-1 p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none bg-white dark:bg-dark-600 text-gray-800 dark:text-white" rows="1" placeholder="Write a comment..."></textarea>
                            <button class="modal-comment-submit-button bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-3 rounded-lg text-sm ml-2" data-post-id="${post.id}">Post</button>
                        </div>
                        <div class="modal-comment-list mt-2 space-y-2 text-sm" data-post-id="${post.id}">
                        </div>
                    </div>` : `<div class="p-3 text-sm text-gray-500 dark:text-gray-400">You need to be logged in to comment.</div>`}
                    <div class="share-feedback p-2 text-center text-sm text-green-500 hidden" data-post-id="${post.id}">Link copied to clipboard!</div>
                    <div id="generalModalFeedbackArea" class="p-2 text-sm text-center"></div>
                </div>
            `;
        }

        async _loadPostIntoModal(postId) {
            const postContentArea = this.contentModalBodyEl.querySelector('#modalPostContentArea');
            if (!postContentArea) { console.warn("[HeaderManagerRefactored] Modal post content area not found."); return; }

            this.currentPostAuthorIdInModal = null;

            if (!postId) {
                postContentArea.innerHTML = `<p class="text-gray-500 dark:text-gray-400 p-4 text-sm">This notification does not directly reference specific content.</p>`;
                if (this.contentModalSecondaryActionBtnEl) this.contentModalSecondaryActionBtnEl.classList.add('hidden');
                return;
            }

            postContentArea.innerHTML = `<p class="text-center text-gray-500 dark:text-gray-400 py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading related content...</p>`;
            if (this.contentModalSecondaryActionBtnEl) this.contentModalSecondaryActionBtnEl.classList.add('hidden');

            try {
                // REFACTORED: Use the centralized fetch utility
                const endpoint = API_ENDPOINTS.MODAL_POST_DATA(postId);
                const result = await this._apiFetch(endpoint);

                if (result.success && result.post) {
                    this.currentPostAuthorIdInModal = result.post.user_id ? String(result.post.user_id) : null;
                    postContentArea.innerHTML = this._renderPostForModal(result.post);
                    if (window.timeago?.render) {
                        window.timeago.render(postContentArea.querySelectorAll('.post-timeago, .comment-timeago'));
                    }
                    if (this.contentModalSecondaryActionBtnEl) {
                        this.contentModalSecondaryActionBtnEl.href = `/post/${result.post.id}`;
                        this.contentModalSecondaryActionBtnEl.classList.remove('hidden');
                        this.contentModalSecondaryActionBtnEl.target = '_blank';
                    }
                } else {
                    throw new Error(result.message || "Could not load post content from API response.");
                }
            } catch (error) {
                console.error("[HeaderManagerRefactored] Error fetching post for modal:", error);
                postContentArea.innerHTML = `<p class="text-red-500 p-4 dark:text-red-400">Error: ${_sanitizeText(error.message || 'Could not load related content.')}</p>`;
                if (this.contentModalSecondaryActionBtnEl) this.contentModalSecondaryActionBtnEl.classList.add('hidden');
            }
        }
        
        async _handleModalLikeClick(buttonEl) {
            if (buttonEl.disabled) return;
            const postId = buttonEl.dataset.postId;
            buttonEl.disabled = true;
            const textSpan = buttonEl.querySelector('.modal-like-button-text');
            const originalText = textSpan.textContent;
            textSpan.textContent = '...';

            try {
                // REFACTORED: Use the centralized fetch utility for a POST request
                const result = await this._apiFetch(API_ENDPOINTS.TOGGLE_POST_LIKE, {
                    method: 'POST',
                    body: { post_id: postId } // The helper will stringify this JSON
                });

                if (!result.success) throw new Error(result.message || 'Failed to toggle like');
                
                const isCurrentlyLiked = result.liked;
                buttonEl.dataset.liked = isCurrentlyLiked.toString();
                textSpan.textContent = isCurrentlyLiked ? 'Unlike' : 'Like';
                const iconEl = buttonEl.querySelector('i');
                iconEl.className = `mr-2 ${isCurrentlyLiked ? 'fas fa-thumbs-up text-blue-600 dark:text-blue-400' : 'far fa-thumbs-up'}`;
                buttonEl.classList.toggle('text-blue-600', isCurrentlyLiked);
                buttonEl.classList.toggle('dark:text-blue-400', isCurrentlyLiked);
                buttonEl.classList.toggle('font-semibold', isCurrentlyLiked);

                const postModalView = buttonEl.closest('.post-item-modal-view');
                if (postModalView) {
                    const likeCountDisplay = postModalView.querySelector(`.like-count-display[data-post-id="${postId}"]`);
                    const likeTextDisplayContainer = postModalView.querySelector(`.flex.justify-between.items-center .text-sm div:first-child`); 

                    if (likeCountDisplay) likeCountDisplay.textContent = result.like_count;
                    if (likeTextDisplayContainer && likeCountDisplay) {
                        const newLikeCount = Number(result.like_count) || 0;
                        likeTextDisplayContainer.innerHTML = `<i class="fas fa-thumbs-up text-blue-600 dark:text-blue-400 mr-1"></i><span class="like-count-display" data-post-id="${postId}">${newLikeCount}</span> Like${newLikeCount !== 1 ? 's' : ''}`;
                    }
                }
            } catch (error) {
                console.error("Error liking post in modal:", error);
                textSpan.textContent = originalText;
            } finally {
                buttonEl.disabled = false;
            }
        }

        _toggleModalCommentInput(triggerEl, focusInput = true) {
            // ... (method is unchanged)
            const postId = triggerEl.dataset.postId;
            const postModalView = triggerEl.closest('.post-item-modal-view');
            if (!postModalView) return;
            const commentSection = postModalView.querySelector(`.modal-comment-input-section[data-post-id="${postId}"]`);
            if (commentSection) {
                const isHidden = commentSection.classList.contains('hidden');
                commentSection.classList.toggle('hidden');
                if (isHidden && focusInput) {
                    commentSection.querySelector('.modal-comment-input')?.focus();
                }
            }
        }

        async _handleModalCommentSubmit(submitButtonElement) {
            if (submitButtonElement.disabled) return;
            const postId = submitButtonElement.dataset.postId;
            const commentInputSection = submitButtonElement.closest('.modal-comment-input-section');
            const postModalViewElement = submitButtonElement.closest('.post-item-modal-view');
            if (!commentInputSection || !postModalViewElement || !postId) return;

            const commentInputElement = commentInputSection.querySelector('.modal-comment-input');
            const content = commentInputElement.value.trim();
            if (!content) { 
                commentInputElement.focus(); 
                commentInputElement.classList.add('border-red-500');
                setTimeout(() => commentInputElement.classList.remove('border-red-500'), 2000);
                return; 
            }

            const originalButtonText = submitButtonElement.textContent;
            commentInputElement.disabled = true;
            submitButtonElement.disabled = true;
            submitButtonElement.textContent = 'Posting...';

            try {
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', content);

                // REFACTORED: Use the centralized fetch utility with FormData
                const result = await this._apiFetch(API_ENDPOINTS.ADD_POST_COMMENT, {
                    method: 'POST',
                    body: formData
                });

                if (!result.success) throw new Error(result.error || result.message || 'Failed to post comment.');
                
                commentInputElement.value = '';
                if (result.comment) { 
                    const commentListEl = commentInputSection.querySelector('.modal-comment-list');
                    if (commentListEl) {
                        const newCommentData = {
                            id: result.comment.id,
                            user_full_name: result.comment.user_full_name || this.currentUserFullName,
                            user_avatar: result.comment.user_avatar || this.currentUserAvatar,
                            user_avatar_fallback: result.comment.user_avatar_fallback, 
                            user_id: String(result.comment.user_id || this.currentUserId), 
                            content: result.comment.content,
                            created_at: result.comment.created_at || new Date().toISOString(),
                            is_edited: result.comment.is_edited || false,
                            updated_at: result.comment.updated_at
                        };
                        const newCommentEl = this._createCommentItemElementForModal(newCommentData);
                        commentListEl.prepend(newCommentEl);
                        if (window.timeago?.render) window.timeago.render(newCommentEl.querySelectorAll('.comment-timeago'));
                    }
                }
                if (typeof result.comment_count !== 'undefined') {
                    // ... (UI update logic for comment count is unchanged)
                    const newCommentCount = Number(result.comment_count) || 0;
                    const commentCountSpanText = postModalViewElement.querySelector('.comment-count-display-text');
                    if (commentCountSpanText) {
                        commentCountSpanText.textContent = `${newCommentCount} comment${newCommentCount !== 1 ? 's' : ''}`;
                    }
                    const viewCommentsTriggerDiv = postModalViewElement.querySelector('.view-comments-trigger-modal');
                    if (viewCommentsTriggerDiv) {
                        const viewCommentsButtonText = `View ${newCommentCount} comment${newCommentCount !== 1 ? 's' : ''}`;
                        let viewCommentsButton = viewCommentsTriggerDiv.querySelector('.modal-view-comments-button');
                        if (!viewCommentsButton && newCommentCount > 0) { 
                             viewCommentsTriggerDiv.innerHTML = `
                                <button class="modal-view-comments-button text-sm text-gray-600 dark:text-gray-400 hover:underline" data-post-id="${postId}" data-original-text="${viewCommentsButtonText}">
                                    ${viewCommentsButtonText}
                                </button>`;
                        } else if (viewCommentsButton) {
                            viewCommentsButton.dataset.originalText = viewCommentsButtonText;
                            const commentsListArea = postModalViewElement.querySelector('.comments-list-area-modal');
                            if (commentsListArea && (commentsListArea.classList.contains('hidden') || commentsListArea.children.length === 0)) {
                                viewCommentsButton.textContent = viewCommentsButtonText;
                            }
                        } else if (newCommentCount === 0) {
                            viewCommentsTriggerDiv.innerHTML = '<div class="p-3 pt-1 text-sm text-gray-500 dark:text-gray-400">No comments yet.</div>';
                        }
                    }
                }
            } catch (error) {
                console.error('[HeaderManagerRefactored] Modal comment submission error:', error);
                const errorFeedbackArea = postModalViewElement.querySelector('#generalModalFeedbackArea');
                if (errorFeedbackArea) {
                    errorFeedbackArea.textContent = `Error posting comment: ${_sanitizeText(error.message)}`;
                    errorFeedbackArea.className = 'p-2 text-sm text-red-600 dark:text-red-400 text-center';
                    setTimeout(() => { errorFeedbackArea.textContent = ''; errorFeedbackArea.className = 'p-2 text-sm text-center'; }, 3000);
                }
            } finally {
                commentInputElement.disabled = false;
                submitButtonElement.disabled = false;
                submitButtonElement.textContent = originalButtonText;
            }
        }
        
        _handleModalShareClick(buttonEl) {
            // ... (method is unchanged as it's a client-side action)
            const postLink = buttonEl.dataset.postLink;
            const postId = buttonEl.dataset.postId;
            const fullPostUrl = window.location.origin + _sanitizeText(postLink);
            const feedbackEl = buttonEl.closest('.post-item-modal-view')?.querySelector(`.share-feedback[data-post-id="${postId}"]`);
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(fullPostUrl)
                    .then(() => this._showShareFeedback(feedbackEl, 'Link copied to clipboard!', true))
                    .catch(err => {
                        console.error('Failed to copy link:', err);
                        this._showShareFeedback(feedbackEl, 'Could not copy link.', false);
                    });
            } else {
                this._showShareFeedback(feedbackEl, 'Copying not supported. Link: ' + fullPostUrl, false);
            }
        }

        _showShareFeedback(element, message, success = true) {
            // ... (method is unchanged)
            if(!element) { 
                console.log(message); 
                return; 
            }
            element.textContent = message;
            element.classList.remove('hidden');
            element.classList.toggle('text-green-500', success);
            element.classList.toggle('text-red-500', !success);
            setTimeout(() => element.classList.add('hidden'), 2500);
        }
        
        async _handleModalViewComments(buttonElement) {
            // ... (method is unchanged)
            const postId = buttonElement.dataset.postId;
            const postModalViewElement = buttonElement.closest('.post-item-modal-view');
            if (!postModalViewElement || !postId) return;
            let commentsListArea = postModalViewElement.querySelector('.comments-list-area-modal');
            if (!commentsListArea) return;

            const isLoading = buttonElement.classList.contains('loading-comments');
            if (isLoading) return;

            if (commentsListArea.classList.contains('comments-loaded') && !commentsListArea.classList.contains('hidden')) {
                // Hide comments
                commentsListArea.classList.add('hidden');
                buttonElement.textContent = buttonElement.dataset.originalText || `View comments`;
                const loadMoreBtn = commentsListArea.querySelector('.modal-load-more-comments-button');
                if (loadMoreBtn) loadMoreBtn.classList.add('hidden'); // Hide load more button as well
            } else {
                // Show/Load comments
                if (!buttonElement.dataset.originalText) buttonElement.dataset.originalText = buttonElement.textContent;
                buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Loading...';
                buttonElement.classList.add('loading-comments');
                buttonElement.disabled = true;
                
                if (commentsListArea.classList.contains('comments-loaded') && commentsListArea.children.length > 0) {
                    commentsListArea.classList.remove('hidden');
                    const commentCount = Number(commentsListArea.closest('.post-item-modal-view').querySelector('.comment-count-display-text').textContent.split(' ')[0]) || 0;
                    const hasNextPage = commentsListArea.querySelector('.modal-load-more-comments-button:not(.hidden)')?.dataset.hasNextPage === 'true';
                    if (!hasNextPage || commentCount === commentsListArea.querySelectorAll('.comment-item-modal').length) {
                         buttonElement.textContent = `Hide comments (${commentsListArea.querySelectorAll('.comment-item-modal').length})`;
                    } else {
                         const currentPage = parseInt(commentsListArea.dataset.currentPage) || 1;
                         buttonElement.textContent = `Hide comments (Page ${currentPage})`; 
                    }
                    const loadMoreBtn = commentsListArea.querySelector('.modal-load-more-comments-button');
                    if (loadMoreBtn && loadMoreBtn.dataset.hasNextPage === 'true') loadMoreBtn.classList.remove('hidden');

                } else {
                    await this._fetchAndDisplayCommentsForModal(postId, 1, commentsListArea, buttonElement);
                    commentsListArea.classList.remove('hidden');
                    commentsListArea.classList.add('comments-loaded');
                }
                buttonElement.classList.remove('loading-comments');
                buttonElement.disabled = false;
            }
        }

        async _fetchAndDisplayCommentsForModal(postId, page = 1, commentsListAreaEl, viewCommentsButtonEl) {
            try {
                // REFACTORED: Use the centralized fetch utility for a GET request
                const result = await this._apiFetch(API_ENDPOINTS.POST_COMMENTS(postId, page));

                if (!result.success) throw new Error(result.message || 'Failed to fetch comments.');
                
                if (page === 1) commentsListAreaEl.innerHTML = '';
                
                const currentlyDisplayedCommentIds = new Set(Array.from(commentsListAreaEl.querySelectorAll('.comment-item-modal')).map(el => el.dataset.commentId));
                
                let newCommentsAdded = 0;
                result.comments.forEach(comment => {
                    if (!currentlyDisplayedCommentIds.has(String(comment.id))) {
                        const commentElement = this._createCommentItemElementForModal(comment);
                        commentsListAreaEl.appendChild(commentElement);
                        newCommentsAdded++;
                    }
                });

                if (window.timeago?.render) window.timeago.render(commentsListAreaEl.querySelectorAll('.comment-timeago:not([data-timeago-rendered])'));
                commentsListAreaEl.querySelectorAll('.comment-timeago:not([data-timeago-rendered])').forEach(el => el.dataset.timeagoRendered = 'true');

                if (result.comments.length === 0 && page === 1 && newCommentsAdded === 0) {
                    // ... (UI logic is unchanged)
                    commentsListAreaEl.innerHTML = '<div class="p-3 pt-1 text-sm text-gray-500 dark:text-gray-400">No comments to show.</div>';
                     if (viewCommentsButtonEl) {
                        const triggerDiv = viewCommentsButtonEl.closest('.view-comments-trigger-modal');
                        if (triggerDiv) { 
                            triggerDiv.innerHTML = '<div class="p-3 pt-1 text-sm text-gray-500 dark:text-gray-400">No comments yet.</div>';
                        }
                    }
                    commentsListAreaEl.dataset.currentPage = '1';
                    commentsListAreaEl.classList.remove('comments-loaded');
                    return; 
                }

                // ... (Pagination/UI logic is unchanged)
                let loadMoreButton = commentsListAreaEl.querySelector('.modal-load-more-comments-button');
                const hasNextPage = result.pagination && result.pagination.current_page < result.pagination.total_pages;

                if (hasNextPage) {
                    if (!loadMoreButton) {
                        loadMoreButton = document.createElement('button');
                        loadMoreButton.className = 'modal-load-more-comments-button text-sm text-blue-600 dark:text-blue-400 hover:underline mt-2 block mx-auto';
                        loadMoreButton.dataset.postId = postId;
                        commentsListAreaEl.appendChild(loadMoreButton); 
                        loadMoreButton.addEventListener('click', async (e) => {
                            const currentButton = e.currentTarget;
                            currentButton.disabled = true;
                            currentButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Loading more...';
                            const nextPageToLoad = (parseInt(commentsListAreaEl.dataset.currentPage) || 1) + 1;
                            await this._fetchAndDisplayCommentsForModal(postId, nextPageToLoad, commentsListAreaEl, viewCommentsButtonEl);
                        });
                    }
                    loadMoreButton.textContent = 'Load more comments';
                    loadMoreButton.disabled = false;
                    loadMoreButton.classList.remove('hidden');
                    loadMoreButton.dataset.hasNextPage = 'true';
                } else { 
                    if (loadMoreButton) loadMoreButton.classList.add('hidden');
                    if (loadMoreButton) loadMoreButton.dataset.hasNextPage = 'false';
                }
                commentsListAreaEl.dataset.currentPage = String(result.pagination.current_page);
                if (newCommentsAdded > 0 || page > 1) commentsListAreaEl.classList.add('comments-loaded');


                if (viewCommentsButtonEl) {
                    if (!commentsListAreaEl.classList.contains('hidden') && commentsListAreaEl.children.length > 0 && !commentsListAreaEl.firstElementChild?.textContent.includes("No comments to show")) {
                         const totalDisplayedComments = commentsListAreaEl.querySelectorAll('.comment-item-modal').length;
                         const commentCountStat = Number(commentsListAreaEl.closest('.post-item-modal-view').querySelector('.comment-count-display-text').textContent.split(' ')[0]) || 0;

                        if (!hasNextPage || totalDisplayedComments === commentCountStat) {
                            viewCommentsButtonEl.textContent = `Hide comments (${totalDisplayedComments})`;
                        } else {
                             viewCommentsButtonEl.textContent = `Hide comments (Page ${result.pagination.current_page} of ${result.pagination.total_pages})`;
                        }
                    } else { 
                         viewCommentsButtonEl.textContent = viewCommentsButtonEl.dataset.originalText || `View comments`;
                    }
                }

            } catch (error) {
                console.error('[HeaderManagerRefactored] Error fetching comments for modal:', error);
                if (page === 1) commentsListAreaEl.innerHTML = `<p class="text-red-500 dark:text-red-400 text-xs">${_sanitizeText(error.message || 'Could not load comments.')}</p>`;
                else {
                    const errorEl = document.createElement('p');
                    errorEl.className = 'text-red-500 dark:text-red-400 text-xs';
                    errorEl.textContent = _sanitizeText(error.message || 'Could not load more comments.');
                    commentsListAreaEl.appendChild(errorEl);
                }
                if (viewCommentsButtonEl) viewCommentsButtonEl.textContent = viewCommentsButtonEl.dataset.originalText || 'Error loading';
            } finally {
                if (viewCommentsButtonEl) {
                    viewCommentsButtonEl.classList.remove('loading-comments');
                    viewCommentsButtonEl.disabled = false;
                    if (commentsListAreaEl.classList.contains('hidden') || commentsListAreaEl.children.length === 0 || (commentsListAreaEl.children.length === 1 && commentsListAreaEl.children[0].textContent.includes("No comments to show"))) {
                         viewCommentsButtonEl.textContent = viewCommentsButtonEl.dataset.originalText || `View comments`;
                    }
                }
                const loadMoreButton = commentsListAreaEl.querySelector('.modal-load-more-comments-button');
                if(loadMoreButton && loadMoreButton.disabled && loadMoreButton.innerHTML.includes('fa-spinner')) {
                    loadMoreButton.textContent = 'Load more comments';
                    loadMoreButton.disabled = false;
                }
            }
        }
        
        _createCommentItemElementForModal(comment) {
            // ... (method is unchanged, it's a pure renderer)
            const item = document.createElement('div');
            item.className = 'comment-item-modal flex items-start space-x-2 text-sm py-1 px-1 text-gray-800 dark:text-gray-200';
            item.dataset.commentId = comment.id;

            const userName = _sanitizeText(comment.user_full_name || 'Anonymous');
            const avatarSrc = comment.user_avatar || comment.user_avatar_fallback || _generateClientFallbackSVG(userName, 24);
            const userProfileLink = `/profile/${comment.user_id}`;
            const content = _sanitizeText(comment.content);
            const timeAgo = _timeAgo(comment.created_at);
            const isEdited = comment.is_edited || (comment.updated_at && comment.updated_at !== comment.created_at);
            
            const commentUserIdStr = comment.user_id ? String(comment.user_id) : null;
            const canDelete = (this.currentUserId && commentUserIdStr && commentUserIdStr === this.currentUserId) || 
                              (this.currentUserId && this.currentPostAuthorIdInModal && this.currentUserId === String(this.currentPostAuthorIdInModal)); 

            let deleteButtonHTML = '';
            if (canDelete) {
                deleteButtonHTML = `
                    <button class="modal-delete-comment-button text-xs text-gray-400 hover:text-red-500 dark:hover:text-red-400 ml-auto p-1" title="Delete comment" data-comment-id="${comment.id}" aria-label="Delete comment">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
            }

            item.innerHTML = `
                <img src="${_sanitizeText(avatarSrc)}" alt="${userName}" class="w-6 h-6 rounded-full object-cover mt-1 flex-shrink-0">
                <div class="flex-1 bg-gray-100 dark:bg-dark-600 p-2 rounded-lg">
                    <div class="flex justify-between items-start">
                         <a href="${userProfileLink}" target="_blank" class="font-semibold text-gray-800 dark:text-gray-100 hover:underline cursor-pointer mr-2">${userName}</a>
                         ${deleteButtonHTML}
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 leading-snug whitespace-pre-wrap">${content}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <span class="comment-timeago" data-timestamp="${_sanitizeText(comment.created_at)}">${timeAgo}</span>
                        ${isEdited ? ' <span class="italic">(edited)</span>' : ''}
                    </p>
                </div>`;
            return item;
        }

        async _handleModalDeleteCommentClick(buttonEl) {
            const commentId = buttonEl.dataset.commentId;
            const commentItemElement = buttonEl.closest('.comment-item-modal');
            const postModalViewElement = buttonEl.closest('.post-item-modal-view');
            const postId = postModalViewElement?.dataset.postId;

            if (!commentId || !commentItemElement || !postModalViewElement || !postId) return;

            const confirmed = await this._showDeleteConfirmationModal('Delete Comment?', 'Are you sure you want to delete this comment? This action cannot be undone.', 'Delete');
            if (!confirmed) return;

            const originalButtonIconHTML = buttonEl.innerHTML; 
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                // REFACTORED: Use the centralized fetch utility for a POST request
                const result = await this._apiFetch(API_ENDPOINTS.DELETE_COMMENT(commentId), {
                    method: 'POST'
                });

                if (!result || !result.success) {
                    throw new Error(result?.message || result?.error || 'Failed to delete comment.');
                }
                
                commentItemElement.remove(); 
                console.log("[HeaderManagerRefactored] Comment deleted successfully:", result);

                if (typeof result.comment_count !== 'undefined') {
                    // ... (UI update logic for comment count is unchanged)
                     const newCommentCount = Number(result.comment_count) || 0;
                    const commentCountStatsSpan = postModalViewElement.querySelector('.comment-count-display-text');
                    if (commentCountStatsSpan) {
                        commentCountStatsSpan.textContent = `${newCommentCount} comment${newCommentCount !== 1 ? 's' : ''}`;
                    }
                    const viewCommentsTriggerDiv = postModalViewElement.querySelector('.view-comments-trigger-modal');
                    const commentsListArea = postModalViewElement.querySelector(`.comments-list-area-modal[data-post-id="${postId}"]`);
                    if (viewCommentsTriggerDiv) {
                        if (newCommentCount === 0) {
                            viewCommentsTriggerDiv.innerHTML = '<div class="p-3 pt-1 text-sm text-gray-500 dark:text-gray-400">No comments yet.</div>';
                            if (commentsListArea) {
                                commentsListArea.innerHTML = ''; 
                                if (!commentsListArea.classList.contains('hidden')) commentsListArea.classList.add('hidden');
                                commentsListArea.classList.remove('comments-loaded');
                                commentsListArea.dataset.currentPage = '1';
                            }
                        } else {
                            let viewCommentsButton = viewCommentsTriggerDiv.querySelector('.modal-view-comments-button');
                            const viewCommentsButtonText = `View ${newCommentCount} comment${newCommentCount !== 1 ? 's' : ''}`;
                            if (!viewCommentsButton) {
                                viewCommentsTriggerDiv.innerHTML = `<button class="modal-view-comments-button text-sm text-gray-600 dark:text-gray-400 hover:underline" data-post-id="${postId}" data-original-text="${viewCommentsButtonText}">${viewCommentsButtonText}</button>`;
                            } else {
                                viewCommentsButton.dataset.originalText = viewCommentsButtonText;
                                if (!commentsListArea || commentsListArea.classList.contains('hidden') || commentsListArea.children.length === 0) {
                                    viewCommentsButton.textContent = viewCommentsButtonText;
                                }
                            }
                        }
                    }
                }

            } catch (error) {
                console.error('[HeaderManagerRefactored] Error deleting comment:', error);
                if (buttonEl?.closest('body')) { 
                    buttonEl.innerHTML = originalButtonIconHTML; 
                    buttonEl.disabled = false;
                    const errorFeedbackArea = postModalViewElement.querySelector('#generalModalFeedbackArea'); 
                    if (errorFeedbackArea) {
                        errorFeedbackArea.textContent = `Error: ${_sanitizeText(error.message)}`;
                        errorFeedbackArea.className = 'p-2 text-sm text-red-600 dark:text-red-400 text-center';
                        setTimeout(() => { errorFeedbackArea.textContent = ''; errorFeedbackArea.className = 'p-2 text-sm text-center'; }, 3000);
                    }
                }
            } finally {
                if (buttonEl?.closest('body') && buttonEl.disabled) {
                    if (buttonEl.innerHTML.includes('fa-spinner')) {
                        buttonEl.innerHTML = originalButtonIconHTML;
                    }
                    buttonEl.disabled = false;
                }
            }
        }

        _showDeleteConfirmationModal(title = 'Confirm Deletion', message = 'Are you sure you want to delete this item? This action cannot be undone.', confirmButtonText = 'Delete') {
            // ... (method is unchanged)
            if (!this.deleteConfirmModalEl || !this.deleteConfirmModalTitleEl || !this.deleteConfirmModalMessageEl || !this.deleteConfirmModalConfirmBtnEl || !this.deleteConfirmModalCancelBtnEl) {
                console.error("[HeaderManagerRefactored] Delete confirmation modal elements are not properly cached or missing from the DOM. Falling back to window.confirm.");
                return Promise.resolve(window.confirm(`${title}\n${message}`));
            }

            this.deleteConfirmModalTitleEl.textContent = title;
            this.deleteConfirmModalMessageEl.innerHTML = _sanitizeText(message);
            this.deleteConfirmModalConfirmBtnEl.textContent = confirmButtonText;
            
            this.deleteConfirmModalEl.classList.remove('hidden');
            this.deleteConfirmModalCancelBtnEl.focus();

            return new Promise((resolve) => {
                let resolved = false;

                const confirmHandler = () => {
                    if (resolved) return;
                    resolved = true;
                    cleanup();
                    resolve(true);
                };

                const cancelHandler = () => {
                    if (resolved) return;
                    resolved = true;
                    cleanup();
                    resolve(false);
                };
                
                const escapeHandler = (event) => {
                    if (event.key === 'Escape') {
                        if (resolved) return;
                        if (!this.deleteConfirmModalEl.classList.contains('hidden')) {
                            resolved = true;
                            cleanup();
                            resolve(false);
                        }
                    }
                };
                
                const modalOverlayClickHandler = (event) => {
                     if (event.target === this.deleteConfirmModalEl) {
                        if (resolved) return;
                        resolved = true;
                        cleanup();
                        resolve(false);
                    }
                };

                const cleanup = () => {
                    this.deleteConfirmModalEl.classList.add('hidden');
                    this.deleteConfirmModalConfirmBtnEl.removeEventListener('click', confirmHandler);
                    this.deleteConfirmModalCancelBtnEl.removeEventListener('click', cancelHandler);
                    this.deleteConfirmModalEl.removeEventListener('click', modalOverlayClickHandler);
                    document.removeEventListener('keydown', escapeHandler, true);
                };

                this.deleteConfirmModalConfirmBtnEl.addEventListener('click', confirmHandler, { once: true });
                this.deleteConfirmModalCancelBtnEl.addEventListener('click', cancelHandler, { once: true });
                this.deleteConfirmModalEl.addEventListener('click', modalOverlayClickHandler);
                document.addEventListener('keydown', escapeHandler, true);
            });
        }
        
        _handleLoggedOutState() {
            // ... (method is unchanged)
            this.currentUserId = null; 
            this.currentUserFullName = 'Guest';
            this.currentUserAvatar = _generateClientFallbackSVG(this.currentUserFullName, 32);
            if (this.userMenuAvatarEl) this._updateUserMenu({ id: null, fullName: this.currentUserFullName, avatarUrl: this.currentUserAvatar });
            
            if (this.notificationManager) {
                this.notificationManager.displayLoggedOutState();
            }
        }

        _handleFetchErrorState() {
            // ... (method is unchanged)
            if (this.userMenuAvatarEl) {
                if (this.currentUserId) {
                     this._updateUserMenu({ id: this.currentUserId, fullName: this.currentUserFullName, avatarUrl: this.currentUserAvatar });
                } else {
                     this._updateUserMenu({ id: null, fullName: 'Guest', avatarUrl: _generateClientFallbackSVG('Guest', 32) });
                }
            }
            if (this.notificationManager) {
                this.notificationManager.displayErrorState();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.globalHeaderManager = new HeaderManager();
    });

})(window, document);