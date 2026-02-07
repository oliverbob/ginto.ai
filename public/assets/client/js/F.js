// F-core.js (or your chosen name)
(function(window, document, undefined) {
    'use strict';

    // ========================================================================
    // F CORE: The main function and prototype
    // ========================================================================
    const F = function(selector, context) {
        if (typeof selector === 'function') {
            return F.ready(selector); // DOM ready shortcut like $(function(){})
        }
        return new F.fn.init(selector, context);
    };

    F.fn = F.prototype = {
        constructor: F,
        version: '1.0.0',
        length: 0, // Makes it array-like

        init: function(selector, context) {
            if (!selector) {
                return this;
            }

            if (typeof selector === 'string') {
                const elements = (context || document).querySelectorAll(selector);
                for (let i = 0; i < elements.length; i++) {
                    this[i] = elements[i];
                }
                this.length = elements.length;
            } else if (selector.nodeType || selector === window) { // Single DOM element or window
                this[0] = selector;
                this.length = 1;
            } else if (Array.isArray(selector) && selector.every(el => el.nodeType)) { // Array of DOM elements
                for (let i = 0; i < selector.length; i++) {
                    this[i] = selector[i];
                }
                this.length = selector.length;
            } else if (selector instanceof F) { // F instance
                return selector;
            }
            return this;
        },

        each: function(callback) {
            for (let i = 0; i < this.length; i++) {
                if (callback.call(this[i], i, this[i]) === false) {
                    break;
                }
            }
            return this;
        },

        addClass: function(className) {
            return this.each(function() { this.classList.add(className); });
        },
        removeClass: function(className) {
            return this.each(function() { this.classList.remove(className); });
        },
        toggleClass: function(className, state) {
            return this.each(function() { this.classList.toggle(className, state); });
        },
        hasClass: function(className) {
            return this.length > 0 && this[0].classList.contains(className);
        },
        hide: function() {
            return this.each(function() { this.style.display = 'none'; });
        },
        show: function() {
            return this.each(function() { this.style.display = ''; });
        },
        on: function(eventType, selector, handler) {
            if (typeof selector === 'function') {
                handler = selector;
                selector = null;
            }
            return this.each(function(i, el) {
                const eventListener = function(event) {
                    if (selector) {
                        if (event.target.closest(selector)) {
                            handler.call(event.target.closest(selector), event);
                        }
                    } else {
                        handler.call(el, event);
                    }
                };
                el.addEventListener(eventType, eventListener);
                if (!el._FListeners) el._FListeners = {};
                if (!el._FListeners[eventType]) el._FListeners[eventType] = [];
                el._FListeners[eventType].push({original: handler, delegated: selector, actual: eventListener});
            });
        },
        off: function(eventType, handler) { // Improved .off()
            return this.each(function(i, el) {
                if (el._FListeners && el._FListeners[eventType]) {
                    el._FListeners[eventType] = el._FListeners[eventType].filter(listenerObj => {
                        if (!handler || listenerObj.original === handler) {
                            el.removeEventListener(eventType, listenerObj.actual);
                            return false; // Remove from array
                        }
                        return true; // Keep in array
                    });
                    if (el._FListeners[eventType].length === 0) {
                        delete el._FListeners[eventType];
                    }
                }
            });
        },
        attr: function(attrName, value) {
            if (value === undefined && this.length > 0) {
                return this[0].getAttribute(attrName);
            }
            return this.each(function() {
                this.setAttribute(attrName, value);
            });
        },
        data: function(dataName, value) {
            const actualDataName = dataName.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
            if (value === undefined && this.length > 0) {
                return this[0].dataset[actualDataName];
            }
            return this.each(function() {
                this.dataset[actualDataName] = value;
            });
        },
        text: function(newText) {
            if (newText === undefined && this.length > 0) {
                return this[0].textContent;
            }
            return this.each(function() {
                this.textContent = newText;
            });
        },
        html: function(newHtml) {
            if (newHtml === undefined && this.length > 0) {
                return this[0].innerHTML;
            }
            return this.each(function() {
                this.innerHTML = newHtml;
            });
        },
        append: function(content) {
            return this.each(function() {
                if (typeof content === 'string') {
                    this.insertAdjacentHTML('beforeend', content);
                } else if (content.nodeType) {
                    this.appendChild(content);
                } else if (content instanceof F) {
                    content.each(el => this.appendChild(el.cloneNode(true)));
                }
            });
        },
        remove: function() {
            return this.each(function() {
                if (this.parentNode) {
                    this.parentNode.removeChild(this);
                }
            });
        },
        empty: function() {
            return this.html('');
        },
        find: function(selector) {
            if (this.length === 0) return F();
            const resultElements = [];
            this.each(function() {
                this.querySelectorAll(selector).forEach(el => resultElements.push(el));
            });
            return F(resultElements);
        },
        children: function(selector) {
            if (this.length === 0) return F();
            const resultElements = [];
            this.each(function() {
                Array.from(this.children).forEach(child => {
                    if (!selector || child.matches(selector)) {
                        resultElements.push(child);
                    }
                });
            });
            return F(resultElements);
        },
        parent: function(selector) {
            if (this.length === 0) return F();
            const resultElements = [];
            this.each(function() {
                const parentEl = this.parentElement;
                if (parentEl && (!selector || parentEl.matches(selector))) {
                    if (!resultElements.includes(parentEl)) {
                         resultElements.push(parentEl);
                    }
                }
            });
            return F(resultElements);
        },
        closest: function(selector) {
            if (this.length === 0) return F();
            const resultElements = [];
            this.each(function() {
                const closestEl = this.closest(selector);
                if (closestEl && !resultElements.includes(closestEl)) {
                    resultElements.push(closestEl);
                }
            });
            return F(resultElements);
        },
        val: function(value) {
            if (value === undefined) {
                return this.length > 0 ? this[0].value : undefined;
            }
            return this.each(function() { this.value = value; });
        },
        css: function(property, value) {
            if (typeof property === 'string' && value === undefined && this.length > 0) {
                return getComputedStyle(this[0])[property];
            }
            const applyCss = (el, prop, val) => {
                el.style[prop.replace(/-([a-z])/g, (g) => g[1].toUpperCase())] = val;
            };
            if (typeof property === 'object') {
                return this.each(function() {
                    for (const prop in property) {
                        applyCss(this, prop, property[prop]);
                    }
                });
            }
            return this.each(function() {
                applyCss(this, property, value);
            });
        }
    };
    F.fn.init.prototype = F.fn;

    // ========================================================================
    // F STATIC PROPERTIES & UTILITIES
    // ========================================================================

    F.isPlainObject = function(obj) {
        if (typeof obj !== 'object' || obj === null) return false;
        let proto = obj;
        while (Object.getPrototypeOf(proto) !== null) {
            proto = Object.getPrototypeOf(proto);
        }
        return Object.getPrototypeOf(obj) === proto;
    };

    F.extend = function() {
        let options, name, src, copy, copyIsArray, clone,
            target = arguments[0] || {},
            i = 1,
            length = arguments.length,
            deep = false;

        if (typeof target === "boolean") {
            deep = target;
            target = arguments[i] || {};
            i++;
        }

        if (typeof target !== "object" && typeof target !== 'function') {
            target = {};
        }

        for (; i < length; i++) {
            if ((options = arguments[i]) != null) {
                for (name in options) {
                    src = target[name];
                    copy = options[name];

                    if (target === copy) {
                        continue;
                    }

                    if (deep && copy && (F.isPlainObject(copy) || (copyIsArray = Array.isArray(copy)))) {
                        if (copyIsArray) {
                            copyIsArray = false;
                            clone = src && Array.isArray(src) ? src : [];
                        } else {
                            clone = src && F.isPlainObject(src) ? src : {};
                        }
                        target[name] = F.extend(deep, clone, copy);
                    } else if (copy !== undefined) {
                        target[name] = copy;
                    }
                }
            }
        }
        return target;
    };

    F.config = {
        DEBOUNCE_DELAY_MS: 750,
        API_SEARCH_LIMIT: 12,
        DEFAULT_AVATAR_SIZE: 96,
        HEADER_AVATAR_SIZE: 32,
        AVATAR_COLORS: ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#06b6d4'],
        HEADER_AVATAR_COLORS: ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#34495e', '#7f8c8d']
    };

    F.state = {
        loggedInUserId: null,
        currentUserData: { id: null, fullName: 'Guest', username: 'Guest', profilePicture: null },
        targetUserIdForFriendsPage: null,

        _getTargetUserIdForFriendsPage: function() {
            const sectionWithProfileId = document.querySelector('section[data-profile-id]');
            if (sectionWithProfileId && sectionWithProfileId.dataset.profileId) {
                return sectionWithProfileId.dataset.profileId;
            }
            const pathMatch = window.location.pathname.match(/\/friends\/(\d+)/);
            if (pathMatch && pathMatch[1]) {
                return pathMatch[1];
            }
            return this.loggedInUserId || '0';
        },

        init: function() {
            this.loggedInUserId = typeof window._SESSION_USER_ID_ !== 'undefined' ? String(window._SESSION_USER_ID_) : null;
            this.currentUserData = window.currentUserData || { id: null, fullName: 'Guest', username: 'Guest', profilePicture: null };
            this.targetUserIdForFriendsPage = this._getTargetUserIdForFriendsPage();
        }
    };

    // ========================================================================
    // F ENDPOINTS
    // ========================================================================
    F.endpoints = {
        // User/Profile
        profile: '/profile/{userId}',
        login: '/login',

        // Friends
        friends: {
            base: '/friends', // For parsing current page, not for direct calls
            unfriend: '/friends/unfriend/{friendId}',
            add: '/friends/add/{friendId}',
            requestAccept: '/friends/request/{requestId}/accept',
            requestDecline: '/friends/request/{requestId}/decline',
            userFriendsSearch: '/friends/user-friends', // query params: query, user_id, limit
        },

        // Suggestions
        suggestions: {
            add: '/friends/suggestion/add/{suggestionId}',
            remove: '/friends/suggestion/remove/{suggestionId}',
            search: '/friends/suggestions/search', // query params: query, limit
        },

        // Messages
        messages: {
            withUser: '/messages/with/{userId}'
        }
    };

    F.utils = {
        debounce: function(func, delay) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        },
        createInitialsSvg: function(initials, size = F.config.DEFAULT_AVATAR_SIZE, textColor = '#FFFFFF', backgroundColor = '#6366f1') {
            const svgNamespace = "http://www.w3.org/2000/svg";
            const svg = document.createElementNS(svgNamespace, "svg");
            svg.setAttribute("width", String(size));
            svg.setAttribute("height", String(size));
            svg.setAttribute("viewBox", `0 0 ${size} ${size}`);
            svg.setAttribute("class", "initials-svg-avatar w-full h-full");

            const circle = document.createElementNS(svgNamespace, "circle");
            circle.setAttribute("cx", String(size / 2));
            circle.setAttribute("cy", String(size / 2));
            circle.setAttribute("r", String(size / 2));
            circle.setAttribute("fill", backgroundColor);
            svg.appendChild(circle);

            const text = document.createElementNS(svgNamespace, "text");
            text.setAttribute("x", "50%");
            text.setAttribute("y", "50%");
            text.setAttribute("dy", "0.35em");
            text.setAttribute("text-anchor", "middle");
            text.setAttribute("fill", textColor);
            text.setAttribute("font-size", String(Math.max(12, size / 2.8)));
            text.setAttribute("font-family", "Inter, sans-serif");
            text.setAttribute("font-weight", "600");
            text.textContent = String(initials).substring(0, 2).toUpperCase(); // textContent is safe
            svg.appendChild(text);
            return svg;
        },
        getColorForInitials: function(initialsStr, colorSet = F.config.AVATAR_COLORS) {
            let hash = 0;
            const str = String(initialsStr || '');
            if (str.length === 0) return colorSet[0];
            for (let i = 0; i < str.length; i++) {
                hash = str.charCodeAt(i) + ((hash << 5) - hash);
                hash = hash & hash;
            }
            return colorSet[Math.abs(hash) % colorSet.length];
        },
        sanitizeSVGText: function(text) {
            return String(text)
                .replace(/&/g, '&')
                .replace(/</g, '<')
                .replace(/>/g, '>')
                .replace(/"/g, '"')
                .replace(/'/g, "'");
        },
        sanitizeXMLChars: function(text) {
            return String(text)
                .replace(/&/g, '&')
                .replace(/</g, '<')
                .replace(/>/g, '>')
                .replace(/"/g, '"')
                .replace(/'/g, "'");
        },
        generateInitialsSVGDataURI: function(name, size = F.config.HEADER_AVATAR_SIZE) {
            let initials = '?';
            const trimmedName = String(name || '').trim();

            if (trimmedName) {
                const parts = trimmedName.split(/\s+/);
                initials = (parts[0] ? parts[0][0] : '').toUpperCase();
                if (parts.length > 1 && parts[parts.length - 1]) {
                    const lastInitial = parts[parts.length - 1][0].toUpperCase();
                    if (initials !== lastInitial && initials.length === 1 && /[A-Z0-9]/i.test(lastInitial)) {
                        initials += lastInitial;
                    }
                } else if (initials.length === 1 && trimmedName.length > 1 && parts.length === 1) {
                    if (/[A-Z0-9]/i.test(trimmedName[1])) {
                        initials += trimmedName[1].toUpperCase();
                    }
                }
            }
             if (!/^[A-Z0-9]{1,2}$/i.test(initials)) {
                initials = trimmedName[0] ? trimmedName[0].toUpperCase() : '';
                if (!/^[A-Z0-9]$/i.test(initials)) initials = '?';
            }

            const bgColor = F.utils.getColorForInitials(trimmedName, F.config.HEADER_AVATAR_COLORS);
            const fontSize = initials.length > 1 ? 40 : 50;

            const svgString = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${bgColor}"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="${fontSize}" fill="white" font-weight="600">${F.utils.sanitizeXMLChars(initials)}</text></svg>`;
            return "data:image/svg+xml;base64," + btoa(svgString);
        },
        fetchJSON: async function(url, options = {}) {
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorData = { message: `HTTP error! status: ${response.status}`};
                try {
                    const errJson = await response.json();
                    errorData = { ...errorData, ...errJson };
                } catch (e) { /* Ignore */ }
                throw new Error(errorData.error || errorData.message);
            }
            return response.json();
        },
        
        // ============ CSRF CHANGE 1: Create a central utility function ============
        /**
         * Returns the HTML string for a hidden CSRF input field.
         * Assumes `window.getCsrfToken()` helper is available.
         * @returns {string} The HTML for the hidden input, or an empty string if the token is not found.
         */
        getCsrfInputHtml: function() {
            // Uses the global helper established in other modules.
            const token = window.getCsrfToken ? window.getCsrfToken() : null;
            if (!token) {
                console.error("F.utils.getCsrfInputHtml: CSRF token is not available. Forms will be submitted without it.");
                return ''; // Return empty string to prevent breaking form submission
            }
            // Backend expects the field to be named 'csrf_token'.
            return `<input type="hidden" name="csrf_token" value="${token}">`;
        }
        // ======================= END OF CSRF CHANGE =======================
    };

    // ========================================================================
    // F MODULES
    // ========================================================================
    F.modules = {};

    F.modules.theme = (function() {
        let lightModeTextEl, darkModeTextEl, darkModeToggleEl;

        function checkPreference() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) return savedTheme;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function apply(theme) {
            document.documentElement.classList.remove('light', 'dark');
            document.documentElement.classList.add(theme);
            localStorage.setItem('theme', theme);

            if (lightModeTextEl && darkModeTextEl) {
                F(lightModeTextEl).toggleClass('hidden', theme === 'dark');
                F(darkModeTextEl).toggleClass('hidden', theme !== 'dark');
            }
        }

        function init() {
            lightModeTextEl = document.querySelector('.light-mode-text');
            darkModeTextEl = document.querySelector('.dark-mode-text');
            darkModeToggleEl = document.getElementById('darkModeToggle');

            apply(checkPreference());

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem('theme')) {
                    apply(e.matches ? 'dark' : 'light');
                }
            });

            if (darkModeToggleEl) {
                F(darkModeToggleEl).on('click', function() {
                    const isDark = document.documentElement.classList.contains('dark');
                    apply(isDark ? 'light' : 'dark');
                });
            }
        }
        return { init, applyTheme: apply };
    })();

    F.modules.avatarPlaceholders = (function() {
        function initialize() {
            F('.avatar-placeholder-container').each(function() {
                const container = this;
                if (F(container).find('.initials-svg-avatar').length > 0) return;

                const img = F(container).find('img')[0];
                const initials = F(container).data('initials') || '?';
                const size = parseInt(F(container).data('size')) || (container.offsetWidth > 0 ? container.offsetWidth : F.config.DEFAULT_AVATAR_SIZE);
                const bgColor = F(container).data('bgColor') || F.utils.getColorForInitials(initials);

                const generateAndReplace = () => {
                    if (F(container).find('.initials-svg-avatar').length > 0) return;
                    const svgElement = F.utils.createInitialsSvg(initials, size, '#FFFFFF', bgColor);
                    if (img) F(img).remove();
                    container.appendChild(svgElement);
                };

                if (img) {
                    const imgSrc = F(img).attr('src');
                    const isEmptyPlaceholder = !imgSrc || imgSrc.startsWith('data:image/gif;base64') || F(img).hasClass('hidden-if-no-src');

                    if (isEmptyPlaceholder) {
                        generateAndReplace();
                    } else {
                        img.onerror = function() {
                            img.onerror = null;
                            generateAndReplace();
                        };
                        if (img.complete && (typeof img.naturalWidth !== "undefined" && img.naturalWidth === 0)) {
                           img.dispatchEvent(new Event('error'));
                        }
                    }
                } else {
                    generateAndReplace();
                }
            });
        }
        return { init: initialize, refresh: initialize };
    })();

    // NEW USER DROPDOWN MODULE
    F.modules.userDropdown = (function(F, document) {
        'use strict';

        let userMenuBtnEl, userDropdownEl;

        function toggleDropdown(event) {
            event.stopPropagation(); // Prevent click from immediately closing via document listener
            if (userDropdownEl) {
                F(userDropdownEl).toggleClass('hidden');
            }
        }

        function closeDropdownOnClickOutside(event) {
            // Ensure elements exist and dropdown is visible
            if (userMenuBtnEl && userDropdownEl && !F(userDropdownEl).hasClass('hidden')) {
                // Check if the click is outside the button AND outside the dropdown
                if (!userMenuBtnEl.contains(event.target) && !userDropdownEl.contains(event.target)) {
                    F(userDropdownEl).addClass('hidden');
                }
            }
        }

        function init() {
            userMenuBtnEl = document.getElementById('userMenuBtn');
            userDropdownEl = document.getElementById('userDropdown');

            if (userMenuBtnEl && userDropdownEl) {
                F(userMenuBtnEl).on('click', toggleDropdown);
                F(document).on('click', closeDropdownOnClickOutside);
            }
        }

        return {
            init: init
        };
    })(F, document);


    F.modules.dropdowns = (function() { // MODIFIED: Handles non-user dropdowns like friend options
        function init() {
            F(document).on('click', '.friend-options-btn', function(event) {
                event.stopPropagation();
                const button = this;
                const friendId = F(button).data('friendId');
                const actionsContainer = button.parentElement;
                
                if (!actionsContainer) return;

                const existingDropdown = F(actionsContainer).find('.friend-options-dropdown')[0];
                if (existingDropdown) {
                    F(existingDropdown).remove();
                    return;
                }
                
                F('.friend-options-dropdown').remove(); // Close any other open friend options dropdown

                const dropdown = document.createElement('div');
                dropdown.className = 'friend-options-dropdown absolute right-0 mt-1 w-40 bg-dark-600 light:bg-gray-200 rounded-md shadow-lg py-1 z-20 border border-dark-500 light:border-gray-300';
                F(dropdown).css('top', 'calc(100% + 0.25rem)'); // Position below the button

                const unfriendForm = document.createElement('form');
                unfriendForm.method = 'POST';
                unfriendForm.action = F.endpoints.friends.unfriend.replace('{friendId}', friendId);
                
                // ============ CSRF CHANGE 2: Inject CSRF token into dropdown form ============
                unfriendForm.innerHTML = `
                    ${F.utils.getCsrfInputHtml()}
                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-dark-500 light:hover:bg-gray-300 hover:text-red-500 light:text-red-500 light:hover:text-red-600">
                        <i class="fas fa-user-minus mr-2"></i> Unfriend
                    </button>`;
                // ======================= END OF CSRF CHANGE =======================

                dropdown.appendChild(unfriendForm);
                actionsContainer.appendChild(dropdown); // Append to the button's parent for relative positioning
            });

            // Global click listener, now only for friend-options-dropdown
            F(document).on('click', function(event) {
                const friendOptionsDropdown = document.querySelector('.friend-options-dropdown');
                if (friendOptionsDropdown && 
                    !friendOptionsDropdown.contains(event.target) && 
                    !F(event.target).closest('.friend-options-btn').length) { // Check if click is not on the button that opened it
                    F(friendOptionsDropdown).remove();
                }
            });
        }
        return { init };
    })();
    
    F.modules.headerUser = (function() {
        function updateAvatar() {
            const userMenuBtn = document.getElementById('userMenuBtn');
            const userDropdownProfileLinkEl = document.getElementById('userDropdownProfileLink');
            if (!userMenuBtn) return;

            let avatarSrcToUse;
            let altText = "Profile";
            let profileLinkHref = F.endpoints.login; // Default to login

            if (F.state.loggedInUserId && F.state.currentUserData.id) {
                altText = (F.state.currentUserData.fullName || F.state.currentUserData.username || 'User') + " Profile";
                profileLinkHref = F.endpoints.profile.replace('{userId}', F.state.currentUserData.id);
                avatarSrcToUse = F.state.currentUserData.profilePicture || F.utils.generateInitialsSVGDataURI(F.state.currentUserData.fullName || F.state.currentUserData.username || 'U');
            } else {
                altText = "Guest Profile";
                avatarSrcToUse = F.utils.generateInitialsSVGDataURI('Guest');
            }
            
            let avatarImgEl = F(userMenuBtn).find('#userMenuAvatar')[0];
            if (!avatarImgEl) {
                F(userMenuBtn).empty();
                avatarImgEl = document.createElement('img');
                avatarImgEl.id = 'userMenuAvatar';
                avatarImgEl.className = 'w-8 h-8 rounded-full';
                userMenuBtn.appendChild(avatarImgEl);
            }
            
            F(avatarImgEl).attr('src', avatarSrcToUse).attr('alt', altText).show();
            if (userDropdownProfileLinkEl) F(userDropdownProfileLinkEl).attr('href', profileLinkHref);
        }
        function init() {
            updateAvatar();
        }
        return { init, update: updateAvatar };
    })();

    F.modules.allFriendsSearch = (function() {
        const state = {
            initialCardElements: [],
            serverSearchedForCurrentTerm: false,
            debounceTimer: null
        };
        const DOMElements = {};

        function cacheDOMElements() {
            DOMElements.sortSelect = document.getElementById('allFriendsSortSelect');
            DOMElements.searchInput = document.getElementById('allFriendsSearchInput');
            DOMElements.gridContainer = document.getElementById('allFriendsGridContainer');
            DOMElements.noResultsDiv = document.getElementById('allFriendsNoResults');
            DOMElements.noResultsTextP = document.getElementById('allFriendsNoResultsText');
            DOMElements.loadingIndicator = document.getElementById('allFriendsLoadingIndicator');
            DOMElements.initialEmptyMessage = document.getElementById('allFriendsGridInitialEmptyMessage');
        }

        function renderCard(friendData) {
            const initials = friendData.avatar_initials || '?';
            const bgColor = friendData.avatar_bg_color || F.utils.getColorForInitials(initials);
            let actionButtonsHtml = '';

            const profileUrl = F.endpoints.profile.replace('{userId}', friendData.id);
            const messageUrl = F.endpoints.messages.withUser.replace('{userId}', friendData.id);

            const primaryBtnClass = "flex-1 h-9 px-3 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors flex items-center justify-center";
            const optionsBtnClass = "h-9 w-9 flex items-center justify-center text-sm font-medium text-gray-200 light:text-gray-800 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-lg transition-colors friend-options-btn";
            const disabledBtnClass = "flex-1 h-9 px-3 text-sm font-medium text-gray-400 light:text-gray-500 bg-dark-500 light:bg-gray-300 rounded-lg cursor-not-allowed flex items-center justify-center";
            const acceptBtnClass = "flex-1 h-9 px-3 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors flex items-center justify-center";
            const secondaryBtnClass = "flex-1 h-9 px-3 text-sm font-medium text-gray-200 light:text-gray-800 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-lg transition-colors flex items-center justify-center";

            const isViewingOwnFriends = friendData.is_viewer_viewing_own_friends || (F.state.targetUserIdForFriendsPage === F.state.loggedInUserId);

            if (isViewingOwnFriends) {
                 actionButtonsHtml = `
                    <a href="${messageUrl}" class="${primaryBtnClass}">Message</a>
                    <button class="${optionsBtnClass}" data-friend-id="${friendData.id}"><i class="fas fa-ellipsis-h"></i></button>
                `;
            } else {
                switch (friendData.relationship_with_viewer) {
                    case 'friends':
                        actionButtonsHtml = `
                            <a href="${messageUrl}" class="${primaryBtnClass}">Message</a>
                            <button class="${optionsBtnClass}" data-friend-id="${friendData.id}"><i class="fas fa-ellipsis-h"></i></button>
                        `;
                        break;
                    case 'request_sent':
                        actionButtonsHtml = `<button disabled class="${disabledBtnClass}">Request Sent</button>`;
                        break;
                    case 'request_received':
                         if(friendData.pending_request_id_from_them) {
                            const acceptUrl = F.endpoints.friends.requestAccept.replace('{requestId}', friendData.pending_request_id_from_them);
                            const declineUrl = F.endpoints.friends.requestDecline.replace('{requestId}', friendData.pending_request_id_from_them);
                            // ============ CSRF CHANGE 3a: Inject CSRF token into friend card forms ============
                            actionButtonsHtml = `
                                <form action="${acceptUrl}" method="POST" class="flex-1">${F.utils.getCsrfInputHtml()}<button type="submit" class="${acceptBtnClass}">Accept</button></form>
                                <form action="${declineUrl}" method="POST" class="flex-1">${F.utils.getCsrfInputHtml()}<button type="submit" class="${secondaryBtnClass}">Decline</button></form>
                            `;
                            // ======================= END OF CSRF CHANGE =======================
                        } else { actionButtonsHtml = `<span class="text-xs text-gray-400">Request Pending</span>`;}
                        break;
                    case 'not_friends':
                    default:
                        const addFriendUrl = F.endpoints.friends.add.replace('{friendId}', friendData.id);
                        // ============ CSRF CHANGE 3b: Inject CSRF token into friend card forms ============
                        actionButtonsHtml = `<form action="${addFriendUrl}" method="POST" class="flex-1">${F.utils.getCsrfInputHtml()}<button type="submit" class="${primaryBtnClass}">Add Friend</button></form>`;
                        // ======================= END OF CSRF CHANGE =======================
                        break;
                }
            }
            return `
                <div class="friend-card-item bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 rounded-lg p-4 flex flex-col items-center transition-transform hover:scale-[1.02] transition-bg transition-border"
                     data-friend-name="${(friendData.full_name || friendData.username || '').toLowerCase()}"
                     data-friended-at="${friendData.accepted_at || '0'}"
                     data-friend-id="${friendData.id}">
                    <a href="${profileUrl}" class="mb-3 avatar-placeholder-container h-24 w-24 rounded-full border-2 border-primary-600 overflow-hidden"
                       data-initials="${initials}" data-size="${F.config.DEFAULT_AVATAR_SIZE}" data-bg-color="${bgColor}">
                        <img src="${friendData.profile_picture || 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}"
                             alt="${friendData.full_name || friendData.username}"
                             class="w-full h-full object-cover ${!friendData.profile_picture ? 'hidden-if-no-src' : ''}">
                    </a>
                    <a href="${profileUrl}" class="font-semibold text-gray-100 light:text-gray-800 hover:underline transition-text text-center mb-2">
                        ${friendData.full_name || friendData.username}
                    </a>
                    <p class="text-gray-400 light:text-gray-600 text-sm mb-4 text-center transition-text h-10 leading-tight overflow-hidden">
                        ${friendData.bio ? friendData.bio.substring(0,50) + (friendData.bio.length > 50 ? '...' : '') : (isViewingOwnFriends ? 'Friend' : 'User')}
                    </p>
                    <div class="flex gap-2 w-full relative mt-auto pt-2 border-t border-dark-600 light:border-gray-300">
                        ${actionButtonsHtml}
                    </div>
                </div>`;
        }

        async function performServerSearch(term) {
            if (!term) {
                if (DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                return;
            }
            
            if (DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).removeClass('hidden');
            if (DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).addClass('hidden');
            state.serverSearchedForCurrentTerm = true;

            try {
                const searchUrl = `${F.endpoints.friends.userFriendsSearch}?query=${encodeURIComponent(term)}&user_id=${F.state.targetUserIdForFriendsPage}&limit=${F.config.API_SEARCH_LIMIT}`;
                const data = await F.utils.fetchJSON(searchUrl);
                
                if(DOMElements.gridContainer) F(DOMElements.gridContainer).empty();
                state.initialCardElements = [];

                if (data.friends && data.friends.length > 0) {
                    data.friends.forEach(friend => {
                        if(DOMElements.gridContainer) F(DOMElements.gridContainer).append(renderCard(friend));
                    });
                    F.modules.avatarPlaceholders.refresh();
                    if(DOMElements.gridContainer) state.initialCardElements = Array.from(DOMElements.gridContainer.getElementsByClassName('friend-card-item'));
                } else {
                    if(DOMElements.noResultsTextP) F(DOMElements.noResultsTextP).text(`No friends found online matching "${term}".`);
                    if(DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).removeClass('hidden');
                }
            } catch (error) {
                console.error("Error fetching 'All Friends':", error);
                if(DOMElements.noResultsTextP) F(DOMElements.noResultsTextP).text(`Error: ${error.message || 'Could not search friends.'}`);
                if(DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).removeClass('hidden');
            } finally {
                if(DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                filterAndSort();
            }
        }

        function filterAndSort() {
            if (!DOMElements.gridContainer) return;

            const searchTerm = DOMElements.searchInput ? F(DOMElements.searchInput).val().toLowerCase().trim() : '';
            const sortBy = DOMElements.sortSelect ? F(DOMElements.sortSelect).val() : 'default';
            
            if (DOMElements.searchInput && F(DOMElements.searchInput).data('lastSearchTerm') !== searchTerm) {
                state.serverSearchedForCurrentTerm = false;
            }
            if(DOMElements.searchInput) F(DOMElements.searchInput).data('lastSearchTerm', searchTerm);

            let cardsToProcess = [...state.initialCardElements];

            if (searchTerm) {
                cardsToProcess = cardsToProcess.filter(cardElement => 
                    (F(cardElement).data('friendName') || '').includes(searchTerm)
                );
            }
            
            cardsToProcess.sort((a, b) => { 
                const nameA = F(a).data('friendName') || ''; const nameB = F(b).data('friendName') || '';
                const dateA = parseInt(F(a).data('friendedAt') || '0'); const dateB = parseInt(F(b).data('friendedAt') || '0');
                switch (sortBy) {
                    case 'name_asc': return nameA.localeCompare(nameB);
                    case 'name_desc': return nameB.localeCompare(nameA);
                    case 'date_asc': return dateA - dateB;
                    case 'date_desc': default: return dateB - dateA;
                }
            });

            F(DOMElements.gridContainer).empty();
            cardsToProcess.forEach(card => DOMElements.gridContainer.appendChild(card));
            const visibleCount = cardsToProcess.length;

            if (visibleCount === 0 && searchTerm !== '') {
                if (!state.serverSearchedForCurrentTerm) {
                    if(DOMElements.noResultsTextP) F(DOMElements.noResultsTextP).text(`No local matches for "${searchTerm}". Searching all friends...`);
                    if(DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).removeClass('hidden');
                    if(DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).removeClass('hidden');
                    
                    clearTimeout(state.debounceTimer);
                    state.debounceTimer = setTimeout(() => {
                        performServerSearch(searchTerm);
                    }, F.config.DEBOUNCE_DELAY_MS);
                } else {
                    if(DOMElements.noResultsTextP) F(DOMElements.noResultsTextP).text(`No friends found matching "${searchTerm}".`);
                    if(DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).removeClass('hidden');
                    if(DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                }
            } else if (DOMElements.noResultsDiv) {
                F(DOMElements.noResultsDiv).toggleClass('hidden', visibleCount > 0 || searchTerm === '');
                if (visibleCount === 0 && searchTerm !== '' && DOMElements.noResultsTextP) {
                    if(!F(DOMElements.noResultsTextP).text().includes("Searching all friends...")) {
                        F(DOMElements.noResultsTextP).text(`No friends found matching "${searchTerm}".`);
                    }
                } else if (visibleCount === 0 && searchTerm === '' && state.initialCardElements.length === 0 && DOMElements.noResultsTextP) {
                    F(DOMElements.noResultsTextP).text(F(DOMElements.initialEmptyMessage).text() || "This user has no friends yet.");
                }
            }
        }
        
        function init() {
            cacheDOMElements();
            if (!DOMElements.gridContainer) return;

            state.initialCardElements = Array.from(DOMElements.gridContainer.getElementsByClassName('friend-card-item'));
            
            if (DOMElements.searchInput) F(DOMElements.searchInput).on('input', filterAndSort);
            if (DOMElements.sortSelect) F(DOMElements.sortSelect).on('change', filterAndSort);
            
            if (state.initialCardElements.length > 0) {
                filterAndSort();
            } else if (DOMElements.noResultsDiv && DOMElements.noResultsTextP) {
                F(DOMElements.noResultsDiv).removeClass('hidden');
                const initialSearchTerm = DOMElements.searchInput ? F(DOMElements.searchInput).val().trim() : '';
                if (initialSearchTerm) {
                    F(DOMElements.noResultsTextP).text(`No friends found matching "${initialSearchTerm}".`);
                    filterAndSort(); 
                } else {
                    F(DOMElements.noResultsTextP).text(F(DOMElements.initialEmptyMessage).text() || "This user has no friends yet.");
                }
            }
        }
        return { init };
    })();

    F.modules.suggestionsSearch = (function() {
        const state = {
            initialCardElements: [],
            serverSearchedForCurrentTerm: false,
            debounceTimer: null
        };
        const DOMElements = {};

        function cacheDOMElements() {
            DOMElements.searchInput = document.getElementById('searchSuggestionsInput');
            DOMElements.gridContainer = document.getElementById('suggestedFriendsGridContainer');
            DOMElements.noResultsDiv = document.getElementById('noSuggestionsFoundMessage');
            DOMElements.noResultsTextP = document.getElementById('noSuggestionsText');
            DOMElements.loadingIndicator = document.getElementById('suggestionsLoadingIndicator');
            DOMElements.seeAllLink = document.getElementById('seeAllSuggestionsLink');
        }
        
        function renderCard(suggestion) {
            const initials = suggestion.avatar_initials || '?';
            const bgColor = suggestion.avatar_bg_color || F.utils.getColorForInitials(initials);
            const profileUrl = F.endpoints.profile.replace('{userId}', suggestion.id);
            const addSuggestionUrl = F.endpoints.suggestions.add.replace('{suggestionId}', suggestion.id);
            const removeSuggestionUrl = F.endpoints.suggestions.remove.replace('{suggestionId}', suggestion.id);

            // ============ CSRF CHANGE 4: Inject CSRF token into suggestion card forms ============
            return `
                <div class="suggestion-card-item bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 rounded-lg p-4 flex flex-col items-center transition-transform hover:scale-[1.02] transition-bg transition-border"
                     data-suggestion-name="${(suggestion.full_name || suggestion.username || '').toLowerCase()}" data-suggestion-id="${suggestion.id}">
                    <a href="${profileUrl}" class="mb-3 avatar-placeholder-container h-24 w-24 rounded-full border-2 border-primary-600 overflow-hidden"
                       data-initials="${initials}" data-size="${F.config.DEFAULT_AVATAR_SIZE}" data-bg-color="${bgColor}">
                        <img src="${suggestion.profile_picture || 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}"
                             alt="${suggestion.full_name || suggestion.username}"
                             class="w-full h-full object-cover ${!suggestion.profile_picture ? 'hidden-if-no-src' : ''}">
                    </a>
                    <a href="${profileUrl}" class="font-semibold text-gray-100 light:text-gray-800 hover:underline transition-text text-center mb-1">
                        ${suggestion.full_name || suggestion.username}
                    </a>
                    <p class="text-gray-400 light:text-gray-600 text-sm mb-4 text-center transition-text h-10 leading-tight overflow-hidden">
                        ${suggestion.mutual_friends_count > 0 ? `${suggestion.mutual_friends_count} mutual friend${suggestion.mutual_friends_count > 1 ? 's' : ''}` : (suggestion.bio ? suggestion.bio.substring(0,50) + (suggestion.bio.length > 50 ? '...' : '') : 'New to SmartFed')}
                    </p>
                    <div class="flex gap-2 w-full mt-auto pt-2 border-t border-dark-600 light:border-gray-300">
                        <form action="${addSuggestionUrl}" method="POST" class="flex-1">
                            ${F.utils.getCsrfInputHtml()}
                            <button type="submit" class="w-full h-9 px-3 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors flex items-center justify-center">Add</button>
                        </form>
                        <form action="${removeSuggestionUrl}" method="POST" class="flex-1">
                            ${F.utils.getCsrfInputHtml()}
                            <button type="submit" class="w-full h-9 px-3 text-sm font-medium text-gray-200 light:text-gray-800 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-lg transition-colors flex items-center justify-center">Remove</button>
                        </form>
                    </div>
                </div>`;
            // ======================= END OF CSRF CHANGE =======================
        }

        async function performServerSearch(term) {
            if (!term) {
                if (DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                return;
            }
            if (DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).removeClass('hidden');
            if (DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).addClass('hidden');
            state.serverSearchedForCurrentTerm = true;

            try {
                const searchUrl = `${F.endpoints.suggestions.search}?query=${encodeURIComponent(term)}&limit=${F.config.API_SEARCH_LIMIT}`;
                const data = await F.utils.fetchJSON(searchUrl);
                
                if(DOMElements.gridContainer) F(DOMElements.gridContainer).empty();
                state.initialCardElements = [];

                if (data.suggestions && data.suggestions.length > 0) {
                    data.suggestions.forEach(sug => {
                        if(DOMElements.gridContainer) F(DOMElements.gridContainer).append(renderCard(sug));
                    });
                    F.modules.avatarPlaceholders.refresh();
                    if(DOMElements.gridContainer) state.initialCardElements = Array.from(DOMElements.gridContainer.getElementsByClassName('suggestion-card-item'));
                } else {
                    if(DOMElements.noResultsTextP) F(DOMElements.noResultsTextP).text(`No further suggestions found online for "${term}".`);
                    if(DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).removeClass('hidden');
                }
            } catch (error) {
                console.error("Error fetching server suggestions:", error);
                if(DOMElements.noResultsTextP) F(DOMElements.noResultsTextP).text(`Error: ${error.message || 'Could not search suggestions.'}`);
                if(DOMElements.noResultsDiv) F(DOMElements.noResultsDiv).removeClass('hidden');
            } finally {
                if(DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                if (DOMElements.seeAllLink) F(DOMElements.seeAllLink).toggleClass('hidden', state.initialCardElements.length === 0);
            }
        }

        function filterClientSideAndTriggerServerSearch() {
            if (!DOMElements.searchInput || !DOMElements.gridContainer) return;

            const searchTerm = F(DOMElements.searchInput).val().toLowerCase().trim();
            if (F(DOMElements.searchInput).data('lastSearchTerm') !== searchTerm) {
                state.serverSearchedForCurrentTerm = false;
            }
            F(DOMElements.searchInput).data('lastSearchTerm', searchTerm);

            let visibleCount = 0;
            if (state.initialCardElements.length > 0) {
                state.initialCardElements.forEach(card => {
                    const suggestionName = F(card).data('suggestionName') || '';
                    const isMatch = suggestionName.includes(searchTerm);
                    F(card).css('display', isMatch ? '' : 'none');
                    if (isMatch) visibleCount++;
                });
            }
            
            const initialPhpNoSuggestionsText = DOMElements.noResultsTextP ? (F(DOMElements.noResultsTextP).data('initialText') || "No new friend suggestions right now.") : "No new friend suggestions right now.";

            if (DOMElements.noResultsDiv && DOMElements.noResultsTextP) {
                if (visibleCount === 0 && searchTerm !== '') {
                    clearTimeout(state.debounceTimer);
                    if (!state.serverSearchedForCurrentTerm) {
                        F(DOMElements.noResultsTextP).text(`No local matches for "${searchTerm}". Checking online...`);
                        F(DOMElements.noResultsDiv).removeClass('hidden');
                        if(DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).removeClass('hidden');
                        state.debounceTimer = setTimeout(() => {
                            performServerSearch(searchTerm);
                        }, F.config.DEBOUNCE_DELAY_MS);
                    } else {
                        F(DOMElements.noResultsTextP).text(`No suggestions found for "${searchTerm}".`);
                        F(DOMElements.noResultsDiv).removeClass('hidden');
                        if(DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                    }
                } else if (visibleCount === 0 && searchTerm === '' && state.initialCardElements.length === 0) {
                    F(DOMElements.noResultsTextP).text(initialPhpNoSuggestionsText);
                    F(DOMElements.noResultsDiv).removeClass('hidden');
                } else {
                    F(DOMElements.noResultsDiv).addClass('hidden');
                }
            }
            if (DOMElements.seeAllLink) {
                const shouldShowLink = (searchTerm === '' && state.initialCardElements.length > 0) || (searchTerm !== '' && visibleCount > 0);
                F(DOMElements.seeAllLink).toggleClass('hidden', !shouldShowLink);
            }
        }

        function init() {
            cacheDOMElements();
            if (!DOMElements.gridContainer) return;

            state.initialCardElements = Array.from(DOMElements.gridContainer.getElementsByClassName('suggestion-card-item'));
            if (DOMElements.searchInput) {
                F(DOMElements.searchInput).on('input', filterClientSideAndTriggerServerSearch);
            }

            if (DOMElements.noResultsDiv && DOMElements.noResultsTextP) {
                const originallyEmpty = state.initialCardElements.length === 0;
                F(DOMElements.noResultsDiv).toggleClass('hidden', !originallyEmpty);
                if (originallyEmpty) {
                     const initialPhpNoSuggestionsText = F(DOMElements.noResultsTextP).data('initialText') || "No new friend suggestions right now.";
                    F(DOMElements.noResultsTextP).text(initialPhpNoSuggestionsText);
                }
                if (DOMElements.seeAllLink) {
                    F(DOMElements.seeAllLink).toggleClass('hidden', originallyEmpty);
                }
            }
            if (DOMElements.searchInput && F(DOMElements.searchInput).val().trim() !== '') {
                filterClientSideAndTriggerServerSearch();
            }
        }
        return { init };
    })();


    // ========================================================================
    // F DOM READY & INITIALIZATION
    // ========================================================================
    F.ready = function(fn) {
        if (document.readyState === 'complete' || (document.readyState !== 'loading' && !document.documentElement.doScroll)) {
            window.setTimeout(fn);
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    };

    F.ready(function() {
        // ============ CSRF CHANGE 5: Define global CSRF helper on DOM ready ============
        if (!window.getCsrfToken) {
            window.getCsrfToken = () => {
                const tokenElement = document.querySelector('meta[name="csrf-token"]');
                if (!tokenElement) {
                    console.error('CSRF token meta tag not found. Please ensure your HTML includes: <meta name="csrf-token" content="...">');
                    return null;
                }
                return tokenElement.getAttribute('content');
            };
            console.log("F-core: CSRF token helper function initialized.");
        }
        // ======================= END OF CSRF CHANGE =======================

        F.state.init();

        F.modules.theme.init();
        F.modules.avatarPlaceholders.init();
        F.modules.userDropdown.init();      // Initialize new user dropdown module
        F.modules.dropdowns.init();         // Initialize other dropdown functionalities (friend options)
        F.modules.headerUser.init();

        if (document.getElementById('allFriendsGridContainer')) {
            F.modules.allFriendsSearch.init();
        }
        if (document.getElementById('suggestedFriendsGridContainer')) {
            F.modules.suggestionsSearch.init();
        }        
    });

    window.F = F;

})(window, document);