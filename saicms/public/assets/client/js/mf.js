// Mutual Friends
(function(F, window, document, undefined) {
    'use strict';
    
    // ============================ NO CHANGES NEEDED HERE ============================
    // The MutualFriends module as provided uses F.utils.fetchJSON, which appears to make GET requests
    // to fetch data. GET requests do not modify server state and thus do not require CSRF protection.
    // Therefore, this section remains unchanged.
    // ==============================================================================

    if (!F || !F.fn) {
        console.error('MF_MODULE_ERROR: F library is not loaded. MutualFriends module cannot be initialized.');
        return;
    }

    if (!F.endpoints.friends) {
        F.endpoints.friends = {};
    }
    F.endpoints.friends.mutual = F.endpoints.friends.mutual || '/friends/mutual/{userId}';

    F.modules.mutualFriends = (function() {

        const DEFAULT_LIMIT = 10;

        function cacheDOMElements(containerElement) {
            const DOMElements = {};
            const $container = F(containerElement);
            DOMElements.container = containerElement;
            DOMElements.list = $container.find('.mutual-friends-list')[0];
            DOMElements.loadingIndicator = $container.find('.mutual-friends-loading')[0];
            DOMElements.searchLoadingIndicator = $container.find('.mutual-friends-search-loading')[0]; // For server search
            DOMElements.errorMessage = $container.find('.mutual-friends-error')[0];
            DOMElements.emptyMessage = $container.find('.mutual-friends-empty')[0];
            DOMElements.loadMoreContainer = $container.find('.mutual-friends-load-more-container')[0];
            DOMElements.loadMoreBtn = $container.find('.mutual-friends-load-more-btn')[0];
            DOMElements.countDisplay = $container.find('.mutual-friends-count-display')[0];
            DOMElements.searchInput = $container.find('.mutual-friends-search-input')[0];
            return DOMElements;
        }

        function renderMutualFriend(friendData) {
            const profileUrl = (F.endpoints.profile || '/profile/{userId}').replace('{userId}', friendData.id);
            const messageUrl = (F.endpoints.messages?.withUser || '/messages/user/{userId}').replace('{userId}', friendData.id);
            const avatarFallback = F.utils.generateInitialsSVGDataURI ? F.utils.generateInitialsSVGDataURI(friendData.full_name || friendData.username, 48) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
            const avatarSrc = friendData.profile_picture || avatarFallback;
            const fullName = F.utils.sanitizeXMLChars(friendData.full_name || friendData.username || 'User');
            const username = F.utils.sanitizeXMLChars(friendData.username || 'username');

            // Store searchable text in a data attribute for easy client-side filtering
            return `
                <li class="mutual-friend-item bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 rounded-lg p-3 flex items-center space-x-3 transition-colors hover:border-primary-500"
                    data-friend-id="${friendData.id}"
                    data-search-terms="${fullName.toLowerCase()} ${username.toLowerCase()}">
                    <div class="flex-shrink-0 rounded-full overflow-hidden h-12 w-12">
                        <img src="${avatarSrc}" alt="${fullName}" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <a href="${profileUrl}" class="block font-medium text-gray-100 light:text-gray-800 hover:underline truncate">${fullName}</a>
                        <p class="text-xs text-gray-400 light:text-gray-600 truncate">@${username}</p>
                    </div>
                    <a href="${messageUrl}" class="flex-shrink-0 h-8 w-8 flex items-center justify-center text-gray-400 light:text-gray-500 hover:text-primary-500 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-full transition-colors" title="Message ${fullName}">
                        <i class="fas fa-comment-dots"></i>
                    </a>
                </li>
            `;
        }

        async function fetchAndDisplayMutualFriends(targetUserId, containerElement, options = {}) {
            const DOMElements = cacheDOMElements(containerElement);
            if (!DOMElements.list) return;

            const {
                page = 1,
                limit = parseInt(F(containerElement).data('mutualFriendsLimit')) || DEFAULT_LIMIT,
                searchTerm = '', // Search term for server query, if any
                isPaginating = false,
                isServerFallbackSearch = false, // True if this is a server search triggered after local search failed
                isInitialLoad = (page === 1 && !searchTerm && !isPaginating)
            } = options;

            // UI updates for loading state
            if (isServerFallbackSearch && DOMElements.searchLoadingIndicator) F(DOMElements.searchLoadingIndicator).removeClass('hidden');
            else if (isInitialLoad && DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).removeClass('hidden');
            // For pagination, the loadMoreBtn itself shows "Loading..."

            if (DOMElements.errorMessage) F(DOMElements.errorMessage).addClass('hidden');
            if (DOMElements.emptyMessage) F(DOMElements.emptyMessage).addClass('hidden'); // Hide empty message during load
            if (DOMElements.loadMoreBtn && (isInitialLoad || isServerFallbackSearch)) { // Disable load more if not paginating
                 F(DOMElements.loadMoreBtn).attr('disabled', true);
            } else if (DOMElements.loadMoreBtn && isPaginating) {
                 F(DOMElements.loadMoreBtn).attr('disabled', true).text('Loading...');
            }


            if (isInitialLoad || (isServerFallbackSearch && page === 1)) { // Clear list for initial load or new server search
                F(DOMElements.list).empty();
                F(containerElement).data('mutualFriendsCurrentPage', 1);
            }
            // `serverSearchTermActive` isn't strictly needed with this hybrid model in the same way,
            // but we can use a flag to know if a server search is in progress for a specific term.
            if (isServerFallbackSearch) F(containerElement).data('isFetchingServerSearch', true);


            try {
                let endpoint = `${F.endpoints.friends.mutual.replace('{userId}', targetUserId)}?page=${page}&limit=${limit}`;
                // Only add 'q' to endpoint if it's a server fallback search.
                // Regular pagination of "all" items should not include 'q'.
                if (isServerFallbackSearch && searchTerm) {
                    endpoint += `&q=${encodeURIComponent(searchTerm)}`;
                }

                const data = await F.utils.fetchJSON(endpoint);

                F(containerElement).data('mutualFriendsCurrentPage', data.current_page || page);
                F(containerElement).data('hasMoreMutualFriends', data.has_more === true);
                // If it was a server search, the total count is for that search.
                // If it was a general load, it's the overall total (or total for "all").
                // This needs careful handling by the backend.
                // Let's assume backend returns total_mutual_friends relevant to the query (with or without 'q')
                F(containerElement).data('totalMutualFriendsCount', data.total_mutual_friends || 0);
                F(containerElement).data('apiMessage', data.message || '');

                if (data.mutual_friends && data.mutual_friends.length > 0) {
                    const fragment = document.createDocumentFragment();
                    const tempDiv = document.createElement('div');
                    data.mutual_friends.forEach(friend => {
                        const friendHTML = renderMutualFriend(friend).trim();
                        if (friendHTML) {
                            tempDiv.innerHTML = friendHTML;
                            if (tempDiv.firstChild && tempDiv.firstChild.nodeType === Node.ELEMENT_NODE) {
                                // Before appending, check if this item already exists from a previous load (relevant for hybrid)
                                // This check is more complex if IDs aren't globally unique across search/non-search
                                // For simplicity here, we assume server search results are distinct or replace existing list
                                fragment.appendChild(tempDiv.firstChild);
                            }
                        }
                    });
                    F(DOMElements.list).append(fragment);
                }

                // After fetching, always re-apply client-side filter if a search term is in the input
                // This ensures newly loaded items (from pagination or server search) are correctly shown/hidden
                const currentInputSearchTerm = DOMElements.searchInput ? F(DOMElements.searchInput).val().trim().toLowerCase() : '';
                applyClientSideFilter(containerElement, currentInputSearchTerm, isServerFallbackSearch);
                // updateUIStates will be called by applyClientSideFilter

            } catch (error) {
                console.error('MF_MODULE_FETCH_ERROR:', error);
                F(containerElement).data('hasMoreMutualFriends', false);
                const errorMessage = `Error: ${error.message || 'Could not load data.'}`;
                F(containerElement).data('apiMessage', errorMessage);
                if (DOMElements.errorMessage) {
                    const p = F(DOMElements.errorMessage).find('p')[0] || DOMElements.errorMessage;
                    F(p).text(errorMessage);
                    F(DOMElements.errorMessage).removeClass('hidden');
                }
                // After an error, still update UI based on what's present (likely nothing new)
                const currentInputSearchTerm = DOMElements.searchInput ? F(DOMElements.searchInput).val().trim().toLowerCase() : '';
                applyClientSideFilter(containerElement, currentInputSearchTerm, isServerFallbackSearch, true /* indicate error */);

            } finally {
                if (DOMElements.loadingIndicator) F(DOMElements.loadingIndicator).addClass('hidden');
                if (DOMElements.searchLoadingIndicator) F(DOMElements.searchLoadingIndicator).addClass('hidden');
                if (DOMElements.loadMoreBtn) {
                    const hasMore = F(containerElement).data('hasMoreMutualFriends') === true;
                     // Only enable if there are more items AND no active search term (or if server search also has pagination)
                    const currentInputSearchTerm = DOMElements.searchInput ? F(DOMElements.searchInput).val().trim().toLowerCase() : '';
                    const canLoadMore = hasMore && (!currentInputSearchTerm || (currentInputSearchTerm && isServerFallbackSearch));

                    F(DOMElements.loadMoreBtn).attr('disabled', !canLoadMore).text('Load More');
                }
                if (isServerFallbackSearch) F(containerElement).data('isFetchingServerSearch', false);
            }
        }

        /**
         * Applies client-side filtering to the currently rendered <li> items.
         * If no items match and it wasn't a server search result itself, it can trigger a server search.
         * @param {boolean} fromServerSearchResult - True if this is called right after a server search has populated the list.
         * @param {boolean} fetchErrorOccurred - True if called from fetch error block.
         */
        function applyClientSideFilter(containerElement, searchTerm, fromServerSearchResult = false, fetchErrorOccurred = false) {
            const DOMElements = cacheDOMElements(containerElement);
            if (!DOMElements.list) return;

            let visibleCount = 0;
            F(DOMElements.list).find('li.mutual-friend-item').each(function() { // Use a common class for items
                if (searchTerm === '') {
                    F(this).show(); // Or remove 'hidden' class
                    visibleCount++;
                } else {
                    const itemSearchTerms = F(this).data('searchTerms') || ''; // Get pre-stored search terms
                    if (itemSearchTerms.includes(searchTerm)) {
                        F(this).show();
                        visibleCount++;
                    } else {
                        F(this).hide();
                    }
                }
            });

            // Logic to trigger server search if local search fails
            const targetUserId = F(containerElement).data('mutualFriendsFor');
            const hasMorePotentially = F(containerElement).data('hasMoreMutualFriends') === true; // Check if server *might* have more
            const isCurrentlyFetchingServerSearch = F(containerElement).data('isFetchingServerSearch') === true;

            if (searchTerm !== '' && visibleCount === 0 && !fromServerSearchResult && !isCurrentlyFetchingServerSearch && !fetchErrorOccurred) {
                // If local search yields nothing for a term, AND it wasn't already a server search result for this term,
                // AND we are not already fetching, THEN try a server search.
                // We could also check `hasMorePotentially` if we only want to try server if more general items *could* exist.
                // For now, let's always try server if local fails for a specific term.
                console.log(`MF_MODULE: Local search for "${searchTerm}" found 0. Querying server.`);
                fetchAndDisplayMutualFriends(targetUserId, containerElement, {
                    page: 1, // Server search always starts at page 1
                    searchTerm: searchTerm,
                    isServerFallbackSearch: true
                });
                // The fetch function will call applyClientSideFilter again with fromServerSearchResult = true
                return; // Avoid double UI update
            }

            updateUIStates(containerElement, searchTerm, visibleCount, fromServerSearchResult);
        }


        function updateUIStates(containerElement, currentSearchTerm, visibleItemCount, fromServerSearchResult = false) {
            const DOMElements = cacheDOMElements(containerElement);
            if (!DOMElements.list) return;

            // Read data attributes using their full names as in HTML
            const totalCountForCurrentQuery = parseInt(F(containerElement).data('totalMutualFriendsCount')) || 0;
            const hasMoreForCurrentQuery = F(containerElement).data('hasMoreMutualFriends') === true;
            const apiMessage = F(containerElement).data('apiMessage') || '';
            const profileUserName = F(containerElement).data('profileUserName') || 'this user';

            if (DOMElements.emptyMessage) {
                let emptyMsgText = '';
                const emptyMsgParagraph = F(DOMElements.emptyMessage).find('p')[0] || DOMElements.emptyMessage;

                // If an error message is already showing, don't overwrite with empty message.
                if (DOMElements.errorMessage && !F(DOMElements.errorMessage).hasClass('hidden')) {
                     F(DOMElements.emptyMessage).addClass('hidden');
                } else if (visibleItemCount === 0) {
                    // No items are visible after filtering or loading
                    if (currentSearchTerm) {
                        if (fromServerSearchResult && totalCountForCurrentQuery === 0) {
                            // Server searched for this term and found nothing
                            emptyMsgText = `No mutual friends found matching "${F.utils.sanitizeXMLChars(currentSearchTerm)}" with ${profileUserName}.`;
                        } else if (!fromServerSearchResult) {
                            // Local search for this term found nothing (server search might be pending or already tried)
                            // The message here could be tricky; it might mean "nothing locally, trying server" or "nothing locally, and server already said no"
                            // For now, a generic "no match for search" is safer if server isn't definitive yet.
                            emptyMsgText = `No results for "${F.utils.sanitizeXMLChars(currentSearchTerm)}".`;
                             if (F(containerElement).data('isFetchingServerSearch') === true) {
                                // If we are actively fetching for this term, don't show empty yet, show search loader.
                                emptyMsgText = ''; // Defer to search loader
                            }
                        }
                    } else if (totalCountForCurrentQuery === 0) { // No search term, and total count is 0 (initial load, no mutuals)
                        emptyMsgText = apiMessage && apiMessage.toLowerCase().includes("no mutual friends found")
                            ? F.utils.sanitizeXMLChars(apiMessage)
                            : `No mutual friends found with ${profileUserName}.`;
                    }
                }


                if (emptyMsgText) {
                    F(emptyMsgParagraph).text(emptyMsgText);
                    F(DOMElements.emptyMessage).removeClass('hidden');
                } else {
                    F(DOMElements.emptyMessage).addClass('hidden');
                }
            }

            if (DOMElements.countDisplay) {
                // Display total count relevant to the current view (all items, or server search results)
                // If a client-side filter is active on a larger loaded set, this total might be confusing.
                // Simplest: show total from the last server query.
                if (totalCountForCurrentQuery > 0) {
                     F(DOMElements.countDisplay).text(`(Total: ${totalCountForCurrentQuery})`);
                } else if (currentSearchTerm && visibleItemCount > 0) {
                    F(DOMElements.countDisplay).text(`(Showing: ${visibleItemCount})`); // For client-filtered results
                }
                else {
                    F(DOMElements.countDisplay).text('');
                }
            }

            // Load more button should be visible if:
            // 1. hasMoreForCurrentQuery is true (server indicates more items for the *current query type*)
            // 2. AND (EITHER no search term is active OR a server search was just performed for the active term and it has pagination)
            const canShowLoadMore = hasMoreForCurrentQuery && (!currentSearchTerm || (currentSearchTerm && fromServerSearchResult));

            if (DOMElements.loadMoreContainer) {
                F(DOMElements.loadMoreContainer).toggleClass('hidden', !canShowLoadMore);
            }
            if (DOMElements.loadMoreBtn) {
                F(DOMElements.loadMoreBtn).attr('disabled', !canShowLoadMore);
                if(canShowLoadMore) F(DOMElements.loadMoreBtn).text('Load More');
            }
        }


        function init() {
            F('[data-mutual-friends-for]').each(function() {
                const containerElement = this;
                const targetUserId = F(containerElement).data('mutualFriendsFor');

                if (targetUserId) {
                    const DOMElements = cacheDOMElements(containerElement);

                    // Initial fetch (no search term, page 1)
                    fetchAndDisplayMutualFriends(targetUserId, containerElement, { page: 1, isInitialLoad: true });

                    if (DOMElements.loadMoreBtn) {
                        F(DOMElements.loadMoreBtn).on('click', function() {
                            let currentPage = parseInt(F(containerElement).data('mutualFriendsCurrentPage')) || 1;
                            // Determine if the "Load More" is for a server-searched list or all items
                            const activeSearchTerm = DOMElements.searchInput ? F(DOMElements.searchInput).val().trim().toLowerCase() : '';
                            const wasServerSearchResult = activeSearchTerm && (F(containerElement).data('totalMutualFriendsCount') > 0); // Heuristic

                            fetchAndDisplayMutualFriends(targetUserId, containerElement, {
                                page: currentPage + 1,
                                searchTerm: activeSearchTerm, // Pass term for paginating server search results
                                isPaginating: true,
                                isServerFallbackSearch: !!activeSearchTerm && wasServerSearchResult // Paginate server search if applicable
                            });
                        });
                    }

                    if (DOMElements.searchInput) {
                        F(DOMElements.searchInput).on('input', F.utils.debounce(function() {
                            const searchTerm = F(this).val().trim().toLowerCase();
                            // `applyClientSideFilter` will handle the logic of local vs. server call
                            applyClientSideFilter(containerElement, searchTerm);
                        }, 400));
                    }
                } else {
                    console.warn("MF_MODULE_INIT: Container missing 'data-mutual-friends-for' ID.", containerElement);
                }
            });
        }

        return {
            init: init
        };
    })();

    // DOMContentLoaded initialization
    if (document.readyState === 'complete' || (document.readyState !== 'loading' && !document.documentElement.doScroll)) {
        F.modules.mutualFriends?.init();
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            F.modules.mutualFriends?.init();
        });
    }

})(window.F, window, document);

