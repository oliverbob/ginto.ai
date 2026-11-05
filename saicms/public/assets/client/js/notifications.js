// assets/client/js/notification-manager.js

(function(window, document, undefined) {
    'use strict';

    const CONFIG = {
        logPrefix: "[NotificationManager]",
        defaultAvatarSize: 32,
        modalAvatarSize: 64,
        friendRequestType: 'friend_request_received',
        friendRequestAcceptedType: 'friend_request_accepted',
        postRelatedNotificationTypes: [
            'post_like',
            'post_comment',
            'post_share',
            'comment_reply'
        ],
        selectors: {
            notificationBtn: '#notificationBtn',
            notificationUnreadBadge: '#notificationUnreadBadge',
            notificationListContainer: '#notificationListContainer',
            notificationEmptyState: '#notificationEmptyState',
            notificationLoadingState: '#notificationLoadingState',
            markAllNotificationsReadBtn: '#markAllNotificationsReadBtn',
            notificationViewAllContainer: '#notificationViewAllContainer',
            notificationLoadMoreSpinner: '#notificationLoadMoreSpinner',
            notificationModal: '#notificationContentModal',
            notificationModalTitle: '#notificationModalTitle',
            notificationModalBody: '#notificationModalBody',
            notificationModalViewLink: '#notificationModalViewLink',
            notificationModalAcceptBtn: '#notificationModalAcceptBtn',
            notificationModalDeclineBtn: '#notificationModalDeclineBtn',
            notificationModalCloseBtn: '#notificationModalCloseBtn',
            csrfTokenMeta: 'meta[name="csrf-token"]' // Added for clarity
        },
        postViewModalConfig: {
            postViewModal: '#nmPostViewModal',
            postViewModalTitle: '#nmPostViewModalTitle',
            postViewModalBody: '#nmPostViewModalBody',
            postViewModalCloseBtn: '#nmPostViewModalCloseBtn',
        },
        apiEndpoints: {
            MARK_ALL_NOTIFICATIONS_READ: '/post/notifications/mark-all-read',
            MARK_SINGLE_NOTIFICATION_READ: (notificationId) => `/post/notifications/${notificationId}/mark-read`,
            ACCEPT_FRIEND_REQUEST: (requestId) => `/friends/notifications/${requestId}/accept`,
            DECLINE_FRIEND_REQUEST: (requestId) => `/friends/notifications/${requestId}/decline`,
            GET_PAGINATED_NOTIFICATIONS: (page, limit = 10) => `/post/header-data?page=${page}&limit=${limit}`,
            MODAL_POST_DATA: (postId) => `/post/${postId}`,
            TOGGLE_POST_LIKE: '/post/like',
            ADD_POST_COMMENT: '/post/comment',
            POST_COMMENTS: (postId, page = 1) => `/post/${postId}/comments?page=${page}`,
            DELETE_COMMENT: (commentId) => `/post/comments/${commentId}/delete`,
            EDIT_COMMENT: (commentId) => `/post/comments/${commentId}/edit`,
            UPDATE_POST: (postId) => `/post/${postId}/update`,
            DELETE_POST: (postId) => `/post/${postId}/delete`,
        },
        cssClasses: {
            hidden: 'hidden',
            unreadNotificationItem: 'notification-unread',
            unreadBgBlue: 'bg-blue-50',
            unreadDarkBg: 'dark:bg-dark-700',
            unreadDot: 'notification-unread-dot'
        },
        text: {
            loadingNotifications: 'Loading notifications...',
            noNewNotifications: 'No new notifications.',
            loginToSeeNotifications: 'Login to see notifications.',
            couldNotLoadNotifications: 'Could not load notifications.',
            notificationFrom: (name) => `Notification from ${name || 'User'}`,
            friendRequestTitle: 'Friend Request',
            friendRequestMessage: (name) => `${name} sent you a friend request.`,
            friendRequestAcceptedTitle: 'Friend Request Accepted',
            friendRequestAcceptedMessage: (name) => `${name} accepted your friend request!`,
            genericError: 'An unexpected error occurred. Please try again.',
            processing: '<i class="fas fa-spinner fa-spin text-sm mr-1"></i> Processing...',
            accept: 'Accept',
            decline: 'Decline',
            close: 'Close',
            viewOriginalContext: 'View Context',
            viewUserProfile: 'View Profile',
            noSpecificContent: 'This notification does not directly reference specific content.'
        },
        scrollThreshold: 80
    };

    const DOM = {
        getElement: (selector, context = document) => context.querySelector(selector),
        getAllElements: (selector, context = document) => context.querySelectorAll(selector),
        addClass: (el, className) => el && el.classList.add(className),
        removeClass: (el, className) => el && el.classList.remove(className),
        toggleClass: (el, className, force) => el && el.classList.toggle(className, force),
        setText: (el, text) => el && (el.textContent = text),
        setHtml: (el, html) => el && (el.innerHTML = html),
        setAttr: (el, attr, value) => el && el.setAttribute(attr, value),
        removeAttr: (el, attr) => el && el.removeAttribute(attr),
        hide: (el) => el && DOM.addClass(el, CONFIG.cssClasses.hidden),
        show: (el) => el && DOM.removeClass(el, CONFIG.cssClasses.hidden),
        create: (tag, props = {}) => {
            const el = document.createElement(tag);
            Object.entries(props).forEach(([key, value]) => {
                if (key === 'textContent') el.textContent = value;
                else if (key === 'innerHTML') el.innerHTML = value;
                else if (key === 'className') el.className = value;
                else if (key.startsWith('on') && typeof value === 'function') {
                    el.addEventListener(key.substring(2).toLowerCase(), value);
                } else {
                    el.setAttribute(key, value);
                }
            });
            return el;
        }
    };

    const Utils = {
        sanitizeText: (str, allowBreaks = false) => {
            if (str === null || typeof str === 'undefined') return '';
            if (typeof str !== 'string') str = String(str);
            const temp = DOM.create('div', {
                textContent: str
            });
            let sanitized = temp.innerHTML;
            if (allowBreaks) sanitized = sanitized.replace(/\n/g, '<br>');
            return sanitized;
        },
        generateFallbackSVG: (name, size = CONFIG.defaultAvatarSize) => {
            name = Utils.sanitizeText(name);
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
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${bgColor}"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="${initial.length > 1 ? 40 : 50}" fill="white" font-weight="bold">${initial}</text></svg>`;
            return "data:image/svg+xml;base64," + btoa(svg);
        },
        timeAgo: (dateString) => {
            if (!dateString) return 'Some time ago';
            const dateStrNormalized = dateString.includes('T') ? dateString : dateString.replace(' ', 'T');
            const date = new Date(dateStrNormalized.endsWith('Z') ? dateStrNormalized : dateStrNormalized + 'Z');
            if (isNaN(date.getTime())) {
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

            const options = {
                month: 'short',
                day: 'numeric'
            };
            if (date.getFullYear() !== now.getFullYear()) options.year = 'numeric';
            return date.toLocaleDateString(undefined, options);
        }
    };

    class NotificationManager {
        constructor(options = {}) {
            this.elements = {};
            this.currentUserData = options.currentUserData || { id: null, fullName: 'Guest', avatar: null };
            this.currentUserId = (this.currentUserData.id != null) ? String(this.currentUserData.id) : null;
            this.currentUserAvatar = this.currentUserData.avatar || Utils.generateFallbackSVG(this.currentUserData.fullName, 32);

            this.currentModalNotification = null;
            this.currentPage = 1;
            this.isLoadingMore = false;
            this.hasMoreNotifications = true;
            this._boundHandleScroll = this._handleScroll.bind(this);
            this.scrollListenerAttached = false;
            this.editingCommentId = null;

            this._validateAndCacheDOMElements(options.elements);
            if (!this.isInitialized) return;

            this._bindStaticEvents();
            this._setInitialUIState();
        }

        _validateAndCacheDOMElements(customElements = {}) {
            let allCoreElementsPresent = true;
            for (const key in CONFIG.selectors) {
                const selector = customElements[key] || CONFIG.selectors[key];
                this.elements[key] = (typeof selector === 'string') ? DOM.getElement(selector) : selector;
                if (!this.elements[key]) { // Simplified check for all selectors
                    const isCritical = ['notificationBtn', 'notificationModal', 'notificationModalTitle', 'notificationModalBody', 'notificationListContainer'].includes(key);
                    if (isCritical) {
                        allCoreElementsPresent = false;
                    }
                }
            }
            this.isInitialized = allCoreElementsPresent;
        }

        /*********************************************************************
         * REFACTORED FUNCTION
         * This is the only function that needed changes to implement CSRF.
         *********************************************************************/
        _getAjaxHeaders() {
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            // Find the CSRF token meta tag in the document's head
            const csrfMeta = DOM.getElement(CONFIG.selectors.csrfTokenMeta);
            
            // Get the token from the 'content' attribute
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

            // If a token is found, add it to the headers.
            // The backend middleware will look for this 'X-CSRF-TOKEN' header.
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }
            
            return headers;
        }

        _bindStaticEvents() {
            if (this.elements.markAllNotificationsReadBtn) {
                this.elements.markAllNotificationsReadBtn.addEventListener('click', () => this._handleMarkAllNotificationsRead());
            }
            if (this.elements.notificationModalCloseBtn) {
                this.elements.notificationModalCloseBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.closeNotificationModal();
                });
            }
            if (this.elements.notificationModal) {
                this.elements.notificationModal.addEventListener('click', (event) => {
                    if (event.target === this.elements.notificationModal) this.closeNotificationModal();
                });
            }
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (this.elements.notificationModal && !this.elements.notificationModal.classList.contains(CONFIG.cssClasses.hidden)) {
                        this.closeNotificationModal();
                    }
                    const postModal = DOM.getElement(CONFIG.postViewModalConfig.postViewModal);
                    if (postModal && !postModal.classList.contains(CONFIG.cssClasses.hidden)) {
                        this._closePostViewModal();
                    }
                }
            });
        }

        _setInitialUIState() {
            DOM.show(this.elements.notificationLoadingState);
            DOM.hide(this.elements.notificationEmptyState);
            DOM.setHtml(this.elements.notificationListContainer, '');
            if (this.elements.notificationLoadMoreSpinner) DOM.hide(this.elements.notificationLoadMoreSpinner);
            this.currentPage = 1;
            this.isLoadingMore = false;
            this.hasMoreNotifications = true;
            if (this.scrollListenerAttached && this.elements.notificationListContainer) {
                this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                this.scrollListenerAttached = false;
            }
        }

        updateUserData(newUserData) {
            this.currentUserData = newUserData || { id: null, fullName: 'Guest', avatar: null };
            this.currentUserId = (this.currentUserData.id != null) ? String(this.currentUserData.id) : null;
            this.currentUserAvatar = this.currentUserData.avatar || Utils.generateFallbackSVG(this.currentUserData.fullName, 32);
        }

        updateNotificationsDisplay(notifications = [], unreadCount = 0, hasMore = false) {
            if (!this.isInitialized || !this.elements.notificationListContainer) return;
            this.currentPage = 1;
            this.hasMoreNotifications = hasMore;
            this.isLoadingMore = false;
            if (this.scrollListenerAttached && this.elements.notificationListContainer) {
                this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                this.scrollListenerAttached = false;
            }
            DOM.hide(this.elements.notificationLoadingState);
            DOM.setHtml(this.elements.notificationListContainer, '');
            this._updateUnreadBadgeCount(unreadCount);
            DOM.toggleClass(this.elements.markAllNotificationsReadBtn, CONFIG.cssClasses.hidden, !(unreadCount > 0 && notifications.length > 0));

            if (notifications.length > 0) {
                DOM.hide(this.elements.notificationEmptyState);
                notifications.forEach(notification => {
                    const item = this._createNotificationItemElement(notification);
                    this.elements.notificationListContainer.appendChild(item);
                });
            } else {
                DOM.setText(this.elements.notificationEmptyState, this.currentUserId ? CONFIG.text.noNewNotifications : CONFIG.text.loginToSeeNotifications);
                DOM.show(this.elements.notificationEmptyState);
            }
            DOM.toggleClass(this.elements.notificationViewAllContainer, CONFIG.cssClasses.hidden, !this.hasMoreNotifications);

            if (this.hasMoreNotifications && this.elements.notificationListContainer && !this.scrollListenerAttached) {
                this.elements.notificationListContainer.addEventListener('scroll', this._boundHandleScroll);
                this.scrollListenerAttached = true;
                setTimeout(() => {
                    if (this.hasMoreNotifications && !this.isLoadingMore &&
                        this.elements.notificationListContainer &&
                        this.elements.notificationListContainer.scrollHeight <= this.elements.notificationListContainer.clientHeight) {
                        this._loadMoreNotifications();
                    }
                }, 100);
            } else if (!this.hasMoreNotifications && this.scrollListenerAttached && this.elements.notificationListContainer) {
                this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                this.scrollListenerAttached = false;
            }
            if (this.elements.notificationLoadMoreSpinner) DOM.hide(this.elements.notificationLoadMoreSpinner);
        }

        _createNotificationItemElement(notification) {
            const isUnread = !notification.is_read;
            const actorName = Utils.sanitizeText(notification.actor_name_parsed || 'User');
            const actorAvatarSrc = notification.actor_avatar_url || Utils.generateFallbackSVG(actorName, CONFIG.defaultAvatarSize);
            const itemClasses = ['flex', 'items-center', 'p-3', 'hover:bg-gray-100', 'dark:hover:bg-dark-600', 'border-b', 'dark:border-dark-600', 'cursor-pointer', 'notification-item'];
            if (isUnread) itemClasses.push(CONFIG.cssClasses.unreadNotificationItem, CONFIG.cssClasses.unreadBgBlue, CONFIG.cssClasses.unreadDarkBg);

            const item = DOM.create('a', {
                href: this._getNotificationLink(notification),
                className: itemClasses.join(' '),
                'data-notification-id': notification.id,
                role: 'button',
                'aria-label': `View notification: ${Utils.sanitizeText(notification.message)}`,
                onClick: (event) => {
                    event.preventDefault();
                    this._handleNotificationItemClick(notification, event.currentTarget);
                }
            });
            item.innerHTML = `
                <img src="${actorAvatarSrc}" alt="${actorName}" class="w-10 h-10 rounded-full flex-shrink-0 object-cover">
                <div class="ml-3 overflow-hidden">
                    <p class="dark:text-gray-100 text-gray-800 text-sm leading-snug pointer-events-none">${Utils.sanitizeText(notification.message)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 pointer-events-none">${Utils.timeAgo(notification.created_at)}</p>
                </div>
                ${isUnread ? `<span class="${CONFIG.cssClasses.unreadDot} ml-auto w-2 h-2 bg-blue-500 rounded-full flex-shrink-0" title="Unread"></span>` : ''}`;
            return item;
        }

        _getNotificationLink(notification) {
            let link = `#view-notification-${notification.id}`;
            if (notification.context) {
                if (notification.context.post_id) {
                    link = `/post/${notification.context.post_id}`;
                    if (notification.context.comment_id) link += `#comment-${notification.context.comment_id}`;
                } else if (notification.context.link) link = notification.context.link;
            }
            return Utils.sanitizeText(link);
        }

        _handleNotificationItemClick(notification, clickedItemElement) {
            this._markSingleNotificationAsRead(notification, clickedItemElement);
            const postId = notification.context?.post_id;

            if (CONFIG.postRelatedNotificationTypes.includes(notification.type) && postId) {
                this._handleViewPostInModal(postId);
                const notificationBtn = DOM.getElement(CONFIG.selectors.notificationBtn);
                if (notificationBtn) {
                     notificationBtn.blur();
                }
            } else {
                this.openNotificationModal(notification);
            }
        }

        async _markSingleNotificationAsRead(notification, clickedItemElement) {
            if (!notification || notification.is_read || !clickedItemElement || !clickedItemElement.classList.contains(CONFIG.cssClasses.unreadNotificationItem)) return;
            DOM.removeClass(clickedItemElement, CONFIG.cssClasses.unreadNotificationItem);
            DOM.removeClass(clickedItemElement, CONFIG.cssClasses.unreadBgBlue);
            DOM.removeClass(clickedItemElement, CONFIG.cssClasses.unreadDarkBg);
            const dot = DOM.getElement(`.${CONFIG.cssClasses.unreadDot}`, clickedItemElement);
            if (dot) dot.remove();
            try {
                const headers = this._getAjaxHeaders();
                const response = await fetch(CONFIG.apiEndpoints.MARK_SINGLE_NOTIFICATION_READ(notification.id), {
                    method: 'POST',
                    headers
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    notification.is_read = true;
                    if (typeof result.data?.unread_count !== 'undefined') this._updateUnreadBadgeCount(result.data.unread_count);
                } else throw new Error(result.message || `API failed to mark notification ${notification.id} as read.`);
            } catch (error) {
                DOM.addClass(clickedItemElement, CONFIG.cssClasses.unreadNotificationItem);
                DOM.addClass(clickedItemElement, CONFIG.cssClasses.unreadBgBlue);
                DOM.addClass(clickedItemElement, CONFIG.cssClasses.unreadDarkBg);
                if (!DOM.getElement(`.${CONFIG.cssClasses.unreadDot}`, clickedItemElement)) {
                    clickedItemElement.appendChild(DOM.create('span', {
                        className: `${CONFIG.cssClasses.unreadDot} ml-auto w-2 h-2 bg-blue-500 rounded-full flex-shrink-0`,
                        title: 'Unread'
                    }));
                }
            }
        }

        async _handleMarkAllNotificationsRead() {
            const btn = this.elements.markAllNotificationsReadBtn;
            if (!btn || btn.disabled) return;
            const originalButtonText = btn.innerHTML;
            btn.disabled = true;
            DOM.setHtml(btn, CONFIG.text.processing);
            try {
                const headers = this._getAjaxHeaders();
                const response = await fetch(CONFIG.apiEndpoints.MARK_ALL_NOTIFICATIONS_READ, {
                    method: 'POST',
                    headers
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    DOM.getAllElements(`.${CONFIG.cssClasses.unreadNotificationItem}`, this.elements.notificationListContainer)
                        .forEach(item => {
                            DOM.removeClass(item, CONFIG.cssClasses.unreadNotificationItem);
                            DOM.removeClass(item, CONFIG.cssClasses.unreadBgBlue);
                            DOM.removeClass(item, CONFIG.cssClasses.unreadDarkBg);
                            DOM.getElement(`.${CONFIG.cssClasses.unreadDot}`, item)?.remove();
                        });
                    DOM.hide(btn);
                    this._updateUnreadBadgeCount(result.data?.unread_count ?? 0);
                } else throw new Error(result.error || `Server error: ${response.status}`);
            } catch (error) {
                // Error handling can be added here
            } finally {
                DOM.setHtml(btn, originalButtonText);
                btn.disabled = false;
            }
        }

        _updateUnreadBadgeCount(unreadCount) {
            const badge = this.elements.notificationUnreadBadge;
            if (!badge) return;
            const count = parseInt(unreadCount, 10);
            if (count > 0) {
                DOM.setText(badge, count > 99 ? '99+' : count);
                DOM.show(badge);
            } else {
                DOM.setText(badge, '0');
                DOM.hide(badge);
            }
            if (this.elements.markAllNotificationsReadBtn) {
                DOM.toggleClass(this.elements.markAllNotificationsReadBtn, CONFIG.cssClasses.hidden,
                    count === 0 || (this.elements.notificationListContainer && this.elements.notificationListContainer.children.length === 0)
                );
            }
        }

        _handleScroll() {
            if (!this.elements.notificationListContainer || !this.scrollListenerAttached) return;
            const {
                scrollTop,
                scrollHeight,
                clientHeight
            } = this.elements.notificationListContainer;
            if (scrollHeight - scrollTop - clientHeight < CONFIG.scrollThreshold) {
                if (this.hasMoreNotifications && !this.isLoadingMore) this._loadMoreNotifications();
            }
        }

        async _loadMoreNotifications() {
            if (this.isLoadingMore || !this.hasMoreNotifications) {
                if (!this.hasMoreNotifications && this.scrollListenerAttached && this.elements.notificationListContainer) {
                    this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                    this.scrollListenerAttached = false;
                }
                return;
            }
            this.isLoadingMore = true;
            this.currentPage++;
            if (this.elements.notificationLoadMoreSpinner) DOM.show(this.elements.notificationLoadMoreSpinner);
            if (this.elements.notificationViewAllContainer) DOM.hide(this.elements.notificationViewAllContainer);

            try {
                const endpoint = CONFIG.apiEndpoints.GET_PAGINATED_NOTIFICATIONS(this.currentPage);
                const headers = this._getAjaxHeaders();
                const response = await fetch(endpoint, {
                    method: 'GET',
                    headers
                });
                if (!response.ok) throw new Error(`API Error: ${response.status} ${response.statusText}`);
                const result = await response.json();

                if (result.success && typeof result.notifications !== 'undefined' && typeof result.hasMoreNotifications !== 'undefined') {
                    const newNotifications = result.notifications;
                    this.hasMoreNotifications = result.hasMoreNotifications;
                    if (newNotifications.length > 0) {
                        newNotifications.forEach(notification => {
                            const item = this._createNotificationItemElement(notification);
                            if (this.elements.notificationListContainer) this.elements.notificationListContainer.appendChild(item);
                        });
                    }
                    if (!this.hasMoreNotifications && this.scrollListenerAttached && this.elements.notificationListContainer) {
                        this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                        this.scrollListenerAttached = false;
                    } else if (this.hasMoreNotifications && !this.scrollListenerAttached && this.elements.notificationListContainer) {
                        this.elements.notificationListContainer.addEventListener('scroll', this._boundHandleScroll);
                        this.scrollListenerAttached = true;
                    }
                } else {
                    this.hasMoreNotifications = false;
                    if (this.scrollListenerAttached && this.elements.notificationListContainer) {
                        this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                        this.scrollListenerAttached = false;
                    }
                }
            } catch (error) {
                this.hasMoreNotifications = false;
                if (this.scrollListenerAttached && this.elements.notificationListContainer) {
                    this.elements.notificationListContainer.removeEventListener('scroll', this._boundHandleScroll);
                    this.scrollListenerAttached = false;
                }
            } finally {
                this.isLoadingMore = false;
                if (this.elements.notificationLoadMoreSpinner) DOM.hide(this.elements.notificationLoadMoreSpinner);
                if (this.elements.notificationViewAllContainer) {
                    DOM.toggleClass(this.elements.notificationViewAllContainer, CONFIG.cssClasses.hidden, !this.hasMoreNotifications);
                }
            }
        }
        
        openNotificationModal(notification) {
            if (!this.isInitialized || !this.elements.notificationModal) return;
            this.currentModalNotification = notification;
            [this.elements.notificationModalAcceptBtn, this.elements.notificationModalDeclineBtn].forEach(btn => {
                if (btn) {
                    btn.onclick = null;
                    btn.disabled = false;
                    DOM.hide(btn);
                }
            });
            DOM.setText(this.elements.notificationModalAcceptBtn, CONFIG.text.accept);
            DOM.setText(this.elements.notificationModalDeclineBtn, CONFIG.text.decline);
            DOM.hide(this.elements.notificationModalViewLink);
            if (notification.type === CONFIG.friendRequestType) this._populateFriendRequestModal(notification);
            else if (notification.type === CONFIG.friendRequestAcceptedType) this._populateFriendRequestAcceptedModal(notification);
            else this._populateGenericNotificationModal(notification);
            DOM.show(this.elements.notificationModal);
            DOM.setAttr(this.elements.notificationModal, 'aria-hidden', 'false');
            const firstFocusable = this.elements.notificationModalAcceptBtn?.classList.contains(CONFIG.cssClasses.hidden) ?
                (this.elements.notificationModalViewLink?.classList.contains(CONFIG.cssClasses.hidden) ? this.elements.notificationModalCloseBtn : this.elements.notificationModalViewLink) :
                this.elements.notificationModalAcceptBtn;
            if (firstFocusable) firstFocusable.focus();
            else if (this.elements.notificationModalCloseBtn) this.elements.notificationModalCloseBtn.focus();
        }

        _populateFriendRequestModal(notification) {
            const requesterName = Utils.sanitizeText(notification.actor_name_parsed || 'User');
            const requesterAvatarSrc = notification.actor_avatar_url || Utils.generateFallbackSVG(requesterName, CONFIG.modalAvatarSize);
            DOM.setText(this.elements.notificationModalTitle, CONFIG.text.friendRequestTitle);
            DOM.setHtml(this.elements.notificationModalBody, `
                <div class="flex flex-col items-center text-center py-4">
                    <img src="${requesterAvatarSrc}" alt="${requesterName}" class="w-20 h-20 rounded-full mb-4 object-cover shadow-md">
                    <p class="dark:text-gray-100 text-gray-800 text-lg font-semibold mb-1">${requesterName}</p>
                    <p class="text-gray-600 dark:text-gray-300 text-sm mb-3">${CONFIG.text.friendRequestMessage(requesterName)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Received: ${Utils.timeAgo(notification.created_at)}</p>
                </div>`);
            DOM.show(this.elements.notificationModalAcceptBtn);
            DOM.show(this.elements.notificationModalDeclineBtn);
            this.elements.notificationModalAcceptBtn.onclick = () => this._handleFriendRequestAction('accept', notification);
            this.elements.notificationModalDeclineBtn.onclick = () => this._handleFriendRequestAction('decline', notification);
            DOM.hide(this.elements.notificationModalViewLink);
        }

        _populateFriendRequestAcceptedModal(notification) {
            const accepterName = Utils.sanitizeText(notification.actor_name_parsed || 'User');
            const accepterAvatarSrc = notification.actor_avatar_url || Utils.generateFallbackSVG(accepterName, CONFIG.modalAvatarSize);
            DOM.setText(this.elements.notificationModalTitle, CONFIG.text.friendRequestAcceptedTitle);
            DOM.setHtml(this.elements.notificationModalBody, `
                <div class="flex flex-col items-center text-center py-4">
                    <img src="${accepterAvatarSrc}" alt="${accepterName}" class="w-20 h-20 rounded-full mb-4 object-cover shadow-md">
                    <p class="dark:text-gray-100 text-gray-800 text-lg font-semibold mb-1">${accepterName}</p>
                    <p class="text-gray-600 dark:text-gray-300 text-sm mb-3">${CONFIG.text.friendRequestAcceptedMessage(accepterName)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Notified: ${Utils.timeAgo(notification.created_at)}</p>
                </div>`);
            DOM.hide(this.elements.notificationModalAcceptBtn);
            DOM.hide(this.elements.notificationModalDeclineBtn);
            let profileLink = null;
            const actorIdFromContext = notification.context && notification.context.actor_id;
            if (actorIdFromContext !== null && actorIdFromContext !== undefined) {
                const sanitizedActorId = Utils.sanitizeText(String(actorIdFromContext));
                if (sanitizedActorId && sanitizedActorId.trim() !== '') profileLink = `/profile/${sanitizedActorId}`;
            }
            if (profileLink) {
                DOM.setAttr(this.elements.notificationModalViewLink, 'href', profileLink);
                DOM.setAttr(this.elements.notificationModalViewLink, 'target', '_self');
                DOM.setAttr(this.elements.notificationModalViewLink, 'rel', 'noopener noreferrer');
                DOM.setText(this.elements.notificationModalViewLink, CONFIG.text.viewUserProfile);
                this.elements.notificationModalViewLink.onclick = null;
                DOM.show(this.elements.notificationModalViewLink);
            } else DOM.hide(this.elements.notificationModalViewLink);
        }

        _populateGenericNotificationModal(notification) {
            const actorName = Utils.sanitizeText(notification.actor_name_parsed || 'User');
            const actorAvatarSrc = notification.actor_avatar_url || Utils.generateFallbackSVG(actorName, CONFIG.modalAvatarSize);
            DOM.setText(this.elements.notificationModalTitle, CONFIG.text.notificationFrom(actorName));
            DOM.setHtml(this.elements.notificationModalBody, `
                <div class="flex items-start py-4">
                    <img src="${actorAvatarSrc}" alt="${actorName}" class="w-12 h-12 rounded-full mr-3 object-cover shadow">
                    <div class="flex-grow">
                        <p class="dark:text-gray-100 text-gray-800 text-md mb-1">${Utils.sanitizeText(notification.message)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Received: ${Utils.timeAgo(notification.created_at)}</p>
                    </div>
                </div>`);
            DOM.hide(this.elements.notificationModalAcceptBtn);
            DOM.hide(this.elements.notificationModalDeclineBtn);
            const contextLink = this._getNotificationLink(notification);
            if (contextLink && !contextLink.startsWith('#view-notification-')) {
                DOM.setAttr(this.elements.notificationModalViewLink, 'href', contextLink);
                try {
                    const url = new URL(contextLink, window.location.origin);
                    if (url.origin !== window.location.origin) {
                        DOM.setAttr(this.elements.notificationModalViewLink, 'target', '_blank');
                        DOM.setAttr(this.elements.notificationModalViewLink, 'rel', 'noopener noreferrer');
                    } else {
                        DOM.removeAttr(this.elements.notificationModalViewLink, 'target');
                        DOM.removeAttr(this.elements.notificationModalViewLink, 'rel');
                    }
                } catch (e) {
                    DOM.removeAttr(this.elements.notificationModalViewLink, 'target');
                    DOM.removeAttr(this.elements.notificationModalViewLink, 'rel');
                }
                DOM.setText(this.elements.notificationModalViewLink, CONFIG.text.viewOriginalContext);
                this.elements.notificationModalViewLink.onclick = null;
                DOM.show(this.elements.notificationModalViewLink);
            } else {
                DOM.setHtml(this.elements.notificationModalBody, this.elements.notificationModalBody.innerHTML + `<p class="text-sm text-gray-500 dark:text-gray-400 mt-3 text-center">${CONFIG.text.noSpecificContent}</p>`);
                DOM.hide(this.elements.notificationModalViewLink);
            }
        }

        closeNotificationModal() {
            if (!this.elements.notificationModal || this.elements.notificationModal.classList.contains(CONFIG.cssClasses.hidden)) return;
            DOM.hide(this.elements.notificationModal);
            DOM.setAttr(this.elements.notificationModal, 'aria-hidden', 'true');
            this.currentModalNotification = null;
            DOM.setHtml(this.elements.notificationModalBody, `<p class="text-gray-700 dark:text-gray-300">${CONFIG.text.loadingNotifications}</p>`);
            DOM.setText(this.elements.notificationModalTitle, 'Notification Details');
            DOM.hide(this.elements.notificationModalAcceptBtn);
            DOM.hide(this.elements.notificationModalDeclineBtn);
            DOM.hide(this.elements.notificationModalViewLink);
            DOM.removeAttr(this.elements.notificationModalViewLink, 'href');
            DOM.removeAttr(this.elements.notificationModalViewLink, 'target');
            DOM.removeAttr(this.elements.notificationModalViewLink, 'rel');
            DOM.setText(this.elements.notificationModalViewLink, CONFIG.text.viewOriginalContext);
        }

        async _handleFriendRequestAction(action, notification) {
            const friendshipId = notification.context?.friendship_id;
            const notificationIdForListItem = notification.id;
            if (!friendshipId) {
                DOM.setHtml(this.elements.notificationModalBody, this.elements.notificationModalBody.innerHTML + `<p class="text-red-500 text-sm mt-2 text-center">Error: Missing context.</p>`);
                return;
            }
            const actionBtn = action === 'accept' ? this.elements.notificationModalAcceptBtn : this.elements.notificationModalDeclineBtn;
            const otherBtn = action === 'accept' ? this.elements.notificationModalDeclineBtn : this.elements.notificationModalAcceptBtn;
            actionBtn.disabled = true;
            if (otherBtn) otherBtn.disabled = true;
            DOM.setHtml(actionBtn, CONFIG.text.processing);
            const endpoint = action === 'accept' ? CONFIG.apiEndpoints.ACCEPT_FRIEND_REQUEST(friendshipId) : CONFIG.apiEndpoints.DECLINE_FRIEND_REQUEST(friendshipId);
            try {
                const headers = this._getAjaxHeaders();
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    DOM.setHtml(this.elements.notificationModalBody, `<div class="flex flex-col items-center text-center py-4"><p class="text-green-500 text-lg font-semibold">${Utils.sanitizeText(result.message || (action === 'accept' ? 'Friend request accepted!' : 'Friend request declined.'))}</p></div>`);
                    DOM.hide(this.elements.notificationModalAcceptBtn);
                    DOM.hide(this.elements.notificationModalDeclineBtn);
                    const listItemToRemove = DOM.getElement(`.notification-item[data-notification-id="${notificationIdForListItem}"]`, this.elements.notificationListContainer);
                    if (listItemToRemove) listItemToRemove.remove();
                    if (result.data && typeof result.data.unread_count !== 'undefined') this._updateUnreadBadgeCount(result.data.unread_count);
                    DOM.setText(this.elements.notificationModalViewLink, CONFIG.text.close);
                    DOM.show(this.elements.notificationModalViewLink);
                    DOM.removeAttr(this.elements.notificationModalViewLink, 'target');
                    DOM.removeAttr(this.elements.notificationModalViewLink, 'rel');
                    DOM.setAttr(this.elements.notificationModalViewLink, 'href', '#');
                    this.elements.notificationModalViewLink.onclick = (e) => {
                        e.preventDefault();
                        this.closeNotificationModal();
                    };
                    if (this.elements.notificationModalViewLink) this.elements.notificationModalViewLink.focus();
                    setTimeout(() => {
                        if (this.currentModalNotification && this.currentModalNotification.id === notification.id &&
                            this.elements.notificationModal && !this.elements.notificationModal.classList.contains(CONFIG.cssClasses.hidden)) {
                            this.closeNotificationModal();
                        }
                    }, 2500);
                } else throw new Error(result.message || `Failed to ${action} friend request.`);
            } catch (error) {
                const currentBodyContent = this.elements.notificationModalBody.innerHTML;
                DOM.setHtml(this.elements.notificationModalBody, currentBodyContent + `<p class="text-red-500 text-sm mt-2 text-center">Error: ${Utils.sanitizeText(error.message || CONFIG.text.genericError)}</p>`);
                actionBtn.disabled = false;
                if (otherBtn) otherBtn.disabled = false;
                DOM.setText(actionBtn, action === 'accept' ? CONFIG.text.accept : CONFIG.text.decline);
            }
        }

        _getPostViewModalElements() {
            const modalConfig = CONFIG.postViewModalConfig;
            const elements = {
                modal: DOM.getElement(modalConfig.postViewModal),
                title: DOM.getElement(modalConfig.postViewModalTitle),
                body: DOM.getElement(modalConfig.postViewModalBody),
                closeBtn: DOM.getElement(modalConfig.postViewModalCloseBtn),
            };
            return elements;
        }

        _createPostViewModalStructure() {
            const modalConfig = CONFIG.postViewModalConfig;
            const modalId = modalConfig.postViewModal.substring(1);
            let elements = this._getPostViewModalElements();

            if (elements.modal) {
                if (elements.body && !elements.body._modalContentListenersAttached) {
                    this._bindModalContentEventListeners(elements.body);
                }
                return elements;
            }

            const modalOverlay = DOM.create('div', {
                id: modalId,
                className: 'fixed inset-0 bg-black bg-opacity-60 dark:bg-opacity-75 overflow-y-auto h-full w-full z-[60] flex justify-center items-center p-4 pfm-modal-overlay hidden animate-fade-in-up',
            });

            modalOverlay.innerHTML = `
                <div class="relative bg-white dark:bg-dark-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                    <div class="flex justify-between items-center px-4 py-3 border-b border-gray-200 dark:border-dark-700">
                        <h3 id="${modalConfig.postViewModalTitle.substring(1)}" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Post Details</h3>
                        <button id="${modalConfig.postViewModalCloseBtn.substring(1)}" class="text-gray-500 dark:text-gray-400 bg-transparent hover:bg-gray-200 dark:hover:bg-dark-700 rounded-md p-1.5 focus:outline-none" aria-label="Close modal">
                            <i class="fas fa-times w-5 h-5"></i>
                        </button>
                    </div>
                    <div id="${modalConfig.postViewModalBody.substring(1)}" class="overflow-y-auto flex-grow bg-white dark:bg-dark-800 p-0">
                        ${this._getStaticInitialModalBodyContent()}
                    </div>
                </div>`;

            document.body.appendChild(modalOverlay);
            elements = this._getPostViewModalElements();

            if (elements.closeBtn) {
                elements.closeBtn.addEventListener('click', () => this._closePostViewModal());
            }
            if (elements.modal) {
                elements.modal.addEventListener('click', (event) => {
                    if (event.target === elements.modal) this._closePostViewModal();
                });
            }
            
            if (elements.body) {
                this._bindModalContentEventListeners(elements.body);
            }

            return elements;
        }
        
        _populatePostViewModal(postData, modalElements) {
            if (!postData || !modalElements || !modalElements.body || !modalElements.title) {
                console.error("NM: Missing data or modal elements for populating post view.");
                if (modalElements && modalElements.body) {
                    modalElements.body.innerHTML = '<div class="p-4"><p class="text-red-500 text-center">Error: Could not load post details.</p></div>';
                }
                return;
            }

            const postOwnerId = postData.user_id || (postData.user ? postData.user.id : 'unknown');
            const authorName = Utils.sanitizeText(postData.full_name || postData.username || 'User');
            const authorAvatar = postData.user_avatar || Utils.generateFallbackSVG(authorName, 40);
            const profileLink = `/profile/${postOwnerId}`;
            const timestamp = postData.created_at || new Date().toISOString();
            const timeAgo = Utils.timeAgo(timestamp);
            const visibility = Utils.sanitizeText(postData.visibility || 'public');
            const visibilityIcon = this._getIconForVisibility(visibility);

            modalElements.title.textContent = `Post by ${authorName}`;

            let mainPostContentHTML = Utils.sanitizeText(postData.content || '', true);
            let mediaDisplayHTML = '';
            if (postData.image) {
                mediaDisplayHTML = `<img src="${Utils.sanitizeText(postData.image)}" alt="Post media" class="post-media-display rounded-lg max-h-[400px] w-full object-contain my-2 mx-auto block">`;
            }

            let sharedContentEmbedHTML = '';
            if (postData.post_type === 'share' && postData.original_post) {
                const original = postData.original_post;
                const originalAuthorName = Utils.sanitizeText(original.full_name || original.username || 'User');
                const originalAuthorAvatar = original.user_avatar || Utils.generateFallbackSVG(originalAuthorName, 32);
                let originalContentPreview = Utils.sanitizeText(original.content, true).substring(0, 150) + (original.content.length > 150 ? '...' : '');
                let originalMediaPreview = '';
                if (original.image) {
                    originalMediaPreview = `<img src="${Utils.sanitizeText(original.image)}" alt="Shared media preview" class="rounded max-h-[100px] object-contain my-1 border dark:border-dark-500">`;
                }

                sharedContentEmbedHTML = `
                    <div class="original-shared-post-embed-modal border dark:border-dark-600 p-3 mt-3 rounded-md bg-gray-50 dark:bg-dark-700">
                        <div class="flex items-center mb-2">
                            <img src="${originalAuthorAvatar}" alt="${originalAuthorName}" class="w-8 h-8 rounded-full mr-2 object-cover flex-shrink-0">
                            <div>
                                <a href="/profile/${original.user_id}" class="font-semibold text-sm dark:text-white hover:underline">${originalAuthorName}</a>
                                <p class="text-xs text-gray-500 dark:text-gray-400">${Utils.timeAgo(original.created_at)} · Original post</p>
                            </div>
                        </div>
                        ${originalContentPreview ? `<div class="text-sm dark:text-gray-300 whitespace-pre-wrap">${originalContentPreview}</div>` : ''}
                        ${originalMediaPreview}
                    </div>`;
            }

            let optionsDropdownHTML = '';
            if (this.currentUserId && postOwnerId && parseInt(this.currentUserId) === parseInt(postOwnerId)) {
                optionsDropdownHTML = `
                <div class="relative post-options-dropdown-container">
                    <button class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-full p-2 post-options-trigger" aria-label="Post options" data-post-id="${postData.id}">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div class="post-options-menu absolute right-0 mt-1 w-48 bg-white dark:bg-dark-700 rounded-md shadow-lg py-1 z-20 hidden" data-menu-for-post="${postData.id}">
                        <button class="edit-post-button w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600 flex items-center" data-post-id="${postData.id}" data-post-type="${postData.post_type || 'text'}">
                            <i class="fas fa-edit mr-2 w-4 text-center"></i>Edit Post
                        </button>
                        <button class="delete-post-button w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-700 dark:hover:text-white flex items-center" data-post-id="${postData.id}">
                            <i class="fas fa-trash-alt mr-2 w-4 text-center"></i>Delete Post
                        </button>
                    </div>
                </div>`;
            }

            const dynamicPostItemHTML = `
            <div class="post-item bg-white dark:bg-dark-700 shadow fade-in" data-post-id="${postData.id}" data-post-owner-id="${postOwnerId}">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <img src="${authorAvatar}" alt="${authorName}" class="w-10 h-10 rounded-full mr-3 object-cover flex-shrink-0">
                            <div>
                                <a href="${profileLink}" class="font-semibold dark:text-white hover:underline">${authorName}</a>
                                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                    <span class="post-timeago" data-timestamp="${timestamp}">${timeAgo}</span>
                                    <span class="mx-1">·</span>
                                    <i class="fas ${visibilityIcon} post-visibility-icon" title="${visibility}"></i>
                                </p>
                            </div>
                        </div>
                        ${optionsDropdownHTML}
                    </div>

                    ${(mainPostContentHTML.trim() !== '') ? `<div class="post-content-display mb-3 dark:text-gray-200 whitespace-pre-wrap">${mainPostContentHTML}</div>` : ''}
                    
                    ${sharedContentEmbedHTML}
                    
                    ${mediaDisplayHTML ? `<div class="post-media-container my-2">${mediaDisplayHTML}</div>` : ''}
                    
                    <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 mb-2 mt-3">
                        <div><i class="fas ${postData.is_liked_by_current_user ? 'fa-solid text-facebook dark:text-blue-400' : 'fa-thumbs-up text-facebook dark:text-blue-400'}  mr-1"></i><span class="like-count-display">${postData.like_count || 0}</span></div>
                        <div><span class="comment-count-display-text hover:underline cursor-pointer" data-post-id="${postData.id}">${postData.comment_count || 0} comment${(postData.comment_count || 0) !== 1 ? 's' : ''}</span></div>
                    </div>
                </div>
                
                <div class="post-actions flex justify-around border-t border-gray-200 dark:border-dark-600">
                    <button class="like-button flex-1 flex items-center justify-center py-2 px-3 ${postData.is_liked_by_current_user ? 'text-facebook dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-400'} hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${postData.id}"><i class="fas fa-thumbs-up mr-2"></i> Like</button>
                    <button class="comment-action-button flex-1 flex items-center justify-center py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${postData.id}"><i class="fas fa-comment-alt mr-2"></i> Comment</button>
                    <button class="share-button flex-1 flex items-center justify-center py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${postData.id}"><i class="fas fa-share mr-2"></i> Share</button>
                </div>
                
                <div class="comment-input-section p-3 border-t border-gray-200 dark:border-dark-600 hidden">
                    <div class="flex items-start space-x-2">
                        <img src="${this.currentUserAvatar}" alt="Your avatar" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                        <textarea class="comment-input flex-1 p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none dark:bg-dark-600 dark:text-white" rows="1" placeholder="Write a comment..."></textarea>
                        <button class="comment-submit-button bg-facebook hover:bg-facebook-dark text-white font-semibold py-2 px-3 rounded-lg text-sm ml-2" data-post-id="${postData.id}"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>
                
                <div class="view-comments-trigger p-3 pt-1 ${(postData.comment_count || 0) === 0 ? 'hidden' : ''}">
                    <button class="view-comments-button text-sm text-gray-600 dark:text-gray-400 hover:underline" data-post-id="${postData.id}" data-original-text="View ${postData.comment_count || 0} comment${(postData.comment_count || 0) !== 1 ? 's' : ''}">View ${postData.comment_count || 0} comment${(postData.comment_count || 0) !== 1 ? 's' : ''}</button>
                </div>
                
                <div class="comments-list-area p-3 pt-0 space-y-2"></div>
            </div>`;

            modalElements.body.innerHTML = dynamicPostItemHTML;
        }

        _bindModalContentEventListeners(modalBodyElement) {
            if (modalBodyElement._modalContentListenersAttached) {
                return;
            }

            modalBodyElement.addEventListener('click', async (event) => {
                const target = event.target;
                const viewCommentsButton = target.closest('.view-comments-button');
                const loadMoreCommentsButton = target.closest('.load-more-comments-button');
                const commentSubmitButton = target.closest('.comment-submit-button');
                const likeButton = target.closest('.like-button');
                const commentActionButton = target.closest('.comment-action-button');
                const shareButton = target.closest('.share-button');
                const deletePostButton = target.closest('.delete-post-button');
                const postOptionsTrigger = target.closest('.post-options-trigger');
                const editCommentButton = target.closest('.edit-comment-button');
                const deleteCommentButton = target.closest('.delete-comment-button');

                if (viewCommentsButton) { event.preventDefault(); this._handleViewComments(viewCommentsButton); }
                else if (loadMoreCommentsButton) { event.preventDefault(); this._handleLoadMoreComments(loadMoreCommentsButton); }
                else if (commentSubmitButton) { event.preventDefault(); this._handleSubmitComment(commentSubmitButton); }
                else if (likeButton) { event.preventDefault(); this._handleToggleLike(likeButton); }
                else if (commentActionButton) { event.preventDefault(); this._handleCommentButtonClick(commentActionButton); }
                else if (shareButton) { event.preventDefault(); this._handleSharePost(shareButton); }
                else if (editCommentButton) { event.preventDefault(); this._handleEditComment(editCommentButton); }
                else if (deleteCommentButton) { event.preventDefault(); this._handleDeleteComment(deleteCommentButton); }
                else if (postOptionsTrigger) {
                    event.preventDefault();
                    event.stopPropagation();
                    const postId = postOptionsTrigger.dataset.postId;
                    const menu = modalBodyElement.querySelector(`.post-options-menu[data-menu-for-post="${postId}"]`);
                    if (menu) {
                        modalBodyElement.querySelectorAll('.post-options-menu:not(.hidden)').forEach(otherMenu => {
                            if (otherMenu !== menu) otherMenu.classList.add('hidden');
                        });
                        menu.classList.toggle('hidden');
                    }
                } else if (deletePostButton) {
                    event.preventDefault();
                    const postId = deletePostButton.dataset.postId;
                    const postItem = deletePostButton.closest('.post-item');
                    const success = await this._handleDeletePost(postId, postItem);
                    if (success) {
                        this._closePostViewModal();
                    }
                }
            });

            modalBodyElement.addEventListener('keypress', (event) => {
                if (event.key === 'Enter' && !event.shiftKey && event.target.classList.contains('comment-input')) {
                    event.preventDefault();
                    const postItem = event.target.closest('.post-item');
                    const submitButton = postItem?.querySelector('.comment-submit-button');
                    if (submitButton) this._handleSubmitComment(submitButton, event.target);
                }
            });
            
             modalBodyElement.addEventListener('keydown', (event) => {
                if (event.target.classList.contains('comment-edit-input')) {
                    const commentItem = event.target.closest('.comment-item');
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        const saveButton = commentItem?.querySelector('.comment-edit-save-button');
                        if (saveButton && !saveButton.disabled) saveButton.click();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        const cancelButton = commentItem?.querySelector('.comment-edit-cancel-button');
                        if (cancelButton) cancelButton.click();
                    }
                }
            });

            const closeOptionsDropdown = (event) => {
                if (!event.target.closest('.post-options-dropdown-container') && modalBodyElement.contains(event.target)) {
                    modalBodyElement.querySelectorAll('.post-options-menu:not(.hidden)').forEach(menu => {
                        menu.classList.add('hidden');
                    });
                } else if (!modalBodyElement.contains(event.target) && !event.target.closest('.post-options-dropdown-container')) {
                    modalBodyElement.querySelectorAll('.post-options-menu:not(.hidden)').forEach(menu => {
                        menu.classList.add('hidden');
                    });
                }
            };

            if (modalBodyElement._globalDropdownClickListener) {
                document.removeEventListener('click', modalBodyElement._globalDropdownClickListener);
            }
            document.addEventListener('click', closeOptionsDropdown);
            modalBodyElement._globalDropdownClickListener = closeOptionsDropdown;
            modalBodyElement._modalContentListenersAttached = true;
        }

        async _handleLoadMoreComments(buttonElement) {
            const postId = buttonElement.dataset.postId;
            let currentPage = parseInt(buttonElement.dataset.currentPage) || 1;
            const postElement = buttonElement.closest('.post-item');
            const commentsListArea = postElement.querySelector('.comments-list-area');
            if (!postId || !commentsListArea) return;

            buttonElement.innerHTML = '<div class="loading-spinner w-4 h-4 inline-block mr-1"></div> Loading more...';
            buttonElement.disabled = true;
            
            await this._fetchAndDisplayComments(postId, currentPage + 1, commentsListArea, buttonElement, true);
        }

        _openPostViewModal(modalElements) {
            if (modalElements && modalElements.modal) {
                modalElements.modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                if (modalElements.closeBtn) modalElements.closeBtn.focus();
            }
        }

        _closePostViewModal() {
            const modalElements = this._getPostViewModalElements();
            if (modalElements && modalElements.modal && !modalElements.modal.classList.contains('hidden')) {
                modalElements.modal.classList.add('hidden');
                document.body.style.overflow = '';
                if (modalElements.body && modalElements.body._globalDropdownClickListener) {
                    document.removeEventListener('click', modalElements.body._globalDropdownClickListener);
                    delete modalElements.body._globalDropdownClickListener;
                }
                if (modalElements.body) {
                    modalElements.body.innerHTML = this._getStaticInitialModalBodyContent();
                }
                if (modalElements.title) {
                    modalElements.title.textContent = 'Post Details';
                }
            }
        }

        _getStaticInitialModalBodyContent() {
            return `
            <div class="post-item bg-white dark:bg-dark-800 rounded-lg shadow-md">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full mr-3 bg-gray-200 dark:bg-dark-600 flex-shrink-0 animate-pulse"></div>
                            <div>
                                <div class="h-4 bg-gray-200 dark:bg-dark-600 rounded w-32 mb-1 animate-pulse"></div>
                                <div class="h-3 bg-gray-200 dark:bg-dark-600 rounded w-24 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="text-gray-400 dark:text-dark-500 animate-pulse"><i class="fas fa-ellipsis-h"></i></div>
                    </div>
                    <div class="h-8 bg-gray-200 dark:bg-dark-600 rounded w-full mb-3 animate-pulse"></div>
                    <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 mb-2">
                        <div class="flex items-center animate-pulse"><i class="fas fa-thumbs-up text-gray-300 dark:text-dark-500 mr-1"></i><span class="like-count-display h-3 bg-gray-200 dark:bg-dark-600 rounded w-4"></span></div>
                        <div class="animate-pulse"><span class="comment-count-display-text h-3 bg-gray-200 dark:bg-dark-600 rounded w-16"></span></div>
                    </div>
                </div>
                <div class="post-actions flex justify-around border-t border-gray-200 dark:border-dark-600 opacity-50 animate-pulse">
                    <div class="flex-1 flex items-center justify-center py-2 px-3 text-gray-500 dark:text-dark-400"><i class="fas fa-thumbs-up mr-2"></i> Like</div>
                    <div class="flex-1 flex items-center justify-center py-2 px-3 text-gray-500 dark:text-dark-400"><i class="fas fa-comment-alt mr-2"></i> Comment</div>
                    <div class="flex-1 flex items-center justify-center py-2 px-3 text-gray-500 dark:text-dark-400"><i class="fas fa-share mr-2"></i> Share</div>
                </div>
            </div>`;
        }

        async _handleViewPostInModal(postId) {
            const modalElements = this._createPostViewModalStructure();
            if (!modalElements || !modalElements.body || !modalElements.title) {
                // You should implement a proper alert/modal system instead of window.alert
                alert('Error: Could not prepare modal to show post.');
                return;
            }

            modalElements.title.textContent = 'Loading Post...';
            modalElements.body.innerHTML = this._getStaticInitialModalBodyContent();
            this._openPostViewModal(modalElements);

            try {
                const response = await fetch(CONFIG.apiEndpoints.MODAL_POST_DATA(postId));
                if (!response.ok) {
                    let errorMsg = `HTTP error ${response.status}`;
                    try { const errorData = await response.json(); errorMsg = errorData.message || errorMsg; } catch (e) { errorMsg = response.statusText || errorMsg; }
                    throw new Error(errorMsg);
                }
                const result = await response.json();
                if (result.success && result.post) {
                    this._populatePostViewModal(result.post, modalElements);
                } else {
                    throw new Error(result.message || "Post data not found.");
                }
            } catch (error) {
                console.error("Error fetching post in modal (ID: " + postId + "):", error);
                if (modalElements.body) modalElements.body.innerHTML = `<p class="text-red-500 p-4 text-center">Error: ${Utils.sanitizeText(error.message)}</p>`;
                if (modalElements.title) modalElements.title.textContent = 'Error Loading Post';
            }
        }
        
        _getIconForVisibility(visibility) {
            switch (visibility) {
                case 'public': return 'fa-globe-americas';
                case 'friends': return 'fa-user-friends';
                case 'private': return 'fa-lock';
                default: return 'fa-globe-americas';
            }
        }
        
        async _handleToggleLike(buttonElement) {
            const postId = buttonElement.dataset.postId;
            if (!postId) return;
            const postElement = buttonElement.closest('.post-item');
            const likeCountSpan = postElement.querySelector('.like-count-display');
            const currentlyLiked = buttonElement.classList.contains('text-facebook');
            
            buttonElement.classList.toggle('text-facebook', !currentlyLiked);
            buttonElement.classList.toggle('dark:text-blue-400', !currentlyLiked);
            buttonElement.classList.toggle('text-gray-600', currentlyLiked);
            buttonElement.classList.toggle('dark:text-gray-400', currentlyLiked);
            let currentNumericCount = parseInt(likeCountSpan.textContent) || 0;
            if (likeCountSpan) likeCountSpan.textContent = currentlyLiked ? Math.max(0, currentNumericCount - 1) : currentNumericCount + 1;
            
            buttonElement.disabled = true;
            try {
                const formData = new FormData();
                formData.append('post_id', postId);
                const response = await fetch(CONFIG.apiEndpoints.TOGGLE_POST_LIKE, { method: 'POST', body: formData, headers: this._getAjaxHeaders() });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.error || 'Failed to toggle like.');
                buttonElement.classList.toggle('text-facebook', result.isLiked);
                buttonElement.classList.toggle('dark:text-blue-400', result.isLiked);
                buttonElement.classList.toggle('text-gray-600', !result.isLiked);
                buttonElement.classList.toggle('dark:text-gray-400', !result.isLiked);
                if (likeCountSpan) likeCountSpan.textContent = result.likeCount;
            } catch (error) {
                buttonElement.classList.toggle('text-facebook', currentlyLiked);
                buttonElement.classList.toggle('dark:text-blue-400', currentlyLiked);
                buttonElement.classList.toggle('text-gray-600', !currentlyLiked);
                buttonElement.classList.toggle('dark:text-gray-400', !currentlyLiked);
                if (likeCountSpan) likeCountSpan.textContent = currentNumericCount;
            } finally {
                buttonElement.disabled = false;
            }
        }
        
        _handleCommentButtonClick(buttonElement) {
            const postElement = buttonElement.closest('.post-item');
            if (!postElement) return;
            const commentInputSection = postElement.querySelector('.comment-input-section');
            const commentInput = postElement.querySelector('.comment-input');
            if (commentInputSection) {
                commentInputSection.classList.toggle('hidden');
                if (!commentInputSection.classList.contains('hidden') && commentInput) commentInput.focus();
            }
        }
        
        async _handleSubmitComment(submitButtonElement, inputElement = null) {
            const postId = submitButtonElement.dataset.postId;
            const postElement = submitButtonElement.closest('.post-item');
            if (!postElement || !postId) return;
            const commentInputElement = inputElement || postElement.querySelector('.comment-input');
            const content = commentInputElement.value.trim();
            if (!content) return;
            
            const originalButtonText = submitButtonElement.innerHTML;
            commentInputElement.disabled = true;
            submitButtonElement.disabled = true;
            submitButtonElement.innerHTML = '<div class="loading-spinner w-4 h-4 mx-auto"></div>';
            try {
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', content);
                const response = await fetch(CONFIG.apiEndpoints.ADD_POST_COMMENT, { method: 'POST', body: formData, headers: this._getAjaxHeaders() });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.error || 'Failed to post comment.');
                
                commentInputElement.value = '';
                if (result.comment) this._appendSingleCommentToUI(postElement, result.comment, true);
                
                const commentsListArea = postElement.querySelector('.comments-list-area');
                if(commentsListArea) {
                    commentsListArea.classList.add('comments-loaded');
                }

                const newCommentCount = typeof result.commentCount !== 'undefined' ? parseInt(result.commentCount, 10) : this._getUpdatedCommentCountFallback(postElement, 1);
                this._updatePostCommentCountsUI(postElement, newCommentCount);

            } catch (error) {
                console.error("NM: Error submitting comment:", error);
            } finally {
                commentInputElement.disabled = false;
                submitButtonElement.disabled = false;
                submitButtonElement.innerHTML = originalButtonText;
            }
        }
        
        _appendSingleCommentToUI(postElement, commentData, prependNew = false) {
            if (!postElement || !commentData) return;
            let commentsListArea = postElement.querySelector('.comments-list-area');
            if (!commentsListArea) return;
            commentsListArea.classList.remove('hidden');

            const postOwnerId = postElement.dataset.postOwnerId;
            const commentDiv = this._createCommentElement(commentData, postOwnerId);
            if (commentDiv) {
                if (prependNew) commentsListArea.insertBefore(commentDiv, commentsListArea.firstChild);
                else commentsListArea.appendChild(commentDiv);
            }
        }
        
        _createCommentElement(commentData, postOwnerId = null) {
            const commentDiv = DOM.create('div', { className: 'comment-item group flex items-start space-x-2 text-sm py-1', 'data-comment-id': commentData.id });
            const avatar = commentData.user_avatar_fallback || Utils.generateFallbackSVG(commentData.user_full_name || commentData.username, 24);
            const userName = Utils.sanitizeText(commentData.user_full_name || commentData.username || 'User');
            const commentContent = Utils.sanitizeText(commentData.content, true);
            const isCommentAuthor = this.currentUserId && commentData.user_id && parseInt(this.currentUserId) === parseInt(commentData.user_id);

            let actionsHTML = '';
            if (isCommentAuthor) {
                actionsHTML += `<button class="edit-comment-button text-xs text-gray-400 hover:text-blue-500" data-comment-id="${commentData.id}"><i class="fas fa-pencil-alt"></i></button>`;
                actionsHTML += `<button class="delete-comment-button text-xs text-gray-400 hover:text-red-500 ml-2" data-comment-id="${commentData.id}"><i class="fas fa-trash-alt"></i></button>`;
            }

            commentDiv.innerHTML = `
                <img src="${avatar}" alt="${userName}" class="w-6 h-6 rounded-full object-cover mt-1 flex-shrink-0">
                <div class="comment-content-bubble flex-1 bg-gray-100 dark:bg-dark-600 p-2 rounded-lg relative">
                    <div class="flex justify-between items-start">
                        <a href="/profile/${commentData.user_id}" class="font-semibold dark:text-white hover:underline">${userName}</a>
                        <div class="comment-actions flex items-center space-x-1 ml-auto opacity-0 group-hover:opacity-100">${actionsHTML}</div>
                    </div>
                    <p class="comment-text-display dark:text-gray-300 whitespace-pre-wrap">${commentContent}</p>
                    <div class="comment-edit-area hidden mt-1"></div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${Utils.timeAgo(commentData.created_at)}</p>
                </div>`;
            return commentDiv;
        }

        async _handleViewComments(buttonElement) {
            const postId = buttonElement.dataset.postId;
            const postElement = buttonElement.closest('.post-item');
            if (!postElement || !postId) return;
            let commentsListArea = postElement.querySelector('.comments-list-area');
            if (!commentsListArea) return;

            const isLoaded = commentsListArea.classList.contains('comments-loaded');
            const isHidden = commentsListArea.classList.contains('hidden');

            const loadMoreButton = postElement.querySelector('.load-more-comments-button');

            if (isLoaded && !isHidden) {
                commentsListArea.classList.add('hidden');
                if (loadMoreButton) loadMoreButton.classList.add('hidden');
                buttonElement.textContent = buttonElement.dataset.originalText || `View comments`;
            } else if (isLoaded && isHidden) {
                commentsListArea.classList.remove('hidden');
                if (loadMoreButton) loadMoreButton.classList.remove('hidden');
                buttonElement.textContent = 'Hide comments';
            } else {
                if (!buttonElement.dataset.originalText) {
                    buttonElement.dataset.originalText = buttonElement.textContent;
                }
                buttonElement.innerHTML = '<div class="loading-spinner w-4 h-4 inline-block mr-1"></div> Loading...';
                buttonElement.disabled = true;
                
                await this._fetchAndDisplayComments(postId, 1, commentsListArea, buttonElement, false);
            }
        }
        
        async _fetchAndDisplayComments(postId, page = 1, commentsListArea, triggerButton = null, isLoadMore = false) {
            let resultData = null;
            try {
                const response = await fetch(CONFIG.apiEndpoints.POST_COMMENTS(postId, page), { headers: this._getAjaxHeaders() });
                if (!response.ok) {
                    const errData = await response.json().catch(() => ({ error: 'Failed to load comments.' }));
                    throw new Error(errData.error);
                }
                resultData = await response.json();

                if (resultData.success && Array.isArray(resultData.comments)) {
                    if (!isLoadMore) {
                        commentsListArea.innerHTML = '';
                    }

                    if (resultData.comments.length === 0 && !isLoadMore) {
                        commentsListArea.innerHTML = `<p class="text-xs text-gray-500 dark:text-gray-400 p-2">No comments to show.</p>`;
                    } else {
                        resultData.comments.forEach(commentData => {
                            this._appendSingleCommentToUI(commentsListArea.closest('.post-item'), commentData, false); 
                        });
                    }
                    
                    const existingLoadMoreButton = commentsListArea.parentElement.querySelector('.load-more-comments-button');
                    if (existingLoadMoreButton) {
                        existingLoadMoreButton.remove();
                    }

                    if (resultData.pagination && resultData.pagination.current_page < resultData.pagination.total_pages) {
                        const loadMoreContainer = DOM.create('div', {
                            className: 'load-more-comments-container px-4 pb-3 flex justify-end' 
                        });
                        
                        const loadMoreButton = DOM.create('button', {
                            className: 'load-more-comments-button text-sm text-blue-500 hover:underline dark:text-blue-400',
                            textContent: 'Load more comments',
                            'data-post-id': postId,
                            'data-current-page': String(resultData.pagination.current_page)
                        });

                        loadMoreContainer.appendChild(loadMoreButton);
                        commentsListArea.insertAdjacentElement('afterend', loadMoreContainer);
                    }

                    if (!isLoadMore) {
                        commentsListArea.classList.add('comments-loaded');
                        commentsListArea.classList.remove('hidden');
                    }
                } else {
                    throw new Error(resultData.error || 'No comments found or error in response.');
                }
            } catch (error) {
                console.error(`Error fetching comments for post ${postId}:`, error);
                if (!isLoadMore) {
                    commentsListArea.innerHTML = `<p class="text-xs text-red-500 p-2">Error: ${error.message}</p>`;
                }
            } finally {
                if (triggerButton) {
                    triggerButton.disabled = false;
                    if (isLoadMore) {
                        triggerButton.parentElement.remove(); // Remove the container of the "loading more" button
                    } else {
                        triggerButton.textContent = 'Hide comments';
                    }
                }
            }
        }
        
        async _handleDeleteComment(buttonElement) {
            const commentId = buttonElement.dataset.commentId;
            const commentItemElement = buttonElement.closest('.comment-item');
            const postElement = buttonElement.closest('.post-item');
            if (!commentId || !commentItemElement || !postElement) return;

            const confirmed = window.confirm('Are you sure you want to delete this comment?');
            if (!confirmed) return;

            try {
                const response = await fetch(CONFIG.apiEndpoints.DELETE_COMMENT(commentId), { method: 'POST', headers: this._getAjaxHeaders() });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.error || 'Failed to delete comment.');
                
                commentItemElement.remove();
                const newCount = typeof result.new_comment_count !== 'undefined' ? parseInt(result.new_comment_count) : this._getUpdatedCommentCountFallback(postElement, -1);
                this._updatePostCommentCountsUI(postElement, newCount);
            } catch (error) {
                 alert(`Error: ${error.message}`);
            }
        }

        async _handleEditComment(editButton) {
            const commentItemElement = editButton.closest('.comment-item');
            if (!commentItemElement) return;
            const commentId = commentItemElement.dataset.commentId;
            if (this.editingCommentId === commentId) return;
            if (this.editingCommentId) {
                const otherEditingComment = document.querySelector(`.comment-item[data-comment-id="${this.editingCommentId}"] .comment-edit-cancel-button`);
                if (otherEditingComment) otherEditingComment.click();
            }
            this.editingCommentId = commentId;

            const commentTextDisplay = commentItemElement.querySelector('.comment-text-display');
            const commentEditArea = commentItemElement.querySelector('.comment-edit-area');
            const originalContent = commentTextDisplay.textContent;

            commentTextDisplay.classList.add('hidden');
            commentEditArea.classList.remove('hidden');
            commentEditArea.innerHTML = `
                <textarea class="comment-edit-input w-full p-2 border rounded-md dark:bg-dark-500" rows="2">${originalContent}</textarea>
                <div class="text-xs mt-1">
                    <button class="comment-edit-save-button text-blue-500">Save</button>
                    <button class="comment-edit-cancel-button text-gray-500 ml-2">Cancel</button>
                </div>`;

            const editInput = commentEditArea.querySelector('.comment-edit-input');
            editInput.focus();

            const closeEditUI = () => {
                commentEditArea.innerHTML = '';
                commentEditArea.classList.add('hidden');
                commentTextDisplay.classList.remove('hidden');
                this.editingCommentId = null;
            };

            commentEditArea.querySelector('.comment-edit-save-button').onclick = async () => {
                const newContent = editInput.value.trim();
                if (!newContent || newContent === originalContent) {
                    closeEditUI();
                    return;
                }
                
                const formData = new FormData();
                formData.append('content', newContent);
                try {
                    const response = await fetch(CONFIG.apiEndpoints.EDIT_COMMENT(commentId), { method: 'POST', body: formData, headers: this._getAjaxHeaders() });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Failed to update comment.');
                    commentTextDisplay.innerHTML = Utils.sanitizeText(result.comment.content, true);
                    closeEditUI();
                } catch(error) {
                    alert(`Error: ${error.message}`);
                }
            };

            commentEditArea.querySelector('.comment-edit-cancel-button').onclick = closeEditUI;
        }

        _handleSharePost(buttonElement) {
            const postIdToShare = buttonElement.dataset.postId;
            if (!postIdToShare) return;
            if (window.SmartFed && typeof window.SmartFed.openPostModal === 'function') {
                window.SmartFed.openPostModal({ isSharing: true, originalPostId: postIdToShare });
            } else {
                alert('Share functionality is currently unavailable.');
            }
        }
        
        _getUpdatedCommentCountFallback(postElement, change) {
            const span = postElement.querySelector('.comment-count-display-text');
            const match = span ? span.textContent.match(/(\d+)/) : null;
            return Math.max(0, (match ? parseInt(match[0]) : 0) + change);
        }

        _updatePostCommentCountsUI(postElement, newCommentCount) {
            const textSpan = postElement.querySelector('.comment-count-display-text');
            const triggerDiv = postElement.querySelector('.view-comments-trigger');
            const button = postElement.querySelector('.view-comments-button');
            const plural = newCommentCount !== 1 ? 's' : '';
            if (textSpan) textSpan.textContent = `${newCommentCount} comment${plural}`;
            if (triggerDiv) triggerDiv.classList.toggle('hidden', newCommentCount === 0);
            if (button) button.dataset.originalText = `View ${newCommentCount} comment${plural}`;
        }
        
        displayLoggedOutState() {
            if (!this.isInitialized) return;
            this.updateUserData({ id: null, fullName: 'Guest' });
            this._setInitialUIState();
            DOM.hide(this.elements.notificationLoadingState);
            DOM.setText(this.elements.notificationEmptyState, CONFIG.text.loginToSeeNotifications);
            DOM.show(this.elements.notificationEmptyState);
            DOM.hide(this.elements.markAllNotificationsReadBtn);
            DOM.hide(this.elements.notificationUnreadBadge);
            DOM.hide(this.elements.notificationViewAllContainer);
            this.closeNotificationModal();
            this._closePostViewModal();
        }

        displayErrorState(message = CONFIG.text.couldNotLoadNotifications) {
            if (!this.isInitialized) return;
            this._setInitialUIState();
            DOM.hide(this.elements.notificationLoadingState);
            DOM.setText(this.elements.notificationEmptyState, message);
            DOM.show(this.elements.notificationEmptyState);
            DOM.hide(this.elements.notificationViewAllContainer);
            this.closeNotificationModal();
            this._closePostViewModal();
        }
    }

    window.NotificationManager = NotificationManager;

})(window, document);