const NotificationManager = (function() {
    'use strict';

    // ============ CSRF CHANGE 1: Refactor the API request helper ============
    // This private function is updated to automatically add the CSRF token to the body of POST requests.
    async function _apiRequest(endpoint, method = 'POST', body = null, isFormData = false) {
        const options = {
            method: method,
            headers: {
                'Accept': 'application/json',
            }
        };

        // Prepare body and add CSRF token ONLY for state-changing methods like POST
        if (method.toUpperCase() === 'POST') {
            // Use the globally available helper to get the token
            const csrfToken = window.getCsrfToken ? window.getCsrfToken() : null;

            if (!csrfToken) {
                const errorMsg = 'CSRF token not found. Please refresh the page and try again.';
                console.error(`NotificationManager API Error: ${errorMsg}`);
                return { success: false, error: errorMsg };
            }

            if (isFormData) {
                // If the original body is not FormData, create a new one.
                const finalBody = body instanceof FormData ? body : new FormData();
                finalBody.append('csrf_token', csrfToken); // Add token
                options.body = finalBody;
            } else {
                // For JSON body, create a new object if body is null, or spread existing body
                const finalBody = (body && typeof body === 'object' && !(body instanceof FormData)) ? { ...body } : {};
                finalBody.csrf_token = csrfToken; // Add token
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(finalBody);
            }
        } else if (body) { 
            // Handle body for other methods (GET with body is rare, but PUT/PATCH might use this)
            if (isFormData) {
                options.body = body;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
        }

        try {
            const response = await fetch(endpoint, options);
            const result = await response.json(); // Assume server always returns JSON

            if (!response.ok) {
                throw new Error(result.message || result.error || `HTTP error! Status: ${response.status}`);
            }
            return { success: true, data: result };
        } catch (error) {
            console.error(`NotificationManager API Error (${method} ${endpoint}):`, error.message);
            return { success: false, error: error.message };
        }
    }
    // ======================= END OF CSRF CHANGE =======================

    /**
     * Marks all notifications for the current user as read.
     * @param {function} [callback] - Optional callback function (success: boolean, responseDataOrError: any) => void
     * @returns {Promise<object>} - Promise resolving to { success: boolean, data?: any, error?: string }
     */
    async function markAllAsRead(callback) {
        // No change needed here; _apiRequest handles the token automatically.
        const response = await _apiRequest(API_ENDPOINTS.MARK_ALL_NOTIFICATIONS_READ, 'POST');
        if (callback && typeof callback === 'function') {
            callback(response.success, response.success ? response.data : response.error);
        }
        return response;
    }

    /**
     * Marks a single notification as read.
     * @param {string|number} notificationId - The ID of the notification to mark as read.
     * @param {function} [callback] - Optional callback function (success: boolean, responseDataOrError: any) => void
     * @returns {Promise<object>} - Promise resolving to { success: boolean, data?: any, error?: string }
     */
    async function markSingleAsRead(notificationId, callback) {
        // No change needed here; _apiRequest handles the token automatically.
        if (!notificationId) {
            const errorMsg = "Notification ID is required.";
            console.error("NotificationManager:", errorMsg);
            if (callback && typeof callback === 'function') {
                callback(false, errorMsg);
            }
            return { success: false, error: errorMsg };
        }
        const response = await _apiRequest(API_ENDPOINTS.MARK_SINGLE_NOTIFICATION_READ(notificationId), 'POST');
        if (callback && typeof callback === 'function') {
            callback(response.success, response.success ? response.data : response.error);
        }
        return response;
    }

    // Public API
    return {
        markAllAsRead: markAllAsRead,
        markSingleAsRead: markSingleAsRead,
    };
})();

// ============ CSRF CHANGE 2: Ensure the global CSRF helper function is defined ============
// This listener block is from your MutualFriends module. We'll add the helper function here
// to ensure it's defined before any code might need it.
document.addEventListener('DOMContentLoaded', function() {
    // Define the global CSRF token retriever if it doesn't already exist.
    if (!window.getCsrfToken) {
        window.getCsrfToken = () => {
            const tokenElement = document.querySelector('meta[name="csrf-token"]');
            if (!tokenElement) {
                console.error('CSRF token meta tag not found. Please ensure your HTML includes: <meta name="csrf-token" content="...">');
                return null;
            }
            return tokenElement.getAttribute('content');
        };
        console.log("CSRF token helper function initialized.");
    }

    // Initialize the F module as before
    if (window.F && window.F.modules && typeof window.F.modules.mutualFriends?.init === 'function') {
        window.F.modules.mutualFriends.init();
    }
});