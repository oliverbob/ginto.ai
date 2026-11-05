const API_ENDPOINTS = {
	HEADER_DATA: '/post/header-data',
	SINGLE_POST: (postId) => `/post/${postId}`,
	MODAL_POST_DATA: (postId) => `/post/${postId}`,
	MARK_ALL_NOTIFICATIONS_READ: '/post/notifications/mark-all-read',
	MARK_SINGLE_NOTIFICATION_READ: (notificationId) => `/post/notifications/${notificationId}/mark-read`,
	TOGGLE_POST_LIKE: '/post/like',
	ADD_POST_COMMENT: '/post/comment',
	POST_COMMENTS: (postId, page = 1) => `/post/${postId}/comments?page=${page}`,
	DELETE_COMMENT: (commentId) => `/post/comments/${commentId}/delete`,
    EDIT_COMMENT: (commentId) => `/post/comments/${commentId}/edit`,
    UPDATE_POST: (postId) => `/post/${postId}/update`,
    DELETE_POST: (postId) => `/post/${postId}/delete`, // Added endpoint for deleting posts
	CREATE_SHARE_POST: '/post/share', // New endpoint for creating a share
	// MODAL_POST_DATA: (postId) => `/post/${postId}`, // This was a duplicate, removed for clarity.
};

const PFM_MODAL_CONFIG = {
    // Selectors for a potentially new, dedicated post view modal
    // If reusing notification modal, these would point to those elements
    postViewModal: '#pfmPostViewModal', // Example ID for a dedicated modal
    postViewModalTitle: '#pfmPostViewModalTitle',
    postViewModalBody: '#pfmPostViewModalBody',
    postViewModalCloseBtn: '#pfmPostViewModalCloseBtn',
    // Fallback if dedicated modal elements aren't found, we can create one
};

class PostFeedManager {
	constructor(feedContainerId, loadingIndicatorId) {
		this.feedContainerEl = document.getElementById(feedContainerId);
		this.loadingIndicatorEl = document.getElementById(loadingIndicatorId);

		// window.APP_USER_ID should be populated from PHP (e.g., $_SESSION['user_id'])
		this.currentUserId = window.APP_USER_ID || null;
		this.currentUserAvatar = window.APP_USER_AVATAR ||
			(window.globalChatManager && typeof window.globalChatManager._generateFallbackAvatarSVG === 'function' ?
				window.globalChatManager._generateFallbackAvatarSVG(window.APP_USER_FULL_NAME || 'U', 32) :
				this._generateClientFallbackSVG(window.APP_USER_FULL_NAME || 'U', 32));

		if (!this.feedContainerEl) {
			console.error('PostFeedManager: Feed container element not found with ID:', feedContainerId, ". Feed cannot be initialized.");
			return;
		}

		// --- Feed State ---
		this.isLoadingPosts = false;
		this.currentFeedPage = 1;
		this.feedPostsPerPage = 10;
		this.noMoreFeedPosts = false;
        this.iframeEmbedded = false; // Flag to ensure iframe is embedded only once

		// --- AI Code Generation State ---
		this.isAIGenerating = false;
		this.aiAbortController = null;
		this.currentGeneratingAIEditor = {
			instance: null,
			uniquePostId: null,
			element: null,
			previewIframe: null,
		};
		this.aiStreamFirstChunkReceived = false;
        this.editingCommentId = null;


		this._bindScrollListener();
		this.initFeed();
		this._bindDelegatedEventListeners();
		this._bindGlobalAIEditorEventListeners();
	}

	_getPostViewModalElements() {
		const elements = {
			modal: document.getElementById(PFM_MODAL_CONFIG.postViewModal.substring(1)),
			title: document.getElementById(PFM_MODAL_CONFIG.postViewModalTitle.substring(1)),
			body: document.getElementById(PFM_MODAL_CONFIG.postViewModalBody.substring(1)),
			closeBtn: document.getElementById(PFM_MODAL_CONFIG.postViewModalCloseBtn.substring(1)),
		};
		// Check if essential elements exist if we are relying on pre-existing HTML
		// elements.modal && elements.title && elements.body && elements.closeBtn;
		return elements;
	}

	_createPostViewModalStructure() {
		const modalId = PFM_MODAL_CONFIG.postViewModal.substring(1);
		let elements = this._getPostViewModalElements(); // Try to get existing elements first

		// If modal already exists in the DOM
		if (elements.modal) {
			// Ensure event listeners for its content are attached if they weren't previously
			// or if the modal was hidden and shown again without content re-population.
			if (elements.body && !elements.body._modalContentListenersAttached) {
				// console.log("PFM: Modal exists, re-binding content listeners to body.");
				this._bindModalContentEventListeners(elements.body);
			}
			return elements;
		}

		// If modal doesn't exist, create it:
		const modalOverlay = document.createElement('div');
		modalOverlay.id = modalId;
		modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-60 dark:bg-opacity-75 overflow-y-auto h-full w-full z-[60] flex justify-center items-center p-4 pfm-modal-overlay hidden animate-fade-in-up';

		const staticInitialPostItemForBody = `
		<div class="post-item bg-white dark:bg-dark-700 rounded-lg shadow-md">
			<div class="p-4">
				<div class="flex items-center justify-between mb-3">
					<div class="flex items-center">
						<div class="w-10 h-10 rounded-full mr-3 bg-gray-200 dark:bg-dark-600 flex-shrink-0 animate-pulse"></div>
						<div>
							<div class="h-4 bg-gray-200 dark:bg-dark-600 rounded w-32 mb-1 animate-pulse"></div>
							<div class="h-3 bg-gray-200 dark:bg-dark-600 rounded w-24 animate-pulse"></div>
						</div>
					</div>
					<div class="text-gray-400 dark:text-dark-500 animate-pulse">
						<i class="fas fa-ellipsis-h"></i>
					</div>
				</div>
				<div class="h-8 bg-gray-200 dark:bg-dark-600 rounded w-full mb-3 animate-pulse"></div>
				<div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 mb-2">
					<div class="flex items-center animate-pulse">
						<i class="fas fa-thumbs-up text-gray-300 dark:text-dark-500 mr-1"></i>
						<span class="like-count-display h-3 bg-gray-200 dark:bg-dark-600 rounded w-4"></span>
					</div>
					<div class="animate-pulse">
						<span class="comment-count-display-text h-3 bg-gray-200 dark:bg-dark-600 rounded w-16"></span>
					</div>
				</div>
			</div>
			<div class="post-actions flex justify-around border-t border-gray-200 dark:border-dark-600 opacity-50 animate-pulse">
				<div class="flex-1 flex items-center justify-center py-2 px-3 text-gray-500 dark:text-dark-400">
					<i class="fas fa-thumbs-up mr-2"></i> Like
				</div>
				<div class="flex-1 flex items-center justify-center py-2 px-3 text-gray-500 dark:text-dark-400">
					<i class="fas fa-comment-alt mr-2"></i> Comment
				</div>
				<div class="flex-1 flex items-center justify-center py-2 px-3 text-gray-500 dark:text-dark-400">
					<i class="fas fa-share mr-2"></i> Share
				</div>
			</div>
		</div>
		`;

		modalOverlay.innerHTML = `
			<div class="relative bg-white dark:bg-dark-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
				<!-- Header -->
				<div class="flex justify-between items-center px-4 py-3 border-b border-gray-200 dark:border-dark-700">
					<h3 id="${PFM_MODAL_CONFIG.postViewModalTitle.substring(1)}" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
						Post Details
					</h3>
					<button id="${PFM_MODAL_CONFIG.postViewModalCloseBtn.substring(1)}"
							class="text-gray-500 dark:text-gray-400 bg-transparent hover:bg-gray-200 dark:hover:bg-dark-700
								rounded-md p-1.5 focus:outline-none"
							aria-label="Close modal">
						<i class="fas fa-times w-5 h-5"></i>
					</button>
				</div>

				<!-- Body: Contains the post-item. Background matches modal shell. No padding here. -->
				<div id="${PFM_MODAL_CONFIG.postViewModalBody.substring(1)}"
					class="overflow-y-auto flex-grow bg-white dark:bg-dark-800 p-0">
					${staticInitialPostItemForBody}
				</div>

				<!-- Footer: Themed background -->
				<div class="flex items-center px-4 py-2 border-t border-gray-200 dark:border-dark-700
							bg-gray-100 dark:bg-dark-800 rounded-b-lg">
					<span class="text-xs text-gray-600 dark:text-gray-400">
						Built by Bob Reyes
					</span>
				</div>
			</div>
		`;

		document.body.appendChild(modalOverlay);
		elements = this._getPostViewModalElements(); // Get elements for the newly created modal

		// Attach event listeners for the modal shell (close button, overlay click, escape key)
		if (elements.closeBtn) {
			elements.closeBtn.addEventListener('click', () => this._closePostViewModal());
		}
		if (elements.modal) {
			elements.modal.addEventListener('click', (event) => {
				if (event.target === elements.modal) this._closePostViewModal();
			});
		}
		
		const keydownListener = (event) => {
			if (event.key === 'Escape' && elements.modal && !elements.modal.classList.contains('hidden')) {
				this._closePostViewModal();
			}
		};
		// Manage escape listener to prevent duplicates
		if (elements.modal && elements.modal._pfmEscapeListener) {
			document.removeEventListener('keydown', elements.modal._pfmEscapeListener);
		}
		document.addEventListener('keydown', keydownListener);
		if (elements.modal) elements.modal._pfmEscapeListener = keydownListener; // Store for removal

		// **** ATTACH LISTENERS FOR MODAL BODY CONTENT ****
		if (elements.body) {
			// console.log("PFM: New modal created, binding content listeners to body.");
			this._bindModalContentEventListeners(elements.body);
		}

		return elements;
	}

	_bindModalContentEventListeners(modalBodyElement) {
		// Prevent attaching multiple listeners if this method is called more than once for the same element
		if (modalBodyElement._modalContentListenersAttached) {
			return;
		}

		modalBodyElement.addEventListener('click', async (event) => {
			const target = event.target;

			// --- Comment Related Actions ---
			const viewCommentsButton = target.closest('.view-comments-button');
			const loadMoreCommentsButton = target.closest('.load-more-comments-button'); // If you implement "load more"
			const commentSubmitButton = target.closest('.comment-submit-button');
			const editCommentButton = target.closest('.edit-comment-button');
			const deleteCommentButton = target.closest('.delete-comment-button');

			// --- Other Post Actions (Like, Comment Action, Share, Options) ---
			const likeButton = target.closest('.like-button');
			const commentActionButton = target.closest('.comment-action-button'); // The button to open the input
			const shareButton = target.closest('.share-button');
			const postOptionsTrigger = target.closest('.post-options-trigger');
			const editPostButton = target.closest('.edit-post-button'); // From options dropdown
			const deletePostButton = target.closest('.delete-post-button'); // From options dropdown

			if (viewCommentsButton) {
				event.preventDefault();
				this._handleViewComments(viewCommentsButton); // Reuses your existing handler
			} else if (loadMoreCommentsButton) {
				event.preventDefault();
				this._handleLoadMoreComments(loadMoreCommentsButton); // Reuses your existing handler
			} else if (commentSubmitButton) {
				event.preventDefault();
				if (!this.currentUserId) { this._showInternalGenericModal('Authentication Required', 'Please log in to post comments.', 'warning'); return; }
				this._handleSubmitComment(commentSubmitButton); // Reuses
			} else if (editCommentButton) {
				event.preventDefault();
				this._handleEditComment(editCommentButton); // Reuses
			} else if (deleteCommentButton) {
				event.preventDefault();
				this._handleDeleteComment(deleteCommentButton); // Reuses
			} else if (likeButton) {
				event.preventDefault();
				if (!this.currentUserId) { this._showInternalGenericModal('Authentication Required', 'Please log in to like posts.', 'warning'); return; }
				this._handleToggleLike(likeButton); // Reuses
			} else if (commentActionButton) {
				event.preventDefault();
				if (!this.currentUserId) { this._showInternalGenericModal('Authentication Required', 'Please log in to comment.', 'warning'); return; }
				this._handleCommentButtonClick(commentActionButton); // Reuses
			} else if (shareButton) {
				event.preventDefault();
				this._handleSharePost(shareButton); // Reuses
			} else if (postOptionsTrigger) {
				event.preventDefault();
				event.stopPropagation(); // Important for dropdowns
				const postId = postOptionsTrigger.dataset.postId;
				const postItemInModal = postOptionsTrigger.closest('.post-item'); // Ensure class is on the root of post HTML in modal
				const menu = postItemInModal ? postItemInModal.querySelector(`.post-options-menu[data-menu-for-post="${postId}"]`) : null;

				if (menu) {
					// Close other open menus *within the modal body*
					modalBodyElement.querySelectorAll('.post-options-menu:not(.hidden)').forEach(otherMenu => {
						if (otherMenu !== menu) otherMenu.classList.add('hidden');
					});
					menu.classList.toggle('hidden');
				}
			} else if (editPostButton) {
				event.preventDefault();
				const postId = editPostButton.dataset.postId;
				const postItem = editPostButton.closest('.post-item');
				this._handleEditPost(postId, postItem); // Inline edit within the modal
				const menu = editPostButton.closest('.post-options-menu');
				if(menu) menu.classList.add('hidden');
			} else if (deletePostButton) {
				event.preventDefault();
				const postId = deletePostButton.dataset.postId;
				const postItem = deletePostButton.closest('.post-item');
				const success = await this._handleDeletePost(postId, postItem); // Assuming it returns true on success
				const menu = deletePostButton.closest('.post-options-menu');
				if(menu) menu.classList.add('hidden');
				if (success) { // If post was successfully deleted from modal
					this._closePostViewModal(); // Close the modal as the content is gone
				}
			}
		});

		// Listener for Enter key on comment input within the modal
		modalBodyElement.addEventListener('keypress', (event) => {
			if (event.key === 'Enter' && !event.shiftKey && event.target.classList.contains('comment-input')) {
				event.preventDefault();
				const postItem = event.target.closest('.post-item'); // Ensure class is on the root of post HTML
				const submitButton = postItem?.querySelector('.comment-submit-button');
				if (submitButton) this._handleSubmitComment(submitButton, event.target);
			}
		});

		// Add keydown for comment edit cancel/save if those use Enter/Escape
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

		// Global click listener to close post options dropdowns within the modal
		// This listener is attached to the document to catch clicks outside the dropdown.
		const closeOptionsDropdown = (event) => {
			// Check if the click is outside any .post-options-dropdown-container *within the modal body*
			if (!event.target.closest('.post-options-dropdown-container') && modalBodyElement.contains(event.target)) {
				modalBodyElement.querySelectorAll('.post-options-menu:not(.hidden)').forEach(menu => {
					menu.classList.add('hidden');
				});
			} else if (!modalBodyElement.contains(event.target) && !event.target.closest('.post-options-dropdown-container')) {
				// Click is outside the modal body and outside any other dropdown container
				modalBodyElement.querySelectorAll('.post-options-menu:not(.hidden)').forEach(menu => {
					menu.classList.add('hidden');
				});
			}
		};
		// Store the listener on the element to be able to remove it later if needed
		if (modalBodyElement._globalDropdownClickListener) {
			document.removeEventListener('click', modalBodyElement._globalDropdownClickListener);
		}
		document.addEventListener('click', closeOptionsDropdown);
		modalBodyElement._globalDropdownClickListener = closeOptionsDropdown;


		modalBodyElement._modalContentListenersAttached = true;
	}

	_openPostViewModal(modalElements) {
		if (modalElements && modalElements.modal) {
			modalElements.modal.classList.remove('hidden');
			// modalElements.modal.classList.add('animate-fade-in-up'); // Already on the class list
			document.body.style.overflow = 'hidden';
			if (modalElements.closeBtn) modalElements.closeBtn.focus();
		}
	}

	_closePostViewModal() {
		const modalElements = this._getPostViewModalElements(); // This gets existing or newly created.
		if (modalElements && modalElements.modal && !modalElements.modal.classList.contains('hidden')) {
			modalElements.modal.classList.add('hidden');
			document.body.style.overflow = '';

			// Clean up document-level listeners associated with this modal instance
			if (modalElements.body && modalElements.body._globalDropdownClickListener) {
				document.removeEventListener('click', modalElements.body._globalDropdownClickListener);
				delete modalElements.body._globalDropdownClickListener;
				// console.log("PFM: Removed global dropdown click listener for modal.");
			}
			if (modalElements.modal._pfmEscapeListener) { // Assuming _pfmEscapeListener is stored on elements.modal
				document.removeEventListener('keydown', modalElements.modal._pfmEscapeListener);
				delete modalElements.modal._pfmEscapeListener;
				// console.log("PFM: Removed escape key listener for modal.");
			}


			// Reset body and title to placeholder for next opening
			if (modalElements.body) {
				// Replace with the staticInitialPostItemForBody or a simpler loading message
				// For simplicity, just a loading message:
				modalElements.body.innerHTML = this._getStaticInitialModalBodyContent(); // Use a helper
			}
			if (modalElements.title) {
				modalElements.title.textContent = 'Post Details';
			}
		}
	}

	_getStaticInitialModalBodyContent() {
		// This should be the same placeholder/loading HTML used in _createPostViewModalStructure's staticInitialPostItemForBody
		return `
	<div class="post-item bg-white dark:bg-dark-700 rounded-lg shadow-md">
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

	async _handleViewOriginalPostInModal(originalPostId) {
		console.log(`[PFM _handleViewOriginalPostInModal] Method called with originalPostId: ${originalPostId}`); // LOG 3

		// 1. Ensure modal structure is ready and get references to its elements.
		const modalElements = this._createPostViewModalStructure(); 
		if (!modalElements || !modalElements.body || !modalElements.title) { // Added title check
			this._showInternalGenericModal('Error', 'Could not prepare modal to show post.', 'error');
			console.error("[_handleViewOriginalPostInModal] Modal elements not found or incomplete.");
			return;
		}

		// 2. Open the modal (it will initially show "Loading post..." or whatever default you have).
		//    Reset title and body to loading state before opening.
		modalElements.title.textContent = 'Loading Post...';
		modalElements.body.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400">Fetching details...</p>';
		this._openPostViewModal(modalElements); 

		// 3. Fetch the data.
		try {
			// This is a GET request, so we use the standard `fetch`
			const response = await fetch(API_ENDPOINTS.MODAL_POST_DATA(originalPostId)); // Single fetch call

			if (!response.ok) {
				let errorMsg = `HTTP error ${response.status}`;
				try {
					const errorData = await response.json();
					errorMsg = errorData.message || errorMsg;
				} catch (e) {
					// Failed to parse JSON error, use status text or default
					errorMsg = response.statusText || errorMsg;
				}
				throw new Error(errorMsg);
			}

			const result = await response.json();

			if (result.success && result.post) {
				// Populate the modal with the fetched post data.
				this._populatePostViewModal(result.post, modalElements);
			} else {
				throw new Error(result.message || "Post data not found or invalid structure from API.");
			}
		} catch (error) {
			console.error("Error fetching/displaying original post in modal (ID: " + originalPostId + "):", error);
			if (modalElements.body) {
				modalElements.body.innerHTML = `<p class="text-red-500 p-4 text-center">Error: ${this._sanitizeHTML(error.message)}</p>`;
			}
			if (modalElements.title) {
				modalElements.title.textContent = 'Error Loading Post';
			}
		}
	}

	// In PostFeedManager.js
	async _handleSharePost(buttonElement) {
		const postIdToShare = buttonElement.dataset.postId;
		console.log('[PFM _handleSharePost] Clicked Share. postIdToShare from button dataset:', postIdToShare, '(Type:', typeof postIdToShare, ')'); // Log 1

		if (!postIdToShare || postIdToShare === "undefined" || postIdToShare.trim() === "") { // More robust check
			this._showInternalGenericModal('Error', 'Cannot share: Original Post ID missing from button attribute or is invalid.', 'error');
			console.error('[PFM _handleSharePost] ERROR: postIdToShare is invalid or missing.'); // Log 1a
			return;
		}

		if (window.SmartFed && typeof window.SmartFed.openPostModal === 'function') {
			console.log('[PFM _handleSharePost] Calling SmartFed.openPostModal with context:', { isSharing: true, originalPostId: postIdToShare }); // Log 2
			window.SmartFed.openPostModal({ isSharing: true, originalPostId: postIdToShare });
		} else {
			this._showInternalGenericModal('Error', 'Could not open the post creation form.', 'error');
			console.error('[PFM _handleSharePost] ERROR: SmartFed.openPostModal not found or not a function.'); // Log 2a
		}
	}

	// --- MODAL UTILITIES (INTERNAL TO POSTFEEDMANAGER) ---
	_createModal(id, title, message, buttonsConfig = []) {
		const existingModal = document.getElementById(id);
		if (existingModal) existingModal.remove();

		const modalOverlay = document.createElement('div');
		modalOverlay.id = id;
		modalOverlay.className = 'fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex justify-center items-center pfm-modal-overlay';

		let titleColorClass = 'text-gray-900 dark:text-white';
		if (buttonsConfig.some(btn => btn.type === 'danger')) titleColorClass = 'text-red-600 dark:text-red-400';
		else if (buttonsConfig.some(btn => btn.type === 'success')) titleColorClass = 'text-green-600 dark:text-green-400';
		else if (buttonsConfig.some(btn => btn.type === 'warning')) titleColorClass = 'text-yellow-600 dark:text-yellow-400';


		modalOverlay.innerHTML = `
            <div class="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-dark-800 pfm-modal-content animate-fade-in-up">
                <div class="mt-3 text-center">
                    <h3 class="text-lg leading-6 font-medium ${titleColorClass} pfm-modal-title">${this._sanitizeHTML(title)}</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-600 dark:text-gray-300 pfm-modal-message">${this._sanitizeHTML(message, true)}</p>
                    </div>
                    <div class="pfm-modal-actions items-center px-4 py-3 space-x-2 sm:space-x-4">
                        <!-- Buttons will be appended here -->
                    </div>
                </div>
            </div>
        `;
		document.body.appendChild(modalOverlay);
		document.body.style.overflow = 'hidden';

		const modalContent = modalOverlay.querySelector('.pfm-modal-content');
		modalOverlay.addEventListener('click', (e) => {
			if (e.target === modalOverlay) {
				const cancelButton = buttonsConfig.find(b => b.isCancel);
				if (cancelButton && cancelButton.onClick) {
					cancelButton.onClick();
				}
				this._removeModal(id);
			}
		});

		return modalOverlay.querySelector('.pfm-modal-actions');
	}

	_removeModal(id) {
		const modal = document.getElementById(id);
		if (modal) {
			const modalContent = modal.querySelector('.pfm-modal-content');
			if (modalContent) {
				modalContent.classList.remove('animate-fade-in-up');
				modalContent.classList.add('animate-fade-out-down');
				setTimeout(() => {
					modal.remove();
					if (!document.querySelector('.pfm-modal-overlay')) {
						document.body.style.overflow = '';
					}
				}, 300);
			} else {
				modal.remove();
				if (!document.querySelector('.pfm-modal-overlay')) {
					document.body.style.overflow = '';
				}
			}
		}
	}

	_showInternalConfirmModal(title, message, confirmText = 'Confirm', cancelText = 'Cancel', confirmType = 'danger') {
		return new Promise((resolve) => {
			const modalId = 'pfm-confirm-modal-' + Date.now();

			let confirmClasses = 'px-4 py-2 text-white font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-dark-800';
			if (confirmType === 'danger') {
				confirmClasses += ' bg-red-600 hover:bg-red-700 focus:ring-red-500';
			} else if (confirmType === 'success') {
				confirmClasses += ' bg-green-500 hover:bg-green-600 focus:ring-green-500';
			} else {
				confirmClasses += ' bg-blue-500 hover:bg-blue-600 focus:ring-blue-500';
			}

			const buttons = [{
					text: cancelText,
					class: 'px-4 py-2 bg-gray-200 text-gray-800 dark:bg-dark-600 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-dark-500 rounded-md font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 dark:focus:ring-offset-dark-800',
					onClick: () => {
						this._removeModal(modalId);
						resolve(false);
					},
					isCancel: true
				},
				{
					text: confirmText,
					class: confirmClasses,
					onClick: () => {
						this._removeModal(modalId);
						resolve(true);
					},
					type: confirmType
				}
			];

			const actionsContainer = this._createModal(modalId, title, message, buttons);

			buttons.forEach(btnConfig => {
				const button = document.createElement('button');
				button.textContent = btnConfig.text;
				button.className = btnConfig.class;
				button.addEventListener('click', btnConfig.onClick);
				actionsContainer.appendChild(button);
			});
			const confirmButtonEl = actionsContainer.querySelector(`button.${confirmType === 'danger' ? 'bg-red-600' : (confirmType === 'success' ? 'bg-green-500' : 'bg-blue-500')}`);
			if (confirmButtonEl) confirmButtonEl.focus();
		});
	}

	_showInternalGenericModal(title, message, type = 'info') {
		return new Promise((resolve) => {
			const modalId = 'pfm-generic-modal-' + Date.now();
			let buttonClass = 'bg-blue-500 hover:bg-blue-600 focus:ring-blue-500'; // default for info

			switch (type) {
				case 'success':
					buttonClass = 'bg-green-500 hover:bg-green-600 focus:ring-green-500';
					break;
				case 'warning':
					buttonClass = 'bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-500 text-white';
					break;
				case 'error':
					buttonClass = 'bg-red-600 hover:bg-red-700 focus:ring-red-500';
					break;
			}

			const buttons = [{
				text: 'OK',
				class: `px-6 py-2 text-white font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-dark-800 ${buttonClass}`,
				onClick: () => {
					this._removeModal(modalId);
					resolve(true);
				},
				type: type,
				isCancel: true
			}];

			const actionsContainer = this._createModal(modalId, title, message, buttons);
			const okButton = document.createElement('button');
			okButton.textContent = 'OK';
			okButton.className = buttons[0].class;
			okButton.addEventListener('click', buttons[0].onClick);
			actionsContainer.appendChild(okButton);
			okButton.focus();
		});
	}

	// --- API & SECURITY HELPERS ---

	/**
	 * Retrieves the CSRF token from the meta tag in the document's head.
	 * @returns {string|null} The CSRF token, or null if not found.
	 */
	_getCsrfToken() {
		const tokenElement = document.querySelector('meta[name="csrf-token"]');
		if (!tokenElement) {
			console.error('CSRF token meta tag not found. POST requests will fail.');
			return null;
		}
		return tokenElement.getAttribute('content');
	}

	/**
	 * A wrapper for the native fetch API that automatically handles CSRF tokens for POST requests.
	 * @param {string} url The URL to fetch.
	 * @param {object} options The options object for the fetch call.
	 * @returns {Promise<Response>} The promise returned by fetch.
	 */
	async _apiFetch(url, options = {}) {
		const method = options.method ? options.method.toUpperCase() : 'GET';

		if (method === 'POST') {
			const csrfToken = this._getCsrfToken();
			if (!csrfToken) {
				this._showInternalGenericModal('Security Error', 'CSRF token is missing. The request cannot be completed. Please refresh the page.', 'error');
				return Promise.reject(new Error('CSRF token is missing. Request blocked.'));
			}

			// Ensure headers object exists for modification
			if (!options.headers) {
				options.headers = {};
			}

			if (options.body instanceof FormData) {
				// Standard for form data is to append the token as a field.
				options.body.append('csrf_token', csrfToken);
			} else {
				// Standard for AJAX/JSON requests is to use a custom header.
				options.headers['X-CSRF-TOKEN'] = csrfToken;
			}
		}

		return fetch(url, options);
	}

	// --- INITIALIZATION AND CORE FEED LOGIC ---
	initFeed() {
		if (!this.feedContainerEl) return;
		this.currentFeedPage = 1;
		this.noMoreFeedPosts = false;
		this.isLoadingPosts = false;
        this.iframeEmbedded = false; // Reset iframe flag on re-init
		this.feedContainerEl.innerHTML = '';
		this.loadPosts();
	}

	_bindScrollListener() {
		window.addEventListener('scroll', () => {
			if (!this.feedContainerEl || this.noMoreFeedPosts || this.isLoadingPosts) return;
			const {
				scrollTop,
				scrollHeight,
				clientHeight
			} = document.documentElement;
			if (scrollTop + clientHeight >= scrollHeight - 400) {
				this.loadPosts();
			}
		});
	}

	async loadPosts() {
		if (this.isLoadingPosts || this.noMoreFeedPosts) return;
		this.isLoadingPosts = true;

		// START: Prepend iframe for featured video (if applicable)
		if (this.feedContainerEl && this.currentFeedPage === 1 && !this.iframeEmbedded) {
			this.iframeEmbedded = true; // Set flag early

			const videoPlaceholder = document.createElement('div');
			videoPlaceholder.id = 'featured-video-placeholder';
			videoPlaceholder.style.textAlign = 'center';
			videoPlaceholder.style.padding = '20px 0';
			videoPlaceholder.style.minHeight = '200px';
			videoPlaceholder.style.display = 'flex';
			videoPlaceholder.style.alignItems = 'center';
			videoPlaceholder.style.justifyContent = 'center';
			videoPlaceholder.style.border = '1px dashed #ccc';
			videoPlaceholder.style.marginBottom = '1rem';
			videoPlaceholder.innerHTML = '<p>Loading featured video...</p>';
			
			// Find the first actual post-item or the loading indicator to insert before it
			// This ensures the video is truly at the top, above even a potential "No posts yet" message if that gets added first.
			const firstPostOrIndicator = this.feedContainerEl.querySelector('.post-item, p.text-center.text-gray-500, #loadingIndicator');
			if (firstPostOrIndicator) {
				this.feedContainerEl.insertBefore(videoPlaceholder, firstPostOrIndicator);
			} else {
				this.feedContainerEl.prepend(videoPlaceholder);
			}

			// This is a GET request, so we use standard fetch
			fetch('/ads/featured') // YOUR SERVER ENDPOINT
				.then(response => {
					if (!response.ok) {
						throw new Error(`Network response was not ok: ${response.statusText}`);
					}
					return response.json();
				})
				.then(data => {
					if (data.success && data.embedHtml) {
						videoPlaceholder.innerHTML = data.embedHtml;
						// Adjust placeholder styles if needed after content load
						videoPlaceholder.style.padding = '0';
						videoPlaceholder.style.border = 'none';
						videoPlaceholder.style.minHeight = 'auto';
						videoPlaceholder.style.display = 'block';
					} else {
						throw new Error(data.message || 'Invalid video HTML received from server.');
					}
				})
				.catch(error => {
					console.error('Failed to load featured video:', error);
					videoPlaceholder.innerHTML = '<p style="color: red;">Could not load featured video. Please try refreshing.</p>';
					// Optionally remove the placeholder if it fails badly, or leave the error.
					// setTimeout(() => videoPlaceholder.remove(), 5000); 
				});
		}
		// END: Prepend iframe

		if (this.loadingIndicatorEl) {
			this.loadingIndicatorEl.innerHTML = '<div class="loading-spinner inline-block"></div> <span class="ml-2">Loading posts...</span>';
			this.loadingIndicatorEl.classList.remove('hidden');
		}

		const fetchUrl = `${window.location.pathname.replace(/\/$/, '')}/feed?page=${this.currentFeedPage}&limit=${this.feedPostsPerPage}`;
		try {
			// This is a GET request, so we use standard fetch
			const response = await fetch(fetchUrl);
			if (!response.ok) {
				const errorData = await response.json().catch(() => ({
					message: "Failed to parse server error."
				}));
				throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
			}
			const result = await response.json();

			// console.log(`[PostFeedManager loadPosts] Page ${this.currentFeedPage} API Response:`, JSON.stringify(result, null, 2)); // DEBUG: Log API response

			if (result.success && Array.isArray(result.posts)) {
				if (this.loadingIndicatorEl) this.loadingIndicatorEl.classList.add('hidden');

				if (result.posts.length === 0) {
					this.noMoreFeedPosts = true;
					// Check if the feed is truly empty (only iframe or nothing else)
					const nonIframeChildren = Array.from(this.feedContainerEl.children).filter(child => child.id !== 'featured-video-placeholder' && !child.classList.contains('post-item'));
					const actualPostItemsCount = this.feedContainerEl.querySelectorAll('.post-item').length;

					if (this.currentFeedPage === 1 && actualPostItemsCount === 0) {
						// If there's an iframe, allow 1 child if it's the iframe. If no iframe, 0 children.
						if (!this.iframeEmbedded || (this.iframeEmbedded && this.feedContainerEl.children.length <=1 && this.feedContainerEl.querySelector('#featured-video-placeholder'))) {
							// Only add "No posts yet" if it's not already there from a previous empty state
							if (!this.feedContainerEl.querySelector('p.no-posts-message')) {
								this.feedContainerEl.insertAdjacentHTML('beforeend', '<p class="text-center text-gray-500 dark:text-gray-400 py-8 no-posts-message">No posts yet.</p>');
							}
						}
					} else if (this.loadingIndicatorEl) { // Only show "no more posts" if there were posts before
						this.loadingIndicatorEl.innerHTML = `<span class="dark:text-gray-300 text-sm">No more posts.</span>`;
						this.loadingIndicatorEl.classList.remove('hidden');
					}
				} else {
					// Remove "No posts yet" message if it exists and we are adding posts
					const noPostsMessage = this.feedContainerEl.querySelector('p.no-posts-message');
					if (noPostsMessage) noPostsMessage.remove();

					for (const dbPost of result.posts) {
						// CRITICAL: dbPost from the API must contain `original_post` if post_type is 'share'
						// console.log(`[PostFeedManager loadPosts] Processing dbPost for _createPostElement:`, JSON.stringify(dbPost, null, 2)); // DEBUG
						
						const postElement = this._createPostElement(dbPost);
						if (this.feedContainerEl && postElement) {
							this.feedContainerEl.appendChild(postElement);
							// Monaco initialization for AI code posts (not for shares embedding AI code)
							if (dbPost.post_type === 'ai_code' && dbPost.code_language) {
								// This await is fine, it ensures Monaco for one post is set up before moving to the next.
								await this._initializeReadOnlyMonacoForFeedPost(postElement, dbPost.id, dbPost.content, dbPost.code_language);
							}
						}
					}
					this.currentFeedPage++;
					if (result.posts.length < this.feedPostsPerPage || (result.pagination && result.pagination.current_page >= result.pagination.total_pages)) {
						this.noMoreFeedPosts = true;
						if (this.loadingIndicatorEl) {
							this.loadingIndicatorEl.innerHTML = '<span class="dark:text-gray-300 text-sm">No more posts.</span>';
							this.loadingIndicatorEl.classList.remove('hidden');
						}
					}
				}
			} else {
				throw new Error(result.message || "Invalid server response structure.");
			}
		} catch (error) {
			console.error('PostFeedManager Error loading posts:', error);
			if (this.loadingIndicatorEl) {
				this.loadingIndicatorEl.innerHTML = `<span class="text-red-500 text-sm">Error: ${error.message}</span>`;
				this.loadingIndicatorEl.classList.remove('hidden'); // Show the error
			}
			// Check if feed is empty to show error message there too
			const actualPostItemsCount = this.feedContainerEl.querySelectorAll('.post-item').length;
			if (this.currentFeedPage === 1 && actualPostItemsCount === 0) {
				if (!this.iframeEmbedded || (this.iframeEmbedded && this.feedContainerEl.children.length <=1 && this.feedContainerEl.querySelector('#featured-video-placeholder'))) {
					if (!this.feedContainerEl.querySelector('p.error-loading-posts')) {
						this.feedContainerEl.insertAdjacentHTML('beforeend', `<p class="text-center text-red-500 py-8 error-loading-posts">Could not load posts. ${this._sanitizeHTML(error.message)}</p>`);
					}
				}
			}
		} finally {
			this.isLoadingPosts = false;
		}
	}

	async prependNewPost(postDataFromAPI) {

		console.log("[prependNewPost] Received postDataFromAPI:", JSON.stringify(postDataFromAPI, null, 2)); // ADD THIS

		if (!this.feedContainerEl || !postDataFromAPI || typeof postDataFromAPI.id === 'undefined') return;
		const postElement = this._createPostElement(postDataFromAPI);
		if (postElement) {
			const noPostsMessage = this.feedContainerEl.querySelector('p.text-center.text-gray-500');
			if (noPostsMessage && this.feedContainerEl.children.length <= (this.iframeEmbedded ? 2 : 1)) noPostsMessage.remove(); // Account for iframe
            
            // Prepend after iframe if it exists
            if (this.iframeEmbedded && this.feedContainerEl.firstChild && this.feedContainerEl.firstChild.tagName === 'IFRAME') {
                this.feedContainerEl.insertBefore(postElement, this.feedContainerEl.children[1]);
            } else {
			    this.feedContainerEl.prepend(postElement);
            }

			if (postDataFromAPI.post_type === 'ai_code' && postDataFromAPI.code_language) {
				await this._initializeReadOnlyMonacoForFeedPost(postElement, postDataFromAPI.id, postDataFromAPI.content, postDataFromAPI.code_language);
			}
			if (this.feedContainerEl.offsetParent !== null) postElement.scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
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
	_createPostElement(dbPost) {
		const postElement = document.createElement('div');
		postElement.className = 'post-item bg-white dark:bg-dark-700 rounded-lg shadow mb-4 fade-in';
		postElement.dataset.postId = dbPost.id;
		const postOwnerId = dbPost.user_id || (dbPost.user ? dbPost.user.id : null);
		postElement.dataset.postOwnerId = postOwnerId;

		const rawDisplayName = this._getDisplayName(dbPost) || dbPost.username || 'User';
		const userAvatar = dbPost.user_avatar || this._generateClientFallbackSVG(rawDisplayName, 40);
		const displayName = this._sanitizeHTML(rawDisplayName || 'User');
		
		let optionsDropdownHTML = '';
		if (this.currentUserId && postOwnerId && parseInt(this.currentUserId) === parseInt(postOwnerId)) {
			if (dbPost.post_type === 'share') {
				optionsDropdownHTML = `
					<div class="relative post-options-dropdown-container">
						<button class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-full p-2 post-options-trigger" aria-label="Post options" data-post-id="${dbPost.id}">
							<i class="fas fa-ellipsis-h"></i>
						</button>
						<div class="post-options-menu absolute right-0 mt-1 w-48 bg-white dark:bg-dark-700 rounded-md shadow-lg py-1 z-20 hidden" data-menu-for-post="${dbPost.id}">
							<button class="edit-post-button w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600 flex items-center" data-post-id="${dbPost.id}" data-post-type="share">
								<i class="fas fa-comment-edit mr-2 w-4 text-center"></i>Edit Share Text
							</button>
							<button class="delete-post-button w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-700 dark:hover:text-white flex items-center" data-post-id="${dbPost.id}">
								<i class="fas fa-trash-alt mr-2 w-4 text-center"></i>Delete Share
							</button>
						</div>
					</div>`;
			} else {
				optionsDropdownHTML = `
					<div class="relative post-options-dropdown-container">
						<button class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-full p-2 post-options-trigger" aria-label="Post options" data-post-id="${dbPost.id}">
							<i class="fas fa-ellipsis-h"></i>
						</button>
						<div class="post-options-menu absolute right-0 mt-1 w-40 bg-white dark:bg-dark-700 rounded-md shadow-lg py-1 z-20 hidden" data-menu-for-post="${dbPost.id}">
							<button class="edit-post-button w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600 flex items-center" data-post-id="${dbPost.id}" data-post-type="${dbPost.post_type || 'text'}">
								<i class="fas fa-edit mr-2 w-4 text-center"></i>Edit Post
							</button>
							<button class="delete-post-button w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-700 dark:hover:text-white flex items-center" data-post-id="${dbPost.id}">
								<i class="fas fa-trash-alt mr-2 w-4 text-center"></i>Delete Post
							</button>
						</div>
					</div>`;
			}
		}

		let locationHTML = '';
		if (dbPost.location_name) {
			locationHTML = `
				<span class="font-normal text-gray-500 dark:text-gray-400">
					is at
					<i class="fas fa-map-marker-alt text-red-500 mx-1"></i>
					<strong class="font-semibold text-gray-800 dark:text-gray-200">${this._sanitizeHTML(dbPost.location_name)}</strong>
				</span>
			`;
		}
		
		let shareDescription = '';
		if (dbPost.post_type === 'share') {
			const author = dbPost.original_post;
			if (author) {
				const authorNameRaw = this._getDisplayName(author) || author.username || '';
				let possessivePronoun;
				switch (author.gender) {
					case 'male': possessivePronoun = 'his'; break;
					case 'female': possessivePronoun = 'her'; break;
					default: possessivePronoun = 'their'; break;
				}
				if (rawDisplayName === authorNameRaw) {
					shareDescription = `shared ${possessivePronoun} own post:`;
				} else {
					shareDescription = `shared ${this._sanitizeHTML(authorNameRaw)}'s post:`;
				}
			}
		}

		const userInfoHTML = `
		<div class="flex items-center justify-between mb-3">
			<div class="flex items-center">
				<img src="${userAvatar}" alt="${displayName}" class="w-10 h-10 rounded-full mr-3 object-cover flex-shrink-0">
				<div>
					<div class="block">
						<a href="/profile/${postOwnerId}" class="font-semibold dark:text-white hover:underline">${displayName}</a>
						${locationHTML ? `<span class="ml-1.5">${locationHTML}</span>` : ''} 
						${shareDescription ? `<span class="text-sm text-gray-900 dark:text-white"> ${shareDescription}</span>` : ''}
					</div> 
					<p class="text-xs text-gray-500 dark:text-gray-400 flex items-center mt-0.5">
						<span class="post-timeago" data-timestamp="${dbPost.created_at}">${this._timeAgo(dbPost.created_at)}</span>
						<span class="mx-1">·</span>
						<i class="fas ${this._getIconForVisibility(dbPost.visibility)} post-visibility-icon" title="${this._sanitizeHTML(dbPost.visibility || 'public')}"></i>
						<span class="post-header-editable-visibility-select-container hidden ml-0.5 items-center"></span>
					</p>
				</div>
			</div>
			${optionsDropdownHTML}
		</div>`;

		let userAddedContentHTML = '';
		if (dbPost.post_type !== 'ai_code') {
			const hasContent = dbPost.content && dbPost.content.trim() !== '';
			const isShare = dbPost.post_type === 'share';
			let classes = 'post-content-display mb-3';
			if (isShare) classes += ' post-share-comment-display';
			if (hasContent) classes += ' dark:text-gray-200 whitespace-pre-wrap';
			else classes += ' hidden';
			const processedContent = hasContent ? this._linkifyText(dbPost.content) : '';
			userAddedContentHTML = `<div class="${classes}">${processedContent}</div>`;
		}

		const linkPreviewHTML = this._createLinkPreviewElement(dbPost);

		let sharedPostEmbedHTML = '';
		if (dbPost.post_type === 'share') {
			if (dbPost.original_post && typeof dbPost.original_post === 'object' && dbPost.original_post.id) {
				const original = dbPost.original_post;
				const originalAuthorNameRaw = this._getDisplayName(original) || original.username || 'User';
				const originalAuthorAvatar = original.user_avatar || this._generateClientFallbackSVG(originalAuthorNameRaw, 32);
				const originalDisplayName = this._sanitizeHTML(originalAuthorNameRaw || 'User');
				const originalPostDirectLink = `${window.location.origin}/post/${original.id}`;
				const originalVisibility = original.visibility || 'public';
            	const originalVisibilityIcon = this._getIconForVisibility(originalVisibility);

				// --- FIX HIGHLIGHT ---
				// This logic correctly creates the location HTML for the *embedded* post.
				let originalLocationHTML = '';
				if (original.location_name) {
					originalLocationHTML = `
						<span class="font-normal text-gray-500 dark:text-gray-400 text-sm">
							was at
							<i class="fas fa-map-marker-alt text-red-500 mx-1"></i>
							<strong class="font-semibold text-gray-800 dark:text-gray-200">${this._sanitizeHTML(original.location_name)}</strong>
						</span>
					`;
				}
				// --- END FIX HIGHLIGHT ---

				let originalContentPreviewHTML = '';
				if (original.post_type === 'ai_code' && original.code_language) {
					originalContentPreviewHTML = `<div class="text-sm dark:text-gray-300 mt-1 p-2 bg-gray-50 dark:bg-dark-800 rounded pointer-events-none">Shared <span class="font-medium">${this._sanitizeHTML(original.code_language)}</span> code.</div>`;
				} else if (original.is_live_stream && original.stream_playback_uid) {
					originalContentPreviewHTML = `<div class="mt-2 p-2 bg-gray-50 dark:bg-dark-800 rounded text-sm dark:text-gray-300 pointer-events-none">Shared a live stream.</div>`;
				} else if (original.image) {
					const mediaUrl = original.image;
					if (mediaUrl.toLowerCase().endsWith('.mp4') || mediaUrl.toLowerCase().endsWith('.webm')) {
						originalContentPreviewHTML = `<div class="mt-2 p-2 bg-gray-900 dark:bg-dark-800 rounded text-sm dark:text-gray-300 pointer-events-none flex items-center justify-center text-white"><i class="fas fa-play-circle text-2xl mr-2"></i> Shared a video.</div>`;
					} else {
						originalContentPreviewHTML = `<div class="mt-2 pointer-events-none"><img src="${this._sanitizeHTML(mediaUrl)}" alt="Shared media" class="rounded-lg max-h-[200px] w-full object-contain my-1 mx-auto block border dark:border-dark-500"></div>`;
					}
					if (original.content && original.content.trim() !== '') {
						originalContentPreviewHTML += `<div class="text-sm dark:text-gray-300 whitespace-pre-wrap mt-1 pt-1 border-t dark:border-dark-500 pointer-events-none">${this._sanitizeHTML(original.content.substring(0, 150) + (original.content.length > 150 ? '...' : ''), true)}</div>`;
					}
				} else if (original.content && original.content.trim() !== '') {
					originalContentPreviewHTML = `<div class="text-sm dark:text-gray-300 whitespace-pre-wrap mt-1 pointer-events-none">${this._sanitizeHTML(original.content.substring(0, 250) + (original.content.length > 250 ? '...' : ''), true)}</div>`;
				}

				sharedPostEmbedHTML = `
				<div class="original-shared-post-embed cursor-pointer border border-gray-200 dark:border-dark-600 rounded-lg p-3 my-2 hover:bg-gray-100 dark:hover:bg-dark-700 transition-colors duration-150" 
					data-action="view-original-post" data-original-post-id="${original.id}">
					<div class="flex items-center mb-2 pointer-events-none">
						<img src="${originalAuthorAvatar}" alt="${originalDisplayName}" class="w-8 h-8 rounded-full mr-2 object-cover flex-shrink-0"> 
						<div class="pointer-events-none">
							<span class="font-semibold text-sm text-gray-900 dark:text-white">${originalDisplayName}</span>
							
							<!-- FIX HIGHLIGHT: The location HTML is rendered here. -->
							${originalLocationHTML ? `<div class="mt-1">${originalLocationHTML}</div>` : ''}

							<p class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center">
								${this._timeAgo(original.created_at || Date.now())}
								<span class="mx-1">·</span>
								<i class="fas ${originalVisibilityIcon} mr-1" title="Original post visibility: ${this._sanitizeHTML(originalVisibility)}"></i>
							</p>
						</div>
					</div>
					${originalContentPreviewHTML}
					<div class="text-right mt-2">
						<a href="${originalPostDirectLink}" class="text-xs text-blue-500 dark:text-blue-400 hover:underline inline-block">View original post</a>
					</div>
				</div>`;
			} else {
				sharedPostEmbedHTML = `<div class="original-shared-post-embed border border-red-300 dark:border-red-700 rounded-lg p-3 my-2 bg-red-50 dark:bg-red-900_"><p class="text-red-700 dark:text-red-300 text-sm">Could not load the original shared content.</p></div>`;
			}
		}

		const inlineEditFormHTML = `<div class="post-inline-edit-form ${dbPost.post_type === 'share' ? 'post-share-inline-edit-form' : ''} hidden space-y-3 mb-3"></div>`;
		
		let primaryMediaHTML = '';
		if (dbPost.post_type === 'ai_code' && dbPost.code_language) {
			const editorDisplayContainerId = `editorDisplayContainer-feed-${dbPost.id}`;
			primaryMediaHTML = `<div class="ai-code-block my-2"><p class="text-sm text-gray-600 dark:text-gray-400 mb-1">A website I built with Sai Code (<span class="post-code-language-display">${this._sanitizeHTML(dbPost.code_language)}</span>):</p><div id="${editorDisplayContainerId}" class="code-editor-display-container min-h-[100px] rounded bg-gray-50 dark:bg-dark-800"><div class="p-2 text-xs text-gray-400 dark:text-gray-500">Loading code view...</div></div>${(dbPost.original_prompt && dbPost.original_prompt.trim() !== '') ? `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic post-original-prompt-display">Prompt: ${this._sanitizeHTML(dbPost.original_prompt)}</p>` : '<p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic post-original-prompt-display hidden"></p>'}</div>`;
		} else if (dbPost.post_type !== 'share') { 
			if (dbPost.is_live_stream && dbPost.stream_playback_uid) {
				const playbackUid = this._sanitizeHTML(dbPost.stream_playback_uid);
				let posterUrl = this._sanitizeHTML(dbPost.image || '');
				if (!posterUrl || !posterUrl.includes('videodelivery.net')) posterUrl = `https://videodelivery.net/${playbackUid}/thumbnails/thumbnail.jpg?time=1s&height=720&width=1280`;
				let streamBaseUrl = "https://iframe.videodelivery.net";
				if (playbackUid.includes('.')) { const domainPart = playbackUid.substring(0, playbackUid.indexOf('/')); if (domainPart.includes('cloudflarestream.com')) streamBaseUrl = `https://iframe.${domainPart}`; }
				const actualVideoId = playbackUid.includes('/') ? playbackUid.substring(playbackUid.indexOf('/') + 1) : playbackUid;
				primaryMediaHTML = `<div class="cloudflare-stream-player-container post-media-display rounded-lg my-2" style="position: relative; padding-top: 56.25%; height: 0; overflow: hidden; background-color: #000;"><iframe src="${streamBaseUrl}/${actualVideoId}?autoplay=true&muted=false&preload=auto&poster=${encodeURIComponent(posterUrl)}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true" loading="lazy" title="Live Stream Video Player"></iframe></div>`;
			} else if (dbPost.image) {
				const mediaUrl = dbPost.image.toLowerCase();
				const sanitizedUrl = this._sanitizeHTML(dbPost.image);
				if (mediaUrl.endsWith('.mp4') || mediaUrl.endsWith('.webm') || mediaUrl.endsWith('.mov')) {
					primaryMediaHTML = `<video src="${sanitizedUrl}" controls loop muted playsinline class="post-media-display rounded-lg max-h-[500px] w-full bg-black my-2 mx-auto block" title="Post video">Your browser does not support the video tag.</video>`;
				} else {
					primaryMediaHTML = `<img src="${sanitizedUrl}" alt="Post media" class="post-media-display rounded-lg max-h-[500px] w-full object-contain my-2 mx-auto block">`;
				}
			} else {
				primaryMediaHTML = `<div class="post-media-display hidden"></div>`;
			}
		}

		const likeCount = dbPost.like_count || 0;
		const commentCount = dbPost.comment_count || 0;
		const statsHTML = `<div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 mb-2"><div><i class="fas fa-thumbs-up text-facebook dark:text-blue-400 mr-1"></i><span class="like-count-display">${likeCount}</span></div><div><span class="comment-count-display-text hover:underline cursor-pointer" data-post-id="${dbPost.id}">${commentCount} comment${commentCount !== 1 ? 's' : ''}</span></div></div>`;
		const isLikedClass = dbPost.is_liked_by_current_user ? 'text-facebook dark:text-blue-400' : 'text-gray-600 dark:text-gray-400';
		const actionsHTML = `<div class="post-actions flex justify-around border-t border-gray-200 dark:border-dark-600"><button class="like-button flex-1 flex items-center justify-center py-2 px-3 ${isLikedClass} hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${dbPost.id}"><i class="fas fa-thumbs-up mr-2"></i> Like</button><button class="comment-action-button flex-1 flex items-center justify-center py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${dbPost.id}"><i class="fas fa-comment-alt mr-2"></i> Comment</button><button class="share-button flex-1 flex items-center justify-center py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600" data-post-id="${dbPost.id}"><i class="fas fa-share mr-2"></i> Share</button></div>`;
		const commentInputHTML = `<div class="comment-input-section p-3 border-t border-gray-200 dark:border-dark-600 hidden"><div class="flex items-start space-x-2"><img src="${this.currentUserAvatar}" alt="Your avatar" class="w-8 h-8 rounded-full object-cover flex-shrink-0"><textarea class="comment-input flex-1 p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none dark:bg-dark-600 dark:text-white" rows="1" placeholder="Write a comment..."></textarea><button class="comment-submit-button bg-facebook hover:bg-facebook-dark text-white font-semibold py-2 px-3 rounded-lg text-sm ml-2" data-post-id="${dbPost.id}"><i class="fa-solid fa-paper-plane"></i></button></div></div>`;
		const viewCommentsButtonHTML = `<div class="view-comments-trigger p-3 pt-1 ${commentCount === 0 ? 'hidden' : ''}"><button class="view-comments-button text-sm text-gray-600 dark:text-gray-400 hover:underline" data-post-id="${dbPost.id}" data-original-text="View ${commentCount} comment${commentCount !== 1 ? 's' : ''}">View ${commentCount} comment${commentCount !== 1 ? 's' : ''}</button></div>`;
		const commentsListAreaHTML = `<div class="comments-list-area p-3 pt-0 space-y-2 hidden"></div>`;

		postElement.innerHTML = `
			<div class="p-4">
				${userInfoHTML}
				${userAddedContentHTML}
				${linkPreviewHTML}
				${sharedPostEmbedHTML}
				${inlineEditFormHTML}
				${primaryMediaHTML}
				${statsHTML}
			</div>
			${actionsHTML}
			${commentInputHTML}
			${viewCommentsButtonHTML}
			${commentsListAreaHTML}`;
		
		return postElement;
	}

	_populatePostViewModal(postData, modalElements) {
		// 1. Initial validation
		if (!postData || !modalElements || !modalElements.body || !modalElements.title) {
			console.error("PFM: Missing data or modal elements for populating post view.");
			if (modalElements && modalElements.body) {
				modalElements.body.innerHTML = '<div class="p-4"><p class="text-red-500 text-center">Error: Could not load post details.</p></div>';
			}
			return;
		}

		// 2. Prepare all data points from the post object
		const postOwnerId = postData.user_id || (postData.user ? postData.user.id : 'unknown');
		const rawAuthorName = this._getDisplayName(postData) || postData.username || 'User';
		const authorName = this._sanitizeHTML(rawAuthorName);
		const authorAvatar = postData.user_avatar || this._generateClientFallbackSVG(rawAuthorName, 40);
		const profileLink = `/profile/${postOwnerId}`;
		const timestamp = postData.created_at || new Date().toISOString();
		const timeAgo = this._timeAgo(timestamp);
		const visibility = this._sanitizeHTML(postData.visibility || 'public');
		const visibilityIcon = this._getIconForVisibility(visibility);

		modalElements.title.textContent = `Post by ${authorName}`;

		// 3. Build all dynamic HTML fragments, mirroring _createPostElement logic
		
		let postBodyHTML = '';
		if (postData.post_type !== 'ai_code') {
			const hasContent = postData.content && postData.content.trim() !== '';
			const isShare = postData.post_type === 'share';
			let classes = 'post-content-display mb-3';
			if (isShare) classes += ' post-share-comment-display';
			if (hasContent) classes += ' dark:text-gray-200 whitespace-pre-wrap';
			else classes += ' hidden';
			const processedContent = hasContent ? this._linkifyText(postData.content) : '';
			postBodyHTML = `<div class="${classes}">${processedContent}</div>`;
		}

		const linkPreviewHTML = this._createLinkPreviewElement(postData);

		let mediaDisplayHTML = '';
		if (postData.post_type === 'ai_code') {
			const editorDisplayContainerId = `editorDisplayContainer-modal-${postData.id}`;
			mediaDisplayHTML = `<div class="ai-code-block my-2"><p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Code (<span class="post-code-language-display">${this._sanitizeHTML(postData.code_language)}</span>):</p><div id="${editorDisplayContainerId}" class="code-editor-display-container min-h-[100px] rounded bg-gray-50 dark:bg-dark-800"><div class="p-2 text-xs text-gray-400 dark:text-gray-500">Loading code view...</div></div></div>`;
		} else if (postData.is_live_stream && postData.stream_playback_uid) {
			const playbackUid = this._sanitizeHTML(postData.stream_playback_uid);
			let posterUrl = this._sanitizeHTML(postData.image || '');
			if (!posterUrl || !posterUrl.includes('videodelivery.net')) posterUrl = `https://videodelivery.net/${playbackUid}/thumbnails/thumbnail.jpg?time=1s&height=360&width=640`;
			let streamBaseUrl = "https://iframe.videodelivery.net";
			if (playbackUid.includes('.')) { const domainPart = playbackUid.substring(0, playbackUid.indexOf('/')); if (domainPart.includes('cloudflarestream.com')) streamBaseUrl = `https://iframe.${domainPart}`; }
			const actualVideoId = playbackUid.includes('/') ? playbackUid.substring(playbackUid.indexOf('/') + 1) : playbackUid;
			mediaDisplayHTML = `<div class="cloudflare-stream-player-container post-media-display rounded-lg my-2" style="position: relative; padding-top: 56.25%; height: 0; overflow: hidden; background-color: #000;"><iframe src="${streamBaseUrl}/${actualVideoId}?autoplay=false&muted=false&preload=metadata&poster=${encodeURIComponent(posterUrl)}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true" loading="lazy" title="Video Player"></iframe></div>`;
		} else if (postData.image) {
			mediaDisplayHTML = `<img src="${this._sanitizeHTML(postData.image)}" alt="Post media" class="post-media-display rounded-lg max-h-[400px] w-full object-contain my-2 mx-auto block">`;
		}

		// --- Shared Post Embed (if applicable) ---
		let sharedContentEmbedHTML = '';
		if (postData.post_type === 'share' && postData.original_post) {
			const original = postData.original_post;
			const originalAuthorNameRaw = this._getDisplayName(original) || original.username || 'User';
			const originalAuthorName = this._sanitizeHTML(originalAuthorNameRaw);
			const originalAuthorAvatar = original.user_avatar || this._generateClientFallbackSVG(originalAuthorNameRaw, 32);
			let originalContentPreview = this._sanitizeHTML(original.content || '', true).substring(0, 150) + (original.content.length > 150 ? '...' : '');
			let originalMediaPreview = '';
			if (original.image) {
				originalMediaPreview = `<img src="${this._sanitizeHTML(original.image)}" alt="Shared media preview" class="rounded max-h-[100px] object-contain my-1 border dark:border-dark-500">`;
			}

			// --- START OF FIX ---
			// Added the missing location logic for the embedded post in the modal.
			let originalLocationHTML = '';
			if (original.location_name) {
				originalLocationHTML = `
					<span class="font-normal text-gray-500 dark:text-gray-400 text-sm">
						was at
						<i class="fas fa-map-marker-alt text-red-500 mx-1"></i>
						<strong class="font-semibold text-gray-800 dark:text-gray-200">${this._sanitizeHTML(original.location_name)}</strong>
					</span>
				`;
			}
			// --- END OF FIX ---

			sharedContentEmbedHTML = `
				<div class="original-shared-post-embed-modal border dark:border-dark-600 p-3 mt-3 rounded-md bg-gray-50 dark:bg-dark-700">
					<div class="flex items-center mb-2">
						<img src="${originalAuthorAvatar}" alt="${originalAuthorName}" class="w-8 h-8 rounded-full mr-2 object-cover flex-shrink-0">
						<div>
							<a href="/profile/${original.user_id}" class="font-semibold text-sm dark:text-white hover:underline">${originalAuthorName}</a>
							
							<!-- FIX: Added the location variable to the template -->
							${originalLocationHTML ? `<div class="mt-1">${originalLocationHTML}</div>` : ''}

							<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${this._timeAgo(original.created_at)} · Original post</p>
						</div>
					</div>
					${originalContentPreview ? `<div class="text-sm dark:text-gray-300 whitespace-pre-wrap">${originalContentPreview}</div>` : ''}
					${originalMediaPreview}
				</div>`;
		} else if (postData.post_type === 'share' && !postData.original_post) {
			sharedContentEmbedHTML = `<div class="original-shared-post-embed-modal border dark:border-dark-600 p-3 mt-3 rounded-md bg-gray-50 dark:bg-dark-700"><p class="text-red-500">Error: Original shared content could not be loaded.</p></div>`;
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
							<i class="fas ${postData.post_type === 'share' ? 'fa-comment-edit' : 'fa-edit'} mr-2 w-4 text-center"></i>${postData.post_type === 'share' ? 'Edit Share Text' : 'Edit Post'}
						</button>
						<button class="delete-post-button w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-700 dark:hover:text-white flex items-center" data-post-id="${postData.id}">
							<i class="fas fa-trash-alt mr-2 w-4 text-center"></i>${postData.post_type === 'share' ? 'Delete Share' : 'Delete Post'}
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
								<span class="post-header-editable-visibility-select-container hidden ml-0.5 items-center"></span>
							</p>
						</div>
					</div>
					${optionsDropdownHTML}
				</div>
				${postBodyHTML}
				${linkPreviewHTML}
				${sharedContentEmbedHTML}
				<div class="post-inline-edit-form hidden space-y-3 mb-3"></div>
				${mediaDisplayHTML ? `<div class="post-media-container my-2">${mediaDisplayHTML}</div>` : '<div class="post-media-display hidden"></div>'}
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
					<img src="${this.currentUserAvatar || this._generateClientFallbackSVG('U', 32)}" alt="Your avatar" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
					<textarea class="comment-input flex-1 p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none dark:bg-dark-600 dark:text-white" rows="1" placeholder="Write a comment..."></textarea>
					<button class="comment-submit-button bg-facebook hover:bg-facebook-dark text-white font-semibold py-2 px-3 rounded-lg text-sm ml-2" data-post-id="${postData.id}"><i class="fa-solid fa-paper-plane"></i></button>
				</div>
			</div>
			<div class="view-comments-trigger p-3 pt-1 ${(postData.comment_count || 0) === 0 ? 'hidden' : ''}">
				<button class="view-comments-button text-sm text-gray-600 dark:text-gray-400 hover:underline" data-post-id="${postData.id}" data-original-text="View ${postData.comment_count || 0} comment${(postData.comment_count || 0) !== 1 ? 's' : ''}">View ${postData.comment_count || 0} comment${(postData.comment_count || 0) !== 1 ? 's' : ''}</button>
			</div>
			<div class="comments-list-area p-3 pt-0 space-y-2"></div>
		</div>
		`;

		modalElements.body.innerHTML = dynamicPostItemHTML;
		
		if (postData.post_type === 'ai_code') {
			const postElementInModal = modalElements.body.querySelector('.post-item');
			if (postElementInModal) {
				setTimeout(() => {
					this._initializeReadOnlyMonacoForFeedPost(postElementInModal, postData.id, postData.content, postData.code_language);
				}, 100);
			}
		}
	}

	/**
	 * Creates a clickable link preview card element.
	 * @param {object} linkData - An object from the post containing link_url, link_domain, etc.
	 * @returns {string} The HTML string for the link preview card, or an empty string.
	 */
	_createLinkPreviewElement(linkData) {
		if (!linkData || !linkData.link_url) {
			return '';
		}

		// Add our tracking parameter for analytics
		const urlWithRef = new URL(linkData.link_url);
		urlWithRef.searchParams.set('ref', 'smartfed');

		// Use link_domain if available, otherwise don't show this line
		const displayDomain = linkData.link_domain ? this._sanitizeHTML(linkData.link_domain.toUpperCase()) : '';
		// Use link_title if you implement a server-side crawler later, otherwise fallback to the URL itself
		const displayTitle = linkData.link_title ? this._sanitizeHTML(linkData.link_title) : this._sanitizeHTML(linkData.link_url);
		// Use link_description if the crawler provides it
		const displayDescription = linkData.link_description ? this._sanitizeHTML(linkData.link_description) : '';
		// Use link_image_url for a rich preview if the crawler provides it
		const imagePreviewHTML = linkData.link_image_url ? `<div class="link-preview-image-wrapper"><img src="${this._sanitizeHTML(linkData.link_image_url)}" alt="Link preview" class="link-preview-image"></div>` : '';


		return `
			<div class="link-preview-container my-3">
				<a href="${this._sanitizeHTML(urlWithRef.toString())}" target="_blank" rel="noopener noreferrer" class="block border border-gray-200 dark:border-dark-600 rounded-lg hover:bg-gray-50 dark:hover:bg-dark-700 no-underline transition-colors overflow-hidden">
					${imagePreviewHTML}
					<div class="p-3">
						${displayDomain ? `<p class="text-xs text-gray-500 dark:text-gray-400 font-semibold mb-1 tracking-wider">${displayDomain}</p>` : ''}
						<p class="font-bold text-gray-800 dark:text-gray-200 text-base leading-tight">${displayTitle}</p>
						${displayDescription ? `<p class="text-sm text-gray-600 dark:text-gray-300 mt-1">${displayDescription}</p>` : ''}
					</div>
				</a>
			</div>
		`;
	}

	/**
	 * Finds URLs in a block of text and wraps them in <a> tags.
	 * This version is robust: it will NOT re-link URLs already inside an <a> tag,
	 * it linkifies domains without http/https, and it adds a tracking parameter.
	 * @param {string} text - The text content to process, which may contain HTML.
	 * @returns {string} The text with all raw URLs converted to clickable links.
	 */
	_linkifyText(text) {
		if (!text) return '';

		// MODIFIED: Using the new, more powerful regex to find domains with or without http/www.
		// The \b ensures we only match whole "words" (e.g., won't match "text.com" in "sometext.com").
		const urlRegex = /(\b(https?:\/\/)?(www\.)?[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]\.[a-zA-Z]{2,63}(?:\/[^\s<>"'{}|\\^`]*)?\b)/g;
		
		// Step 1: Find and temporarily replace all existing <a> tags. (This is unchanged)
		const existingLinks = [];
		let tempText = text.replace(/<a\b[^>]*>[\s\S]*?<\/a>/gi, (match) => {
			const placeholder = `__EXISTING_LINK_${existingLinks.length}__`;
			existingLinks.push(match);
			return placeholder;
		});

		// Step 2: Sanitize the remaining text and linkify any raw URLs found in it.
		const tempDiv = document.createElement('div');
		tempDiv.textContent = tempText;
		let sanitizedAndLinkedText = tempDiv.innerHTML.replace(urlRegex, (url) => {
			// This is the core of the new logic.
			try {
				let fullUrl = url;
				// If the matched URL doesn't have a protocol, add https:// to it.
				// This is necessary for the `new URL()` constructor to work reliably.
				if (!/^https?:\/\//i.test(fullUrl)) {
					fullUrl = 'https://' + fullUrl;
				}

				// Use the full URL to create a URL object and add the tracking parameter.
				const urlWithRef = new URL(fullUrl);
				urlWithRef.searchParams.set('ref', 'smartfed_text_link');

				// Create the link: href uses the full, modified URL, but the displayed text
				// is the original, clean URL the user saw (e.g., "google.com").
				return `<a href="${urlWithRef.toString()}" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline break-all">${url}</a>`;
			} catch (e) {
				// If for some reason the match isn't a valid URL, return it as plain text.
				return url;
			}
		});

		// Step 3: Restore the original <a> tags by replacing the placeholders. (This is unchanged)
		if (existingLinks.length > 0) {
			sanitizedAndLinkedText = sanitizedAndLinkedText.replace(/__EXISTING_LINK_(\d+)__/g, (match, index) => {
				return existingLinks[parseInt(index, 10)] || match;
			});
		}

		return sanitizedAndLinkedText;
	}
	
	async _initializeReadOnlyMonacoForFeedPost(postElement, postId, codeContent, language) {
		const editorDisplayContainer = postElement.querySelector(`#editorDisplayContainer-feed-${postId}`);
		if (!editorDisplayContainer) return;
		const aiCodeTitleId = `aiCodeTitle-feed-${postId}`,
			toggleCodeButtonId = `toggleCodeButton-feed-${postId}`,
			codeSectionId = `codeSection-feed-${postId}`,
			editorTargetId = `monaco-editor-feedRender-${postId}`,
			runNewTabButtonId = `runNewTabButton-feed-${postId}`,
			previewContainerId = `previewContainer-feed-${postId}`,
			previewIframeId = `previewIframe-feed-${postId}`,
			expandButtonId = `expandPreviewBtn-feed-${postId}`;
		const isHtml = language === 'html',
			initialPreviewHeight = '400px',
			initialExpandButtonText = 'Collapse',
			initialPreviewExpandedState = 'true';
		editorDisplayContainer.innerHTML = '';
		editorDisplayContainer.style.border = `1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}`;
		editorDisplayContainer.style.borderRadius = '0.375rem';
		editorDisplayContainer.style.overflow = 'hidden';
		editorDisplayContainer.innerHTML = `<div class="editor-header p-2 flex justify-between items-center bg-gray-100 dark:bg-dark-700 border-b border-gray-300 dark:border-dark-500"><h4 id="${aiCodeTitleId}" style="font-size: 0.875rem; font-weight:500;" class="dark:text-gray-200">AI Generated Code (${this._sanitizeHTML(language)})</h4><button id="${toggleCodeButtonId}" class="text-xs bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-400 px-2 py-1 rounded !text-gray-700 dark:!text-gray-200">Show Code</button></div><div id="${codeSectionId}" class="hidden"><div id="${editorTargetId}" style="height: 250px; min-height:150px;" class="ro-monaco-editor-instance"></div><div class="editor-footer p-2 flex justify-between items-center border-t border-gray-300 dark:border-dark-500 bg-gray-100 dark:bg-dark-700"><select id="languageSelector-feedRender-${postId}" aria-label="Select language" class="language-selector text-xs p-1 rounded bg-gray-200 dark:bg-dark-600 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-dark-500 focus:outline-none">${['html', 'javascript', 'typescript', 'css', 'python', 'java', 'csharp', 'php', 'ruby', 'go', 'swift', 'kotlin', 'sql', 'markdown', 'json', 'xml', 'yaml'].map(lang =>`<option value="${lang}" ${lang === language ? 'selected' : ''}>${lang.charAt(0).toUpperCase() + lang.slice(1)}</option>`).join('')}</select><div><button data-copy-code-feed="${postId}" class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded">Copy</button><button id="${runNewTabButtonId}" class="text-xs bg-green-500 hover:bg-green-600 text-white py-1 px-2 rounded ml-2 ${!isHtml ? 'hidden' : ''}">Run</button></div></div></div><div id="${previewContainerId}" class="mt-0 ${isHtml ? '' : 'hidden'}" style="border-top: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}; background-color: ${document.documentElement.classList.contains('dark') ? '#2D2D2D' : '#f9f9f9'};"><div class="editor-header p-2 flex justify-between items-center bg-gray-100 dark:bg-dark-700 border-b border-gray-300 dark:border-dark-500"><h4 style="font-size: 0.875rem; font-weight:500;" class="dark:text-gray-200">HTML Preview</h4><button id="${expandButtonId}" class="text-xs bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-400 px-2 py-1 rounded !text-gray-700 dark:!text-gray-200">${initialExpandButtonText}</button></div><iframe id="${previewIframeId}" style="width:100%; height:${initialPreviewHeight}; border:none; background-color:white;" sandbox="allow-scripts allow-same-origin" data-is-expanded="${initialPreviewExpandedState}" title="HTML Preview for feed post"></iframe></div>`;
		const aiCodeTitleElement = editorDisplayContainer.querySelector(`#${aiCodeTitleId}`),
			toggleCodeButton = editorDisplayContainer.querySelector(`#${toggleCodeButtonId}`),
			codeSection = editorDisplayContainer.querySelector(`#${codeSectionId}`),
			copyCodeButton = editorDisplayContainer.querySelector(`[data-copy-code-feed="${postId}"]`),
			runNewTabButton = editorDisplayContainer.querySelector(`#${runNewTabButtonId}`),
			languageSelector = editorDisplayContainer.querySelector(`#languageSelector-feedRender-${postId}`),
			feedPreviewContainer = editorDisplayContainer.querySelector(`#${previewContainerId}`),
			feedPreviewIframe = editorDisplayContainer.querySelector(`#${previewIframeId}`),
			feedExpandButton = editorDisplayContainer.querySelector(`#${expandButtonId}`);
		try {
			const editorInstance = await this._initializeMonaco(editorTargetId, codeContent, language, true);
			if (toggleCodeButton && codeSection) toggleCodeButton.addEventListener('click', () => {
				const isSectionHidden = codeSection.classList.toggle('hidden');
				toggleCodeButton.textContent = isSectionHidden ? "Show Code" : "Hide Code";
				if (!isSectionHidden && editorInstance) setTimeout(() => editorInstance.layout(), 0);
			});
			if (isHtml && feedPreviewIframe && editorInstance) feedPreviewIframe.srcdoc = editorInstance.getValue();
			if (copyCodeButton && editorInstance) copyCodeButton.addEventListener('click', () => navigator.clipboard.writeText(editorInstance.getValue()).then(() => {
				const o = copyCodeButton.textContent;
				copyCodeButton.textContent = "Copied!";
				setTimeout(() => copyCodeButton.textContent = o, 2e3);
			}).catch(e => console.error("Feed copy error:", e)));
			if (runNewTabButton && editorInstance) runNewTabButton.addEventListener('click', () => {
				const currentLang = editorInstance.getModel().getLanguageId();
				if (currentLang === 'html') {
					const c = editorInstance.getValue();
					try {
						const n = window.open();
						if (n) {
							n.document.open();
							n.document.write(c);
							n.document.close();
						} else this._showInternalGenericModal('Error', "Failed to open new tab. Pop-up blocker?", 'error');
					} catch (e) {
						console.error("Error opening HTML tab:", e);
						this._showInternalGenericModal('Error', "Error running HTML.", 'error');
					}
				} else this._showInternalGenericModal('Info', 'Run (New Tab) for HTML only.', 'info');
			});
			if (languageSelector && editorInstance) languageSelector.addEventListener('change', function() {
				const newLang = this.value;
				monaco.editor.setModelLanguage(editorInstance.getModel(), newLang);
				const isNewLangHtml = newLang === 'html';
				if (aiCodeTitleElement) aiCodeTitleElement.textContent = `AI Generated Code (${this._sanitizeHTML(newLang)})`;
				if (runNewTabButton) runNewTabButton.classList.toggle('hidden', !isNewLangHtml);
				if (feedPreviewContainer && feedPreviewIframe) {
					feedPreviewContainer.classList.toggle('hidden', !isNewLangHtml);
					if (isNewLangHtml) feedPreviewIframe.srcdoc = editorInstance.getValue();
					else feedPreviewIframe.srcdoc = '';
				}
			});
			if (feedExpandButton && feedPreviewIframe) feedExpandButton.addEventListener('click', () => {
				const isCurrentlyExpanded = feedPreviewIframe.dataset.isExpanded === 'true';
				const newHeight = isCurrentlyExpanded ? '150px' : '400px';
				const newButtonText = isCurrentlyExpanded ? 'Expand' : 'Collapse';
				const newDatasetState = isCurrentlyExpanded ? 'false' : 'true';
				feedPreviewIframe.style.height = newHeight;
				feedExpandButton.textContent = newButtonText;
				feedPreviewIframe.dataset.isExpanded = newDatasetState;
			});
		} catch (e) {
			console.error("PFM: Error initializing read-only Monaco:", e);
			const el = document.getElementById(editorTargetId);
			if (el) el.innerHTML = `<pre class="p-2 bg-gray-100 dark:bg-dark-900 text-sm overflow-auto rounded" style="max-height:250px;">${this._sanitizeHTML(codeContent)}</pre><p class="text-xs text-red-500 p-1 text-center">Preview error.</p>`;
			else editorDisplayContainer.innerHTML = `<div class="p-2"><pre class="bg-gray-100 dark:bg-dark-900 text-sm overflow-auto rounded p-2" style="max-height:250px;">${this._sanitizeHTML(codeContent)}</pre><p class="text-xs text-red-500 p-1 text-center">Preview error.</p></div>`;
			if (codeSection && codeSection.classList.contains('hidden')) codeSection.classList.remove('hidden');
			if (toggleCodeButton) {
				toggleCodeButton.textContent = "Code(Error)";
				toggleCodeButton.disabled = true;
			}
			if (feedPreviewContainer) feedPreviewContainer.classList.add('hidden');
		}
	}

	async _initializeMonaco(containerId, codeContent, language, readOnly = false, customOptions = {}) {
		return new Promise((resolve, reject) => {
			if (typeof require === 'undefined') return reject(new Error("RequireJS not loaded"));
			if (!window.monacoPathsConfigured) {
				require.config({
					paths: {
						'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs'
					}
				});
				window.monacoPathsConfigured = true;
			}
			require(['vs/editor/editor.main'], () => {
				const editorTargetElement = document.getElementById(containerId);
				if (!editorTargetElement) return reject(new Error(`Editor element #${containerId} not found`));
				editorTargetElement.innerHTML = '';
				const baseOptions = {
					value: codeContent,
					language: language,
					theme: document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs',
					automaticLayout: true,
					minimap: {
						enabled: true
					},
					scrollBeyondLastLine: false,
					fontSize: readOnly ? 13 : 14,
					lineNumbers: 'on',
					roundedSelection: true,
					scrollbar: {
						vertical: 'auto',
						horizontal: 'auto',
						verticalScrollbarSize: 8,
						horizontalScrollbarSize: 8
					},
					wordWrap: 'on',
					readOnly: readOnly,
					contextmenu: true,
					selectionHighlight: true,
					renderIndentGuides: true,
					glyphMargin: false,
					folding: true,
					lineDecorationsWidth: readOnly ? 5 : 0,
					lineNumbersMinChars: 3,
					overviewRulerLanes: 2,
				};
				try {
					const editorInstance = monaco.editor.create(editorTargetElement, {
						...baseOptions,
						...customOptions
					});
					resolve(editorInstance);
				} catch (creationError) {
					if (editorTargetElement) editorTargetElement.innerHTML = `<p class="p-4 text-red-500">Failed to create editor.</p>`;
					reject(creationError);
				}
			}, (err) => {
				const editorTargetElement = document.getElementById(containerId);
				if (editorTargetElement) editorTargetElement.innerHTML = `<p class="p-4 text-red-500">Failed to load editor library.</p>`;
				reject(err);
			});
		});
	}

    async _handleEditComment(editButton) {
		const commentItemElement = editButton.closest('.comment-item');
		if (!commentItemElement) return;

		const commentId = commentItemElement.dataset.commentId;
		if (this.editingCommentId === commentId) return; 

		if (this.editingCommentId && this.editingCommentId !== commentId) {
			const otherEditingComment = this.feedContainerEl.querySelector(`.comment-item[data-comment-id="${this.editingCommentId}"] .comment-edit-cancel-button`);
			if (otherEditingComment) otherEditingComment.click();
		}
		this.editingCommentId = commentId;

		const commentTextDisplay = commentItemElement.querySelector('.comment-text-display');
		const commentEditArea = commentItemElement.querySelector('.comment-edit-area');
		const commentActions = commentItemElement.querySelector('.comment-actions'); 

		if (!commentTextDisplay || !commentEditArea || !commentActions) return;

		const originalContent = commentTextDisplay.textContent; 
		
		commentTextDisplay.classList.add('hidden');
		commentActions.classList.add('hidden');

		commentEditArea.classList.remove('hidden');
		commentEditArea.innerHTML = `
			<textarea class="comment-edit-input w-full p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none dark:bg-dark-500 dark:text-white text-sm" rows="2">${originalContent}</textarea>
			<div class="comment-edit-actions text-xs mt-1 flex items-center justify-end space-x-2">
				<button class="comment-edit-cancel-button text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Cancel</button>
				<button class="comment-edit-save-button bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-xs">Save</button>
			</div>
		`;

		const editInput = commentEditArea.querySelector('.comment-edit-input');
		const saveButton = commentEditArea.querySelector('.comment-edit-save-button');
		const cancelButton = commentEditArea.querySelector('.comment-edit-cancel-button');

		editInput.focus();
		editInput.selectionStart = editInput.selectionEnd = editInput.value.length;

		const closeEditUI = (newTextContent = null) => {
			commentEditArea.innerHTML = '';
			commentEditArea.classList.add('hidden');
			commentTextDisplay.classList.remove('hidden');
			if (newTextContent !== null) {
				commentTextDisplay.innerHTML = this._sanitizeHTML(newTextContent, true);
			}
			commentActions.classList.remove('hidden');
			this.editingCommentId = null;
		};

		saveButton.addEventListener('click', async () => {
			const newContent = editInput.value.trim();
			if (!newContent) {
				this._showInternalGenericModal('Validation Error', 'Comment content cannot be empty.', 'warning');
				editInput.focus();
				return;
			}
			if (newContent === originalContent) {
				closeEditUI(originalContent); 
				return;
			}

			saveButton.disabled = true;
			saveButton.innerHTML = '<div class="loading-spinner-xs inline-block"></div> Saving...';

			try {
				const formData = new FormData();
				formData.append('content', newContent);

				const response = await this._apiFetch(API_ENDPOINTS.EDIT_COMMENT(commentId), {
					method: 'POST',
					body: formData,
					headers: { 'Accept': 'application/json' }
				});
				const result = await response.json();

				if (!response.ok || !result.success) {
					throw new Error(result.message || 'Failed to update comment.');
				}
				
				commentTextDisplay.innerHTML = this._sanitizeHTML(result.comment.content, true);
				
				const timeagoSpan = commentItemElement.querySelector('.comment-timeago');
				const editedMarker = commentItemElement.querySelector('.comment-content-bubble .text-xs em');

				if (result.comment.updated_at && timeagoSpan) {
					if (result.comment.updated_at !== result.comment.created_at) {
						if (!editedMarker) {
							const pTimestamp = timeagoSpan.parentElement;
							const newEditedMarker = document.createElement('em');
	newEditedMarker.className = 'text-xs';
							newEditedMarker.innerHTML = ' · edited';
							pTimestamp.appendChild(newEditedMarker);
						}
					} else {
						if(editedMarker) editedMarker.remove();
					}
				}
				closeEditUI(result.comment.content); 
			} catch (error) {
				console.error('Error updating comment:', error);
				this._showInternalGenericModal('Error', `Failed to update comment: ${error.message}`, 'error');
				saveButton.disabled = false;
				saveButton.textContent = 'Save';
			}
		});

		cancelButton.addEventListener('click', () => {
			closeEditUI(originalContent); 
		});
	}

	_bindDelegatedEventListeners() {
		if (!this.feedContainerEl) return;
		
		// Listener for closing post options dropdown when clicking outside
		document.addEventListener('click', (event) => {
			if (!event.target.closest('.post-options-dropdown-container')) {
				document.querySelectorAll('.post-options-menu:not(.hidden)').forEach(menu => {
					menu.classList.add('hidden');
				});
			}
		});
		
		this.feedContainerEl.addEventListener('click', async (event) => {
			const likeButton = event.target.closest('.like-button');
			const commentActionButton = event.target.closest('.comment-action-button');
			const commentSubmitButton = event.target.closest('.comment-submit-button');
			const viewCommentsButton = event.target.closest('.view-comments-button');
			const loadMoreCommentsButton = event.target.closest('.load-more-comments-button');
			const postOptionsTrigger = event.target.closest('.post-options-trigger');
			const editPostButton = event.target.closest('.edit-post-button');
			const deletePostButton = event.target.closest('.delete-post-button');
			const shareButton = event.target.closest('.share-button');
			const editCommentButton = event.target.closest('.edit-comment-button'); 
			const deleteCommentButton = event.target.closest('.delete-comment-button');
			
			// --- Handler for clicking the embedded original post to view in modal ---
			const viewOriginalPostTrigger = event.target.closest('.original-shared-post-embed[data-action="view-original-post"]');

			if (likeButton) { 
				event.preventDefault(); 
				if (!this.currentUserId) { this._showInternalGenericModal('Authentication Required', 'Please log in to like posts.', 'warning'); return; } 
				this._handleToggleLike(likeButton); 
			} else if (commentActionButton) { 
				event.preventDefault(); 
				if (!this.currentUserId) { this._showInternalGenericModal('Authentication Required', 'Please log in to comment.', 'warning'); return; } 
				this._handleCommentButtonClick(commentActionButton); 
			} else if (commentSubmitButton) { 
				event.preventDefault(); 
				if (!this.currentUserId) { this._showInternalGenericModal('Authentication Required', 'Please log in to post comments.', 'warning'); return; } 
				this._handleSubmitComment(commentSubmitButton); 
			} else if (viewCommentsButton) { 
				event.preventDefault(); 
				this._handleViewComments(viewCommentsButton); 
			} else if (loadMoreCommentsButton) { 
				event.preventDefault(); 
				this._handleLoadMoreComments(loadMoreCommentsButton); 
			} else if (postOptionsTrigger) {
				event.preventDefault(); 
				event.stopPropagation();
				const postId = postOptionsTrigger.dataset.postId;
				const menu = document.querySelector(`.post-options-menu[data-menu-for-post="${postId}"]`);
				if (menu) {
					document.querySelectorAll('.post-options-menu:not(.hidden)').forEach(otherMenu => {
						if (otherMenu !== menu) otherMenu.classList.add('hidden');
					});
					menu.classList.toggle('hidden');
				}
			} else if (editPostButton) {
				event.preventDefault();
				const postId = editPostButton.dataset.postId;
				this._handleEditPost(postId, editPostButton.closest('.post-item')); // Assuming _handleEditPost exists
				const menu = editPostButton.closest('.post-options-menu');
				if(menu) menu.classList.add('hidden');
			} else if (deletePostButton) {
				event.preventDefault();
				const postId = deletePostButton.dataset.postId;
				this._handleDeletePost(postId, deletePostButton.closest('.post-item'));
				const menu = deletePostButton.closest('.post-options-menu');
				if(menu) menu.classList.add('hidden');
			} else if (shareButton) { 
				event.preventDefault();
				this._handleSharePost(shareButton); 
			} else if (viewOriginalPostTrigger) { // <<<--- CORRECTLY PLACED HANDLER
				event.preventDefault(); 
				console.log('[PFM Delegated Click] Click detected on an .original-shared-post-embed element.'); // LOG 1
				const originalPostId = viewOriginalPostTrigger.dataset.originalPostId;
				console.log('[PFM Delegated Click] Extracted originalPostId from dataset:', originalPostId); // LOG 2

				if (originalPostId) {
					this._handleViewOriginalPostInModal(originalPostId);
				} else {
					console.error("[PFM Delegated Click] Original post ID missing from embed click.");
					this._showInternalGenericModal('Error', 'Could not load original post: ID missing from element.', 'error');
				}
			} else if (editCommentButton) { 
				event.preventDefault();
				this._handleEditComment(editCommentButton);
			} else if (deleteCommentButton) { 
				event.preventDefault();
				this._handleDeleteComment(deleteCommentButton);
			}
		});

		// Listener for Enter key on comment input
		this.feedContainerEl.addEventListener('keypress', (event) => {
			if (event.key === 'Enter' && !event.shiftKey && event.target.classList.contains('comment-input')) {
				event.preventDefault();
				const postItem = event.target.closest('.post-item');
				const submitButton = postItem?.querySelector('.comment-submit-button');
				if (submitButton) this._handleSubmitComment(submitButton, event.target);
			}
		});

		// Listener for Enter key on comment edit input
		this.feedContainerEl.addEventListener('keypress', async (event) => {
			if (event.key === 'Enter' && !event.shiftKey && event.target.classList.contains('comment-edit-input')) {
				event.preventDefault();
				const commentItem = event.target.closest('.comment-item');
				const saveButton = commentItem?.querySelector('.comment-edit-save-button');
				if (saveButton && !saveButton.disabled) { 
					saveButton.click(); 
				}
			}
		});

		// Listener for Escape key on comment edit input
		this.feedContainerEl.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && event.target.classList.contains('comment-edit-input')) {
				event.preventDefault();
				const commentItem = event.target.closest('.comment-item');
				const cancelButton = commentItem?.querySelector('.comment-edit-cancel-button');
				if (cancelButton) {
					cancelButton.click(); 
				}
			}
		});
	}

	_generateUniqueId = () => 'sai-live-' + Date.now() + Math.random().toString(36).substr(2, 5);
	_createAICodePostElement(uniqueId, promptText) {
		const postElement = document.createElement('div');
		postElement.className = 'temporary-ai-post bg-white dark:bg-dark-700 rounded-lg shadow mb-4 fade-in';
		postElement.id = `temp-post-${uniqueId}`;
		postElement.dataset.uniqueGenId = uniqueId;
		postElement.dataset.originalPrompt = promptText;
		const sanitizedPrompt = this._sanitizeHTML(promptText);
		const promptDisplay = sanitizedPrompt.length > 100 ? sanitizedPrompt.substring(0, 97) + '...' : sanitizedPrompt;
		const tempPostHeaderHTML = `<div class="p-4"><div class="flex items-center justify-between"><div class="flex items-center space-x-2"><img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MCA0MCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIj4KICA8Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIyMCIgZmlsbD0iIzFBN0NGRiIvPgogIDx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSJ3aGl0ZSIgZm9udC1mYW1pbHk9InNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMjAiIGZvbnQtd2VpZ2h0PSJib2xkIj5BSSBFPC90ZXh0Pgo8L3N2Zz4K" alt="AI Editor" class="w-10 h-10 rounded-full"><div><p class="font-semibold dark:text-white">AI Code Generation</p><p class="text-gray-500 dark:text-gray-400 text-xs">Streaming live...</p></div></div><button class="temp-ai-post-close-btn text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Close"><i class="fas fa-times"></i></button></div></div>`;
		const editorWrapperHTML = ` <div class="p-4 pt-0 border-t border-gray-200 dark:border-dark-600"> <div class="mb-2"><p class="text-sm text-gray-700 dark:text-gray-300">Sai is generating for: "<em>${promptDisplay}</em>"</p><p class="status-indicator-${uniqueId} text-xs text-gray-500 dark:text-gray-400">Status: Initializing...</p></div> <div class="editor-container" style="border: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}; border-radius: 0.375rem; overflow: hidden; background-color: ${document.documentElement.classList.contains('dark') ? '#1E1E1E' : '#f9f9f9'};"> <div class="editor-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 1rem; background-color: ${document.documentElement.classList.contains('dark') ? '#3a3a3a' : '#e9e9e9'}; border-bottom: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}; color: ${document.documentElement.classList.contains('dark') ? '#e4e6eb' : '#333'};"> <span style="font-size: 0.875rem; font-weight: 500;">Code Editor</span> <select id="languageSelector-${uniqueId}" aria-label="Select language" class="language-selector" style="font-size: 0.875rem; border: 1px solid ${document.documentElement.classList.contains('dark') ? '#555' : '#ccc'}; border-radius: 0.25rem; padding: 0.125rem 0.25rem; background-color: ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : 'white'}; color: ${document.documentElement.classList.contains('dark') ? '#e4e6eb' : 'inherit'};"> ${['html', 'javascript', 'typescript', 'css', 'python', 'java', 'csharp', 'php', 'ruby', 'go', 'swift', 'kotlin', 'sql', 'markdown', 'json', 'xml', 'yaml'].map(lang =>`<option value="${lang}" ${lang === 'html' ? 'selected':''}>${lang.charAt(0).toUpperCase() + lang.slice(1)}</option>`).join('')} </select> </div> <div id="editor-${uniqueId}" style="height: 300px; min-height:150px; flex-grow: 1;" class="monaco-editor-instance"></div> <div class="editor-footer" style="display: flex; justify-content: flex-end; align-items: center; padding: 0.5rem 1rem; background-color: ${document.documentElement.classList.contains('dark') ? '#3a3a3a' : '#e9e9e9'}; border-top: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'};"> <button id="copyButton-${uniqueId}" class="text-sm bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded hidden">Copy</button> <button id="runButton-${uniqueId}" class="text-sm bg-gray-200 dark:bg-dark-500 hover:bg-gray-300 dark:hover:bg-dark-400 text-gray-700 dark:text-gray-200 py-1 px-3 rounded ml-2 hidden">Run (New Tab)</button> <button id="stopButton-${uniqueId}" class="text-sm bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded ml-2"><i class="fas fa-stop mr-1"></i>Stop</button> <button id="saveToFeedButton-${uniqueId}" class="text-sm bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded ml-2 hidden">Share Code</button> </div> </div> <div id="previewContainer-${uniqueId}" class="mt-4 hidden" style="padding:0; border: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}; border-radius: 0.375rem; background-color: ${document.documentElement.classList.contains('dark') ? '#2D2D2D' : '#f9f9f9'};"> <div class="editor-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 1rem; background-color: ${document.documentElement.classList.contains('dark') ? '#3a3a3a' : '#e9e9e9'}; border-bottom: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}; color: ${document.documentElement.classList.contains('dark') ? '#e4e6eb' : '#333'};"> <h4 style="font-size: 0.875rem; font-weight:500;">Live HTML Preview</h4> <button id="expandPreviewBtn-${uniqueId}" class="text-xs bg-gray-200 dark:bg-dark-500 hover:bg-gray-300 dark:hover:bg-dark-400 px-2 py-1 rounded !text-gray-700 dark:!text-gray-200">Expand</button> </div> <iframe id="previewIframe-${uniqueId}" style="width:100%; height:150px; border:none; border-top: 1px solid ${document.documentElement.classList.contains('dark') ? '#4A4A4A' : '#ccc'}; background-color:white;" sandbox="allow-scripts allow-same-origin" data-is-expanded="false" title="Live HTML Preview for AI editor"></iframe> </div> </div>`;
		postElement.innerHTML = tempPostHeaderHTML + editorWrapperHTML;
		const tempCloseBtn = postElement.querySelector('.temp-ai-post-close-btn');
		if (tempCloseBtn) tempCloseBtn.addEventListener('click', () => this.stopAIGeneration(true));
		return postElement;
	}
	async _initializeMonacoInElement(uniqueId, tempPostElement, initialLanguage, initialCode) {
		const editorTargetId = `editor-${uniqueId}`;
		try {
			const editorInstance = await this._initializeMonaco(editorTargetId, initialCode, initialLanguage, false);
			const langSelector = tempPostElement.querySelector(`#languageSelector-${uniqueId}`);
			const copyBtn = tempPostElement.querySelector(`#copyButton-${uniqueId}`);
			const runBtn = tempPostElement.querySelector(`#runButton-${uniqueId}`);
			const stopBtnInPost = tempPostElement.querySelector(`#stopButton-${uniqueId}`);
			const previewIframe = tempPostElement.querySelector(`#previewIframe-${uniqueId}`);
			const previewContainer = tempPostElement.querySelector(`#previewContainer-${uniqueId}`);
			const expandPreviewBtn = tempPostElement.querySelector(`#expandPreviewBtn-${uniqueId}`);
			const saveToFeedBtn = tempPostElement.querySelector(`#saveToFeedButton-${uniqueId}`);
			if (langSelector) {
				langSelector.addEventListener('change', () => {
					const newLang = langSelector.value;
					monaco.editor.setModelLanguage(editorInstance.getModel(), newLang);
					this._updateLivePreviewIframe(editorInstance, previewIframe, previewContainer);
					if (runBtn) runBtn.classList.toggle('hidden', newLang !== 'html');
				});
			}
			if (copyBtn) copyBtn.addEventListener('click', () => {
				navigator.clipboard.writeText(editorInstance.getValue()).then(() => {
					const originalText = copyBtn.textContent;
					copyBtn.textContent = 'Copied!';
					setTimeout(() => {
						copyBtn.textContent = originalText;
					}, 2000);
				}).catch(err => console.error('Copy failed for live AI editor:', err));
			});
			if (runBtn) {
				runBtn.addEventListener('click', () => {
					const code = editorInstance.getValue();
					const lang = editorInstance.getModel().getLanguageId();
					if (lang === 'html') {
						try {
							const newTab = window.open();
							if (newTab) {
								newTab.document.open();
								newTab.document.write(code);
								newTab.document.close();
							} else {
								this._showInternalGenericModal('Error', "Failed to open new tab. Pop-up blocker?", 'error');
							}
						} catch (e) {
							console.error("Error opening HTML tab:", e);
							this._showInternalGenericModal('Error', "Error running HTML.", 'error');
						}
					} else {
						this._showInternalGenericModal('Info', "Run (New Tab) is only available for HTML code.", 'info');
					}
				});
			}
			if (stopBtnInPost && !stopBtnInPost.dataset.listenerAttached) {
				stopBtnInPost.addEventListener('click', () => this.stopAIGeneration(false));
				stopBtnInPost.dataset.listenerAttached = 'true';
			}
			if (saveToFeedBtn && !saveToFeedBtn.dataset.listenerAttached) {
				saveToFeedBtn.addEventListener('click', async () => {
					if (!editorInstance) {
						this._showInternalGenericModal('Error', "Live AI Editor not available.", 'error');
						return;
					}
					const currentCode = editorInstance.getValue();
					const currentLanguage = editorInstance.getModel().getLanguageId();
					const originalPromptVal = tempPostElement.dataset.originalPrompt || "";
					if (!currentCode.trim()) {
						this._showInternalGenericModal('Validation Error', "Cannot share empty code.", 'warning');
						return;
					}
					const originalButtonText = saveToFeedBtn.textContent;
					saveToFeedBtn.textContent = 'Sharing...';
					saveToFeedBtn.disabled = true;
					try {
						const response = await this._apiFetch('/post', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'Accept': 'application/json'
							},
							body: JSON.stringify({
								content: currentCode,
								post_type: 'ai_code',
								code_language: currentLanguage,
								original_prompt: originalPromptVal,
								visibility: 'public'
							})
						});
						const result = await response.json();
						if (response.ok && result.success && result.post) {
							this.prependNewPost(result.post);
							saveToFeedBtn.textContent = 'Shared!';
							saveToFeedBtn.style.backgroundColor = '#6B7280';
							saveToFeedBtn.style.cursor = 'default';
							if (tempPostElement) tempPostElement.dataset.sharedToFeedId = result.post.id;
							const statusIndicator = tempPostElement.querySelector(`.status-indicator-${uniqueId}`);
							if (statusIndicator) statusIndicator.textContent = "Status: Code shared to feed!";
						} else {
							this._showInternalGenericModal('Error', `Failed to share: ${result.message || 'Server error'}`, 'error');
							saveToFeedBtn.textContent = originalButtonText;
							saveToFeedBtn.disabled = false;
						}
					} catch (networkError) {
						console.error("Error sharing AI code post:", networkError);
						this._showInternalGenericModal('Error', 'Network error sharing code.', 'error');
						saveToFeedBtn.textContent = originalButtonText;
						saveToFeedBtn.disabled = false;
					}
				});
				saveToFeedBtn.dataset.listenerAttached = 'true';
			}
			if (previewIframe && expandPreviewBtn && previewContainer) {
				editorInstance.getModel().onDidChangeContent(() => {
					if (editorInstance.getModel().getLanguageId() === 'html') {
						this._updateLivePreviewIframe(editorInstance, previewIframe, previewContainer);
					}
				});
				this._updateLivePreviewIframe(editorInstance, previewIframe, previewContainer);
				expandPreviewBtn.addEventListener('click', () => {
					const isExpanded = previewIframe.dataset.isExpanded === 'true';
					previewIframe.style.height = isExpanded ? '150px' : '400px';
					expandPreviewBtn.textContent = isExpanded ? 'Expand' : 'Collapse';
					previewIframe.dataset.isExpanded = String(!isExpanded);
				});
			}
			return editorInstance;
		} catch (error) {
			console.error(`Error initializing Monaco for live AI post #${uniqueId}:`, error);
			const target = tempPostElement.querySelector(`#editor-${uniqueId}`);
			if (target) target.innerHTML = `<p class="p-4 text-red-500">Failed to init live editor.</p>`;
			throw error;
		}
	}
	_updateLivePreviewIframe(editorInstance, previewIframe, previewContainer) {
		if (!editorInstance || !previewIframe || !previewContainer) return;
		const currentLanguage = editorInstance.getModel().getLanguageId();
		if (currentLanguage === 'html') {
			previewContainer.classList.remove('hidden');
			previewIframe.srcdoc = editorInstance.getValue();
		} else {
			previewContainer.classList.add('hidden');
			previewIframe.srcdoc = ``;
		}
	}
	_setAIGeneratingStateInternal(isActive) {
		this.isAIGenerating = isActive;
		if (window.SmartFed && typeof window.SmartFed.updateModalUIAfterAIStateChange === 'function') {
			window.SmartFed.updateModalUIAfterAIStateChange(isActive);
		}
		if (this.currentGeneratingAIEditor.element && this.currentGeneratingAIEditor.uniquePostId) {
			const editorId = this.currentGeneratingAIEditor.uniquePostId;
			const tempPostEl = this.currentGeneratingAIEditor.element;
			const stopBtnLive = tempPostEl.querySelector(`#stopButton-${editorId}`);
			const copyBtnLive = tempPostEl.querySelector(`#copyButton-${editorId}`);
			const runBtnLive = tempPostEl.querySelector(`#runButton-${editorId}`);
			const shareBtnLive = tempPostEl.querySelector(`#saveToFeedButton-${editorId}`);
			const langSelectorLive = tempPostEl.querySelector(`#languageSelector-${editorId}`);
			if (stopBtnLive) stopBtnLive.classList.toggle('hidden', !isActive);
			if (langSelectorLive) langSelectorLive.disabled = isActive;
			if (isActive) {
				if (copyBtnLive) copyBtnLive.classList.add('hidden');
				if (runBtnLive) runBtnLive.classList.add('hidden');
				if (shareBtnLive && shareBtnLive.textContent !== 'Shared!') shareBtnLive.classList.add('hidden');
			}
		}
		if (!isActive) {
			this.aiStreamFirstChunkReceived = false;
			this.aiAbortController = null;
		}
	}
	async startAIGeneration(promptValue) {
		if (!promptValue) {
			this._showInternalGenericModal('Input Required', 'Prompt cannot be empty.', 'warning');
			return;
		}
		this.cleanupTemporaryAIPost(false);
		this.aiAbortController = new AbortController();
		this._setAIGeneratingStateInternal(true);
		this.aiStreamFirstChunkReceived = false;
		let accumulatedResponseForEditor = "";
		const uniqueLiveId = this._generateUniqueId();
		this.currentGeneratingAIEditor.uniquePostId = uniqueLiveId;
		this.currentGeneratingAIEditor.element = this._createAICodePostElement(uniqueLiveId, promptValue);
		
        if (this.feedContainerEl) {
            // Prepend after iframe if it exists
            if (this.iframeEmbedded && this.feedContainerEl.firstChild && this.feedContainerEl.firstChild.tagName === 'IFRAME') {
                this.feedContainerEl.insertBefore(this.currentGeneratingAIEditor.element, this.feedContainerEl.children[1]);
            } else {
			    this.feedContainerEl.prepend(this.currentGeneratingAIEditor.element);
            }
        } else {
			console.error("Feed container not found for AI editor.");
			this._setAIGeneratingStateInternal(false);
			return;
		}

		const tempPostElementForScope = this.currentGeneratingAIEditor.element;
		let editorInstanceForScope = null;
		this.currentGeneratingAIEditor.previewIframe = tempPostElementForScope.querySelector(`#previewIframe-${uniqueLiveId}`);
		if (window.SmartFed && typeof window.SmartFed.hidePostModalProgrammatically === 'function') {
			window.SmartFed.hidePostModalProgrammatically();
		}
		if (tempPostElementForScope) tempPostElementForScope.scrollIntoView({
			behavior: 'smooth',
			block: 'start'
		});
		try {
			this.currentGeneratingAIEditor.instance = await this._initializeMonacoInElement(uniqueLiveId, tempPostElementForScope, 'html', 'Status: Connecting...');
			editorInstanceForScope = this.currentGeneratingAIEditor.instance;
			if (editorInstanceForScope) editorInstanceForScope.focus();
			const statusIndicatorLive = tempPostElementForScope.querySelector(`.status-indicator-${uniqueLiveId}`);
			if (statusIndicatorLive) statusIndicatorLive.textContent = 'Status: Generating...';
			const theUserID = this.currentUserId || window.APP_USER_ID || 'guest_ai_feed';
			const response = await this._apiFetch("/api", {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
					"Accept": "text/event-stream"
				},
				body: JSON.stringify({
					prompt: promptValue,
					userID: theUserID
				}),
				signal: this.aiAbortController.signal
			});
			if (!response.ok) {
				if (this.aiAbortController?.signal.aborted) throw new DOMException('Aborted', 'AbortError');
				let errorDataText = await response.text().catch(() => "Server error.");
				if (errorDataText.includes("SHOW_PREMIUM_MODAL") || errorDataText.toLowerCase().includes("premium")) {
					if (typeof window.showGlobalPremiumModal === 'function') window.showGlobalPremiumModal();
					else this._showInternalGenericModal('Premium Required', 'This feature requires premium access.', 'info');
					if (statusIndicatorLive) statusIndicatorLive.textContent = 'Status: Premium needed.';
					this._setAIGeneratingStateInternal(false);
					if (tempPostElementForScope) {
						const stopBtn = tempPostElementForScope.querySelector(`#stopButton-${uniqueLiveId}`);
						if (stopBtn) stopBtn.classList.add('hidden');
					}
					return;
				}
				throw new Error(`API Error: ${response.status} ${errorDataText.substring(0,200)}`);
			}
			const reader = response.body.getReader();
			const decoder = new TextDecoder("utf-8");
			while (true) {
				if (this.aiAbortController?.signal.aborted) {
					if (statusIndicatorLive) statusIndicatorLive.textContent = 'Status: Aborted.';
					break;
				}
				const {
					done,
					value
				} = await reader.read();
				if (done) break;
				const chunk = decoder.decode(value, {
					stream: true
				});
				accumulatedResponseForEditor += chunk;
				if (editorInstanceForScope) {
					editorInstanceForScope.setValue(accumulatedResponseForEditor);
					editorInstanceForScope.revealLineInCenterIfOutsideViewport(editorInstanceForScope.getModel().getLineCount(), monaco.editor.ScrollType.Smooth);
					const previewContainer = tempPostElementForScope.querySelector(`#previewContainer-${uniqueLiveId}`);
					if (this.currentGeneratingAIEditor.previewIframe && previewContainer) {
						this._updateLivePreviewIframe(editorInstanceForScope, this.currentGeneratingAIEditor.previewIframe, previewContainer);
					}
				}
				if (!this.aiStreamFirstChunkReceived) this.aiStreamFirstChunkReceived = true;
			}
			if (!this.aiAbortController?.signal.aborted) {
				if (this.aiStreamFirstChunkReceived && accumulatedResponseForEditor.trim()) {
					if (statusIndicatorLive) statusIndicatorLive.textContent = 'Status: Complete.';
				} else {
					if (statusIndicatorLive) statusIndicatorLive.textContent = 'Status: No content.';
				}
			}
		} catch (error) {
			const statusIndicatorLive = tempPostElementForScope?.querySelector(`.status-indicator-${uniqueLiveId}`);
			if (error.name === 'AbortError') {
				if (statusIndicatorLive) statusIndicatorLive.textContent = 'Status: Aborted by user.';
			} else {
				console.error("AI gen stream error (FeedMgr):", error);
				if (statusIndicatorLive) statusIndicatorLive.textContent = `Status: Error - ${error.message.substring(0,100)}...`;
				if (window.SmartFed && typeof window.SmartFed.openPostModal === 'function' && window.SmartFed.postModalTextarea) {
					window.SmartFed.openPostModal();
					window.SmartFed.postModalTextarea.value = `AI Error: ${error.message}\n\nPrompt:\n${promptValue}`;
				} else {
					this._showInternalGenericModal('AI Error', `An error occurred: ${error.message}`, 'error');
				}
			}
		} finally {
			this._setAIGeneratingStateInternal(false);
			if (tempPostElementForScope && uniqueLiveId) {
				const stopBtn = tempPostElementForScope.querySelector(`#stopButton-${uniqueLiveId}`);
				const copyBtn = tempPostElementForScope.querySelector(`#copyButton-${uniqueLiveId}`);
				const runBtn = tempPostElementForScope.querySelector(`#runButton-${uniqueLiveId}`);
				const shareBtn = tempPostElementForScope.querySelector(`#saveToFeedButton-${uniqueLiveId}`);
				if (stopBtn) stopBtn.classList.add('hidden');
				const hasGeneratedContent = accumulatedResponseForEditor.trim() !== "";
				let currentLang = 'html';
				if (editorInstanceForScope?.getModel()) currentLang = editorInstanceForScope.getModel().getLanguageId();
				if (hasGeneratedContent) {
					if (copyBtn) copyBtn.classList.remove('hidden');
					if (runBtn) runBtn.classList.toggle('hidden', currentLang !== 'html');
					if (shareBtn && shareBtn.textContent !== 'Shared!') shareBtn.classList.remove('hidden');
				} else {
					if (copyBtn) copyBtn.classList.add('hidden');
					if (runBtn) runBtn.classList.add('hidden');
					if (shareBtn && shareBtn.textContent !== 'Shared!') shareBtn.classList.add('hidden');
				}
			}
		}
	}
	stopAIGeneration(isFromModalClose = false) {
		if (this.aiAbortController && !this.aiAbortController.signal.aborted) this.aiAbortController.abort();
		this._setAIGeneratingStateInternal(false);
		if (this.currentGeneratingAIEditor.element && this.currentGeneratingAIEditor.uniquePostId) {
			const editorId = this.currentGeneratingAIEditor.uniquePostId;
			const tempPostEl = this.currentGeneratingAIEditor.element;
			const statusIndicator = tempPostEl.querySelector(`.status-indicator-${editorId}`);
			if (statusIndicator && !statusIndicator.textContent.toLowerCase().includes('error')) statusIndicator.textContent = 'Status: Stopped by user.';
			const stopBtn = tempPostEl.querySelector(`#stopButton-${editorId}`);
			if (stopBtn) stopBtn.classList.add('hidden');
			const editorInstance = this.currentGeneratingAIEditor.instance;
			if (editorInstance && editorInstance.getValue().trim() !== "") {
				const copyBtn = tempPostEl.querySelector(`#copyButton-${editorId}`);
				const runBtn = tempPostEl.querySelector(`#runButton-${editorId}`);
				const shareBtn = tempPostEl.querySelector(`#saveToFeedButton-${editorId}`);
				const lang = editorInstance.getModel()?.getLanguageId() || 'html';
				if (copyBtn) copyBtn.classList.remove('hidden');
				if (runBtn) runBtn.classList.toggle('hidden', lang !== 'html');
				if (shareBtn && shareBtn.textContent !== 'Shared!') shareBtn.classList.remove('hidden');
			}
			if (isFromModalClose && !tempPostEl.dataset.sharedToFeedId) {
				tempPostEl.remove();
				this.currentGeneratingAIEditor = {
					instance: null,
					uniquePostId: null,
					element: null,
					previewIframe: null
				};
			}
		}
		console.log("AI Gen stopped (FeedMgr).");
	}
	cleanupTemporaryAIPost(forceRemove = false) {
		if (this.currentGeneratingAIEditor.element) {
			const isShared = !!this.currentGeneratingAIEditor.element.dataset.sharedToFeedId;
			if (forceRemove || !isShared) {
				this.currentGeneratingAIEditor.element.remove();
				this.currentGeneratingAIEditor = {
					instance: null,
					uniquePostId: null,
					element: null,
					previewIframe: null
				};
				if (this.isAIGenerating) this.stopAIGeneration(true);
			}
		}
	}
	_bindGlobalAIEditorEventListeners() {
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				const activePfmModal = document.querySelector('.pfm-modal-overlay');
				if (activePfmModal) {
					const cancelButton = activePfmModal.querySelector('.pfm-modal-actions button:first-child');
					if (cancelButton) cancelButton.click();
					e.preventDefault();
					e.stopPropagation();
					return;
				}
				if (this.currentGeneratingAIEditor.element && this.currentGeneratingAIEditor.previewIframe) {
					const livePreviewIframe = this.currentGeneratingAIEditor.previewIframe;
					const livePreviewContainer = livePreviewIframe.closest('div[id^="previewContainer-"]');
					const feedPreviewContainers = document.querySelectorAll('div[id^="previewContainer-feed-"]:not(.hidden)');
					if (livePreviewContainer && !livePreviewContainer.classList.contains('hidden')) {
						const livePreviewToggleButton = this.currentGeneratingAIEditor.element.querySelector(`#expandPreviewBtn-${this.currentGeneratingAIEditor.uniquePostId}`);
						if (livePreviewToggleButton) livePreviewToggleButton.click();
					} else if (feedPreviewContainers.length > 0) {
						feedPreviewContainers.forEach(container => {
							const postIdParts = container.id.split('-');
							const postId = postIdParts[postIdParts.length - 1];
							const runButton = document.querySelector(`button[data-run-code-feed="${postId}"]`);
							if (runButton && runButton.textContent === "Hide Preview") {
								runButton.click();
							}
						});
					}
				}
			}
		});
	}
    
	async _handleEditPost(postId, postElement) {
        if (!postElement) {
            this._showInternalGenericModal('Error', 'Post element not found for editing.', 'error');
            return;
        }

        if (postElement.classList.contains('is-editing-post')) {
            const existingTextarea = postElement.querySelector(`#inlineEditContent-${postId}`);
            if (existingTextarea) existingTextarea.focus();
            return;
        }

        const currentlyEditing = this.feedContainerEl.querySelector('.post-item.is-editing-post');
        if (currentlyEditing && currentlyEditing !== postElement) {
            const cancelButton = currentlyEditing.querySelector('.post-edit-cancel-button');
            if (cancelButton) cancelButton.click();
        }
        postElement.classList.add('is-editing-post');

        const editButtonInDropdown = postElement.querySelector(`.edit-post-button[data-post-id="${postId}"]`);
        const originalEditButtonHTML = editButtonInDropdown ? editButtonInDropdown.innerHTML : '';
        if (editButtonInDropdown) editButtonInDropdown.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';

        let originalPostData;
        try {
			// This is a GET request, so we use standard fetch
            const response = await fetch(API_ENDPOINTS.SINGLE_POST(postId));
            if (!response.ok) throw new Error('Failed to fetch post data.');
            const result = await response.json();
            if (!result.success || !result.post) throw new Error(result.message || 'Could not load post data.');
            originalPostData = result.post;
        } catch (error) { 
            console.error("Error fetching post for inline edit:", error);
            this._showInternalGenericModal('Error', `Could not load post data: ${error.message}`, 'error');
            if (editButtonInDropdown) editButtonInDropdown.innerHTML = originalEditButtonHTML;
            postElement.classList.remove('is-editing-post');
            return;
        }
        if (editButtonInDropdown) editButtonInDropdown.innerHTML = originalEditButtonHTML;

        const staticHeaderVisibilityIconDisplay = postElement.querySelector('.post-visibility-icon'); 
        const editableHeaderElementsContainer = postElement.querySelector('.post-header-editable-visibility-select-container'); 
        
        const contentDisplay = postElement.querySelector('.post-content-display');
        const aiCodeBlock = postElement.querySelector('.ai-code-block');
        const mediaImage = postElement.querySelector('img[alt="Post media"]');
        const streamPlayer = postElement.querySelector('.cloudflare-stream-player-container');
        const inlineEditFormContainer = postElement.querySelector('.post-inline-edit-form');

        if (!inlineEditFormContainer || !staticHeaderVisibilityIconDisplay || !editableHeaderElementsContainer) {
            console.error("Required UI elements for edit not found. Ensure .post-inline-edit-form, .post-visibility-icon, and .post-header-editable-visibility-select-container exist.", postElement);
            this._showInternalGenericModal('Error', 'UI elements for editing are missing. Please check console.', 'error');
            postElement.classList.remove('is-editing-post');
            return;
        }

        if (contentDisplay) contentDisplay.classList.add('hidden');
        if (aiCodeBlock) aiCodeBlock.classList.add('hidden');
        if (mediaImage) mediaImage.classList.add('hidden');
        if (streamPlayer) streamPlayer.classList.add('hidden');
        staticHeaderVisibilityIconDisplay.classList.add('hidden');

        editableHeaderElementsContainer.innerHTML = ''; 
        editableHeaderElementsContainer.classList.remove('hidden');
        editableHeaderElementsContainer.classList.add('inline-flex', 'items-center');

        const liveEditableIcon = document.createElement('i');
        liveEditableIcon.className = `fas ${this._getIconForVisibility(originalPostData.visibility)} text-xs text-gray-500 dark:text-gray-400 mr-1`;
        liveEditableIcon.title = originalPostData.visibility.charAt(0).toUpperCase() + originalPostData.visibility.slice(1);
        editableHeaderElementsContainer.appendChild(liveEditableIcon);

        const selectWrapper = document.createElement('div'); 
        selectWrapper.className = 'relative';

        const visibilityOptionsHTML = ['public', 'friends', 'private']
            .map(v => `<option value="${v}" ${originalPostData.visibility === v ? 'selected' : ''}>${v.charAt(0).toUpperCase() + v.slice(1)}</option>`)
            .join('');

        const visibilitySelectInHeader = document.createElement('select');
        visibilitySelectInHeader.id = `headerEditVisibilitySelect-${postId}`;
        visibilitySelectInHeader.name = 'visibility';
        visibilitySelectInHeader.className = "py-0 pl-2 pr-6 text-xs border-gray-300 dark:border-dark-600 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-0 rounded-sm dark:bg-dark-700 dark:text-gray-200 appearance-none";
        visibilitySelectInHeader.style.lineHeight = "1.2";
        visibilitySelectInHeader.style.height = "1.4rem"; 
        visibilitySelectInHeader.style.minHeight = "1.4rem";
        visibilitySelectInHeader.innerHTML = visibilityOptionsHTML;
        selectWrapper.appendChild(visibilitySelectInHeader);

        const customArrow = document.createElement('div');
        customArrow.className = "absolute inset-y-0 right-0 flex items-center px-1 pointer-events-none"; 
        customArrow.innerHTML = `<svg class="w-3 h-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>`;
        selectWrapper.appendChild(customArrow);
        
        editableHeaderElementsContainer.appendChild(selectWrapper);

        visibilitySelectInHeader.addEventListener('change', function() {
            liveEditableIcon.className = `fas ${this._getIconForVisibility(this.value)} text-xs text-gray-500 dark:text-gray-400 mr-1`;
            liveEditableIcon.title = this.value.charAt(0).toUpperCase() + this.value.slice(1);
        }.bind(this));


        let formInputsHTML = '';
        const isAICodePost = originalPostData.post_type === 'ai_code';

        formInputsHTML += `
            <div class="mt-2">
                <label for="inlineEditContent-${postId}" class="sr-only">${isAICodePost ? 'Code' : 'Content'}</label>
                <textarea id="inlineEditContent-${postId}" name="content" rows="${isAICodePost ? '10' : '4'}"
                          class="w-full shadow-sm sm:text-sm border-gray-200 dark:border-dark-500 rounded-md dark:bg-dark-700 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-0 p-2 ${isAICodePost ? 'font-mono text-xs' : ''}"
                          placeholder="${isAICodePost ? 'Enter your code...' : 'What\'s on your mind?'}">${this._sanitizeHTML(originalPostData.content)}</textarea>
            </div>
        `;

        if (isAICodePost) {
            formInputsHTML += `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div>
                        <label for="inlineEditCodeLang-${postId}" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-0.5">Language</label>
                        <input type="text" id="inlineEditCodeLang-${postId}" name="code_language" value="${this._sanitizeHTML(originalPostData.code_language || '')}"
                               class="block w-full shadow-sm sm:text-sm border-gray-200 dark:border-dark-500 rounded-md dark:bg-dark-700 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-0 p-2">
                    </div>
                    <div>
                        <label for="inlineEditOriginalPrompt-${postId}" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-0.5">Original Prompt (Optional)</label>
                        <input type="text" id="inlineEditOriginalPrompt-${postId}" name="original_prompt" value="${this._sanitizeHTML(originalPostData.original_prompt || '')}"
                               class="block w-full shadow-sm sm:text-sm border-gray-200 dark:border-dark-500 rounded-md dark:bg-dark-700 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-0 p-2">
                    </div>
                </div>
            `;
        }
        
        formInputsHTML += `
            <div class="flex justify-end items-center space-x-2 mt-4">
                <button type="button" class="post-edit-cancel-button px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-dark-500 hover:bg-gray-300 dark:hover:bg-dark-400 rounded-md shadow-sm">Cancel</button>
                <button type="button" class="post-edit-save-button px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm">Save Changes</button>
            </div>
        `;

        inlineEditFormContainer.innerHTML = formInputsHTML;
        inlineEditFormContainer.classList.remove('hidden');

        const editContentTextarea = inlineEditFormContainer.querySelector(`#inlineEditContent-${postId}`);
        if (editContentTextarea) {
            editContentTextarea.focus();
            editContentTextarea.selectionStart = editContentTextarea.selectionEnd = editContentTextarea.value.length;
        }

        const saveButton = inlineEditFormContainer.querySelector('.post-edit-save-button');
        const cancelButton = inlineEditFormContainer.querySelector('.post-edit-cancel-button');

        const closeInlineEdit = (updatedDataToDisplay = null) => {
            inlineEditFormContainer.innerHTML = '';
            inlineEditFormContainer.classList.add('hidden');

            editableHeaderElementsContainer.innerHTML = ''; 
            editableHeaderElementsContainer.classList.add('hidden');
            editableHeaderElementsContainer.classList.remove('inline-flex', 'items-center');
            staticHeaderVisibilityIconDisplay.classList.remove('hidden'); 
            
            if (contentDisplay) contentDisplay.classList.remove('hidden');
            if (aiCodeBlock) aiCodeBlock.classList.remove('hidden');
            if (mediaImage) mediaImage.classList.remove('hidden');
            if (streamPlayer) streamPlayer.classList.remove('hidden');
            
            postElement.classList.remove('is-editing-post');

            if (updatedDataToDisplay) {
                this._updatePostElementUI(postElement, updatedDataToDisplay);
            } else if (originalPostData) {
                this._updatePostElementUI(postElement, originalPostData);
            }
        };

        saveButton.addEventListener('click', async () => {
            const newContent = editContentTextarea.value;
            const newVisibility = visibilitySelectInHeader.value; 
            let newCodeLanguage = null;
            let newOriginalPrompt = null;

            if (isAICodePost) {
                newCodeLanguage = inlineEditFormContainer.querySelector(`#inlineEditCodeLang-${postId}`).value.trim();
                newOriginalPrompt = inlineEditFormContainer.querySelector(`#inlineEditOriginalPrompt-${postId}`).value.trim();
                if (newContent.trim() === '') {
                     this._showInternalGenericModal('Validation Error', 'Code content cannot be empty.', 'warning'); return;
                }
                if (newCodeLanguage === '') {
                     this._showInternalGenericModal('Validation Error', 'Code language cannot be empty.', 'warning'); return;
                }
            } else {
                if (newContent.trim() === '' && !originalPostData.image && !originalPostData.stream_playback_uid) {
                    this._showInternalGenericModal('Validation Error', 'Post content cannot be empty.', 'warning'); return;
                }
            }
            
            let changesMade = false;
            if (newContent !== originalPostData.content) changesMade = true;
            if (newVisibility !== originalPostData.visibility) changesMade = true;
            if (isAICodePost) {
                if (newCodeLanguage !== (originalPostData.code_language || '')) changesMade = true;
                if (newOriginalPrompt !== (originalPostData.original_prompt || '')) changesMade = true;
            }

            if (!changesMade) {
                closeInlineEdit(originalPostData);
                return;
            }

            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

            const formData = new FormData();
            formData.append('content', newContent);
            formData.append('visibility', newVisibility);
            if (isAICodePost) {
                formData.append('code_language', newCodeLanguage);
                formData.append('original_prompt', newOriginalPrompt);
            }

            try {
                const response = await this._apiFetch(API_ENDPOINTS.UPDATE_POST(postId), {
                    method: 'POST', body: formData, headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (result.success && result.post) {
                    closeInlineEdit(result.post);
                } else {
                    throw new Error(result.message || 'Failed to update post.');
                }
            } catch (error) {
                console.error("Error saving post edit:", error);
                this._showInternalGenericModal('Error', `Could not save changes: ${error.message}`, 'error');
                saveButton.disabled = false;
                saveButton.textContent = 'Save Changes';
            }
        });

        cancelButton.addEventListener('click', () => {
            closeInlineEdit(originalPostData);
        });
         
        inlineEditFormContainer.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.target.tagName !== 'TEXTAREA' || e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                if (!saveButton.disabled) saveButton.click();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelButton.click();
            }
        });
    }

    async _updatePostElementUI(postElement, updatedPostData) {
        if (!postElement || !updatedPostData) return;

        const contentDisplay = postElement.querySelector('.post-content-display');
        if (contentDisplay) {
            if (updatedPostData.post_type !== 'ai_code' && updatedPostData.content && updatedPostData.content.trim() !== '') {
                contentDisplay.innerHTML = this._sanitizeHTML(updatedPostData.content, true);
                contentDisplay.classList.remove('hidden');
            } else if (updatedPostData.post_type !== 'ai_code') {
                contentDisplay.innerHTML = '';
                contentDisplay.classList.add('hidden');
            }
        }

        const aiCodeBlock = postElement.querySelector('.ai-code-block');
        if (updatedPostData.post_type === 'ai_code') {
            if (aiCodeBlock) { 
                const langDisplay = aiCodeBlock.querySelector('.post-code-language-display');
                if (langDisplay) langDisplay.textContent = this._sanitizeHTML(updatedPostData.code_language);
                
                const promptDisplay = aiCodeBlock.querySelector('.post-original-prompt-display');
                if (promptDisplay) {
                    if (updatedPostData.original_prompt && updatedPostData.original_prompt.trim() !== '') {
                        promptDisplay.innerHTML = `Prompt: ${this._sanitizeHTML(updatedPostData.original_prompt)}`;
                        promptDisplay.classList.remove('hidden');
                    } else {
                        promptDisplay.innerHTML = '';
                        promptDisplay.classList.add('hidden');
                    }
                }
                const editorContainerId = `editorDisplayContainer-feed-${updatedPostData.id}`;
                const editorContainer = postElement.querySelector(`#${editorContainerId}`);
                if (editorContainer) {
                    await this._initializeReadOnlyMonacoForFeedPost(postElement, updatedPostData.id, updatedPostData.content, updatedPostData.code_language);
                }
                aiCodeBlock.classList.remove('hidden');
            }
        } else {
            if (aiCodeBlock) aiCodeBlock.classList.add('hidden');
        }
        
        const staticVisibilityIconEl = postElement.querySelector('.post-visibility-icon');
        if (staticVisibilityIconEl) {
            const iconClass = this._getIconForVisibility(updatedPostData.visibility);
            staticVisibilityIconEl.className = `fas ${iconClass} post-visibility-icon`; 
            staticVisibilityIconEl.title = this._sanitizeHTML(updatedPostData.visibility.charAt(0).toUpperCase() + updatedPostData.visibility.slice(1));
            staticVisibilityIconEl.classList.remove('hidden'); 
        }

        const timeAgoSpan = postElement.querySelector('.post-timeago');
        if (timeAgoSpan) {
            let timeText = this._timeAgo(updatedPostData.created_at); 
            const originalCreatedAtTimestamp = timeAgoSpan.dataset.timestamp; // Keep original for comparison logic
            const originalCreatedAtDate = new Date(originalCreatedAtTimestamp.includes('T') ? originalCreatedAtTimestamp : originalCreatedAtTimestamp.replace(' ', 'T') + 'Z');
            const updatedAtDate = new Date(updatedPostData.updated_at.includes('T') ? updatedPostData.updated_at : updatedPostData.updated_at.replace(' ', 'T') + 'Z');

            // Check if updated_at is significantly different from created_at (e.g., more than a minute)
            // This simple check is to avoid showing 'edited' for minor timestamp differences due to db precision
            const oneMinute = 60 * 1000;
            if (updatedPostData.updated_at && (updatedAtDate.getTime() - originalCreatedAtDate.getTime() > oneMinute)) {
                timeAgoSpan.dataset.timestamp = updatedPostData.updated_at; // Update the timestamp to the latest edit
                timeText = this._timeAgo(updatedPostData.updated_at) + ' · edited';
            } else {
                 // If not significantly edited, or no updated_at, use created_at and don't change data-timestamp
                 // timeAgoSpan.dataset.timestamp = updatedPostData.created_at; // Or keep original
                timeText = this._timeAgo(updatedPostData.created_at);
            }
            timeAgoSpan.textContent = timeText;
        }
    }

	async _handleDeletePost(postId, postElement) {
		if (!postId || !postElement) {
			this._showInternalGenericModal('Error', 'Could not delete post: missing information.', 'error');
			return false; // Indicate failure: Missing parameters
		}

		const confirmed = await this._showInternalConfirmModal(
			'Confirm Delete Post',
			'Are you sure you want to delete this post and all its associated data (likes, comments)? This action cannot be undone.',
			'Delete Post', 'Cancel', 'danger'
		);

		if (!confirmed) {
			return false; // Indicate failure: User cancelled
		}

		const deleteButtonInDropdown = postElement.querySelector(`.delete-post-button[data-post-id="${postId}"]`);
		const originalButtonHTML = deleteButtonInDropdown ? deleteButtonInDropdown.innerHTML : '<i class="fas fa-trash-alt mr-2 w-4 text-center"></i>Delete Post'; // Fallback

		if (deleteButtonInDropdown) {
			deleteButtonInDropdown.disabled = true;
			deleteButtonInDropdown.innerHTML = '<i class="fas fa-spinner fa-spin mr-2 w-4 text-center"></i>Deleting...';
		} else {
			console.warn(`PFM: Delete button not found in dropdown for post ${postId} within provided element.`);
		}

		try {
			const response = await this._apiFetch(API_ENDPOINTS.DELETE_POST(postId), {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
				}
			});

			const result = await response.json();

			if (!response.ok || !result.success) {
				throw new Error(result.message || result.error || 'Server error deleting post.');
			}

			return new Promise(resolve => {
				postElement.classList.add('fade-out-post');
				setTimeout(() => {
					const parentContainer = postElement.parentElement;
					postElement.remove();

					if (this.feedContainerEl && parentContainer === this.feedContainerEl) {
						const actualPostItemsCount = this.feedContainerEl.querySelectorAll('.post-item').length;
						const hasFeaturedVideo = this.feedContainerEl.querySelector('#featured-video-placeholder');
						const expectedMinChildren = hasFeaturedVideo ? 1 : 0;

						if (actualPostItemsCount === 0 && this.feedContainerEl.children.length <= expectedMinChildren) {
							if (!this.feedContainerEl.querySelector('p.no-posts-message')) {
								this.feedContainerEl.insertAdjacentHTML('beforeend', '<p class="text-center text-gray-500 dark:text-gray-400 py-8 no-posts-message">No posts yet.</p>');
							}
							if (this.loadingIndicatorEl) this.loadingIndicatorEl.classList.add('hidden');
						}
					}
					resolve(true); // Deletion successful
				}, 300); // Match CSS animation duration
			});

		} catch (error) {
			console.error('Error deleting post:', error);
			this._showInternalGenericModal('Error', `Failed to delete post: ${this._sanitizeHTML(error.message)}`, 'error');
			if (deleteButtonInDropdown) {
				deleteButtonInDropdown.disabled = false;
				deleteButtonInDropdown.innerHTML = originalButtonHTML;
			}
			return false; // Indicate failure: Error occurred
		}
	}

	/**
	 * Get a display name from various possible fields on a post/comment/user object.
	 * Checks common variants: full_name, fullname, fullName, user_full_name, name, display_name, username, and nested user objects.
	 */
	_getDisplayName(obj) {
		if (!obj) return null;
		if (typeof obj === 'string') return obj;
		const keys = ['full_name','fullname','fullName','user_full_name','user_fullname','userFullName','name','display_name','displayName','username'];
		for (const k of keys) {
			if (obj[k]) return obj[k];
		}
		if (obj.user && typeof obj.user === 'object') return this._getDisplayName(obj.user);
		return null;
	}

	_generateClientFallbackSVG(name, size = 32) {
		if (window.globalChatManager && typeof window.globalChatManager._generateFallbackAvatarSVG === 'function') {
			return window.globalChatManager._generateFallbackAvatarSVG(name ? name.substring(0, 1) : '?', size);
		}
		const initial = name ? name.trim().toUpperCase().charAt(0) : '?';
		const colors = ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#34495e', '#7f8c8d'];
		const bgColor = colors[(initial.charCodeAt(0) || 0) % colors.length];
		const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${bgColor}"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="50" fill="white" font-weight="bold">${initial}</text></svg>`;
		return "data:image/svg+xml;base64," + btoa(svg);
	}
	_sanitizeHTML(str, allowBreaks = false) {
		// 1. Ensure the input is a string
		if (typeof str !== 'string') str = String(str || '');

		// 2. Use the browser's own mechanisms to escape HTML special characters
		const temp = document.createElement('div');
		temp.textContent = str; // This safely converts <, >, & etc. to <, >, &
		
		// 3. Return the sanitized string. DO NOT convert \n to <br> here.
		// The `whitespace-pre-wrap` class on the container div will handle rendering newlines correctly.
		return temp.innerHTML;
	}
	_timeAgo(dateString) {
		if (!dateString) return 'some time ago';
		const dateStr = dateString.includes('T') ? dateString : dateString.replace(' ', 'T') + (dateString.endsWith('Z') ? '' : 'Z');
		const date = new Date(dateStr);
		if (isNaN(date.getTime())) return 'invalid date';
		const now = new Date();
		const seconds = Math.round((now - date) / 1000);
		if (seconds < 5) return `just now`;
		if (seconds < 60) return `${seconds}s ago`;
		const minutes = Math.round(seconds / 60);
		if (minutes < 60) return `${minutes}m ago`;
		const hours = Math.round(minutes / 60);
		if (hours < 24) return `${hours}h ago`;
		const days = Math.round(hours / 24);
		if (days === 1) return `1d ago`;
		if (days < 7) return `${days}d ago`;
		return date.toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric'
		});
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
			const response = await this._apiFetch(API_ENDPOINTS.TOGGLE_POST_LIKE, {
				method: 'POST',
				body: formData
			});
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.error || 'Failed to toggle like.');
			buttonElement.classList.toggle('text-facebook', result.isLiked);
			buttonElement.classList.toggle('dark:text-blue-400', result.isLiked);
			buttonElement.classList.toggle('text-gray-600', !result.isLiked);
			buttonElement.classList.toggle('dark:text-gray-400', !result.isLiked);
			if (likeCountSpan) likeCountSpan.textContent = result.likeCount;
		} catch (error) {
			console.error('Like toggle error:', error);
			this._showInternalGenericModal('Error', `Error toggling like: ${error.message}`, 'error');
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
		} else if (commentInput) commentInput.focus();
	}
	async _handleSubmitComment(submitButtonElement, inputElement = null) {
		const postId = submitButtonElement.dataset.postId;
		const postElement = submitButtonElement.closest('.post-item');
		if (!postElement || !postId) return;
		const commentInputElement = inputElement || postElement.querySelector('.comment-input');
		if (!commentInputElement) return;
		const content = commentInputElement.value.trim();
		if (!content) {
			this._showInternalGenericModal('Validation Error', 'Comment cannot be empty.', 'warning');
			return;
		}
		const originalButtonText = submitButtonElement.innerHTML;
		commentInputElement.disabled = true;
		submitButtonElement.disabled = true;
		submitButtonElement.innerHTML = '<div class="loading-spinner w-4 h-4 mx-auto"></div>';
		try {
			const formData = new FormData();
			formData.append('post_id', postId);
			formData.append('content', content);
			const response = await this._apiFetch(API_ENDPOINTS.ADD_POST_COMMENT, {
				method: 'POST',
				body: formData
			});
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.error || 'Failed to post comment.');
			commentInputElement.value = '';
			if (result.comment) this._appendSingleCommentToUI(postElement, result.comment, true);
			const newCommentCount = typeof result.commentCount !== 'undefined' ? parseInt(result.commentCount, 10) : this._getUpdatedCommentCountFallback(postElement, 1);
			this._updatePostCommentCountsUI(postElement, newCommentCount);
			const commentsListArea = postElement.querySelector('.comments-list-area');
			const viewCommentsButtonMain = postElement.querySelector('.view-comments-button');
			if (commentsListArea && commentsListArea.classList.contains('hidden') && newCommentCount > 0) {
				commentsListArea.classList.remove('hidden');
				if (viewCommentsButtonMain && viewCommentsButtonMain.textContent.toLowerCase().startsWith('view')) viewCommentsButtonMain.textContent = 'Hide comments';
				commentsListArea.classList.add('comments-loaded');
			}
		} catch (error) {
			console.error('Comment submission error:', error);
			this._showInternalGenericModal('Error', `Error posting comment: ${error.message}`, 'error');
		} finally {
			commentInputElement.disabled = false;
			submitButtonElement.disabled = false;
			submitButtonElement.innerHTML = originalButtonText;
		}
	}
	async _handleViewComments(buttonElement) {
		const postId = buttonElement.dataset.postId;
		const postElement = buttonElement.closest('.post-item');
		if (!postElement || !postId) return;
		let commentsListArea = postElement.querySelector('.comments-list-area');
		if (!commentsListArea) return;
		if (commentsListArea.classList.contains('comments-loaded') && !commentsListArea.classList.contains('hidden')) {
			commentsListArea.classList.add('hidden');
			buttonElement.textContent = buttonElement.dataset.originalText || `View comments`;
			const loadMoreBtn = postElement.querySelector('.load-more-comments-button');
			if (loadMoreBtn) loadMoreBtn.classList.add('hidden');
		} else {
			if (!buttonElement.dataset.originalText) buttonElement.dataset.originalText = buttonElement.textContent;
			buttonElement.innerHTML = '<div class="loading-spinner w-4 h-4 inline-block mr-1"></div> Loading...';
			buttonElement.disabled = true;
			await this._fetchAndDisplayComments(postId, 1, commentsListArea, buttonElement);
			commentsListArea.classList.remove('hidden');
			commentsListArea.classList.add('comments-loaded');
		}
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

	async _fetchAndDisplayComments(postId, page = 1, commentsListArea, triggerButton, isLoadMore = false) {
		let resultData = null;
		try {
			// This is a GET request, so we use standard fetch
			const response = await fetch(API_ENDPOINTS.POST_COMMENTS(postId, page));
			if (!response.ok) {
				const errData = await response.json().catch(() => ({
					error: 'Failed to load comments.'
				}));
				throw new Error(errData.error);
			}
			resultData = await response.json();
			if (resultData.success && Array.isArray(resultData.comments)) {
				if (!isLoadMore) commentsListArea.innerHTML = '';
				if (resultData.comments.length === 0 && !isLoadMore && commentsListArea.children.length === 0) {
					commentsListArea.innerHTML = `<p class="text-xs text-gray-500 dark:text-gray-400 p-2">No comments yet for this post.</p>`;
				}
				const postElement = commentsListArea.closest('.post-item'); 
				resultData.comments.forEach(commentData => {
					this._appendSingleCommentToUI(postElement, commentData, false); 
				});
				const existingLoadMoreButton = commentsListArea.parentElement.querySelector('.load-more-comments-button');
				if (existingLoadMoreButton) existingLoadMoreButton.remove();
				if (resultData.pagination && resultData.pagination.current_page < resultData.pagination.total_pages) {
					const loadMoreButton = document.createElement('button');
					loadMoreButton.className = 'load-more-comments-button text-sm text-facebook hover:underline mt-2 dark:text-blue-400 ml-10 block';
					loadMoreButton.textContent = 'Load more comments';
					loadMoreButton.dataset.postId = postId;
					loadMoreButton.dataset.currentPage = String(resultData.pagination.current_page);
					commentsListArea.insertAdjacentElement('afterend', loadMoreButton);
				}
				if (triggerButton && !isLoadMore && (resultData.comments.length > 0 || commentsListArea.children.length > 0)) {
					commentsListArea.classList.remove('hidden');
				}
			} else {
				throw new Error(resultData.error || 'No comments found or error in response.');
			}
		} catch (error) {
			console.error(`Error fetching comments for post ${postId}:`, error);
			if (!isLoadMore && commentsListArea && commentsListArea.children.length === 0) {
				commentsListArea.innerHTML = `<p class="text-xs text-red-500 p-2">Error: ${error.message}</p>`;
			} else if (triggerButton && isLoadMore) { 
				this._showInternalGenericModal('Error', `Could not fetch more comments: ${error.message}`, 'error');
			} else if (triggerButton && !isLoadMore) { 
				this._showInternalGenericModal('Error', `Could not fetch comments: ${error.message}`, 'error');
			}
		} finally {
			if (triggerButton) {
				triggerButton.disabled = false;
				if (isLoadMore) {
					if (!resultData || !resultData.success || resultData.comments.length === 0 || (resultData.pagination && resultData.pagination.current_page >= resultData.pagination.total_pages)) {
						triggerButton.remove(); 
					} else {
						triggerButton.innerHTML = 'Load more comments';
					}
				} else {
					triggerButton.innerHTML = commentsListArea.classList.contains('hidden') ? (triggerButton.dataset.originalText || 'View comments') : 'Hide comments';
					if (commentsListArea.children.length === 0 ||
						(commentsListArea.firstElementChild && (commentsListArea.firstElementChild.textContent.startsWith('Error:') || commentsListArea.firstElementChild.textContent.startsWith('No comments yet')))) {
						triggerButton.textContent = triggerButton.dataset.originalText || 'View comments';
						commentsListArea.classList.remove('comments-loaded');
					}
				}
			}
		}
	}

	_appendSingleCommentToUI(postElement, commentData, prependNew = false) {
		if (!postElement || !commentData) return;
		let commentsListArea = postElement.querySelector('.comments-list-area');
		if (!commentsListArea) return;
		if (commentsListArea.childElementCount === 1 && (commentsListArea.firstElementChild.textContent.startsWith("No comments yet") || commentsListArea.firstElementChild.textContent.startsWith("Error:"))) {
			commentsListArea.innerHTML = '';
		}
		commentsListArea.classList.remove('hidden');

		const postOwnerId = postElement.dataset.postOwnerId; 
		const commentDiv = this._createCommentElement(commentData, postOwnerId);
		if (commentDiv) {
			if (prependNew) commentsListArea.insertBefore(commentDiv, commentsListArea.firstChild);
			else commentsListArea.appendChild(commentDiv);
		}
	}

	_createCommentElement(commentData, postOwnerId = null) {
        const commentDiv = document.createElement('div');
        commentDiv.className = 'comment-item group flex items-start space-x-2 text-sm py-1'; 
        commentDiv.dataset.commentId = commentData.id;

		const rawCommentName = this._getDisplayName(commentData) || commentData.username || 'User';
		const avatar = commentData.user_avatar_fallback || this._generateClientFallbackSVG(rawCommentName, 24);
		const userName = this._sanitizeHTML(rawCommentName);
        const commentContent = this._sanitizeHTML(commentData.content, true); 

        let actionsHTML = ''; 
        const isCommentAuthor = this.currentUserId && commentData.user_id && parseInt(this.currentUserId) === parseInt(commentData.user_id);
        const isPostOwnerCurrent = this.currentUserId && postOwnerId && parseInt(this.currentUserId) === parseInt(postOwnerId);

        if (isCommentAuthor) { 
            actionsHTML += `
                <button class="edit-comment-button text-xs text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 
                               opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-150 p-1" 
                        data-comment-id="${commentData.id}" 
                        title="Edit comment" 
                        aria-label="Edit comment">
                    <i class="fas fa-pencil-alt"></i>
                </button>
            `;
        }
        if (isCommentAuthor || isPostOwnerCurrent) { 
            actionsHTML += `
                <button class="delete-comment-button text-xs text-gray-400 hover:text-red-500 dark:hover:text-red-400 
                               opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-150 p-1" 
                        data-comment-id="${commentData.id}" 
                        title="Delete comment" 
                        aria-label="Delete comment">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
        }
        
        const commentAuthorProfileLink = `/profile/${commentData.user_id}`; 

        commentDiv.innerHTML = `
            <img src="${avatar}" alt="${userName}" class="w-6 h-6 rounded-full object-cover mt-1 flex-shrink-0">
            <div class="comment-content-bubble flex-1 bg-gray-100 dark:bg-dark-600 p-2 rounded-lg relative">
                <div class="flex justify-between items-start">
                    <a href="${commentAuthorProfileLink}" class="font-semibold dark:text-white hover:underline cursor-pointer mr-2">${userName}</a>
                    <div class="comment-actions flex items-center space-x-1 ml-auto">
                        ${actionsHTML}
                    </div>
                </div>
                <p class="comment-text-display dark:text-gray-300 leading-snug whitespace-pre-wrap mt-0.5">${commentContent}</p>
                <div class="comment-edit-area hidden mt-1"></div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <span class="comment-timeago" data-timestamp="${commentData.created_at}">${this._timeAgo(commentData.created_at)}</span>
                    ${commentData.updated_at && commentData.updated_at !== commentData.created_at ? ` · <em class="text-xs">edited</em>` : ''}
                </p>
            </div>
        `;
        return commentDiv;
    }

	async _handleDeleteComment(buttonElement) {
		const commentId = buttonElement.dataset.commentId;
		const commentItemElement = buttonElement.closest('.comment-item');
		const postElement = buttonElement.closest('.post-item');

		if (!commentId || !commentItemElement || !postElement) {
			this._showInternalGenericModal('Error', 'Could not delete comment: missing information.', 'error');
			return;
		}

		const confirmed = await this._showInternalConfirmModal(
			'Confirm Delete Comment',
			'Are you sure you want to delete this comment? This action cannot be undone.',
			'Delete', 'Cancel', 'danger'
		);

		if (!confirmed) return;

		buttonElement.disabled = true;
		const icon = buttonElement.querySelector('i');
		const originalIconClass = icon ? icon.className : null;
		if (icon) icon.className = 'fas fa-spinner fa-spin';

		try {
			const response = await this._apiFetch(API_ENDPOINTS.DELETE_COMMENT(commentId), {
				method: 'POST',
				headers: {
					'Accept': 'application/json'
				}
			});
			const result = await response.json();

			if (!response.ok || !result.success) {
				throw new Error(result.error || result.message || 'Server error deleting comment.');
			}

			commentItemElement.style.transition = 'opacity 0.3s ease-out, max-height 0.3s ease-out, margin 0.3s ease-out, padding 0.3s ease-out';
			commentItemElement.style.opacity = '0';
			commentItemElement.style.maxHeight = '0px';
			commentItemElement.style.margin = '0px';
			commentItemElement.style.padding = '0px';
			commentItemElement.style.overflow = 'hidden';

			setTimeout(() => {
				commentItemElement.remove();
				const newCommentCount = typeof result.new_comment_count !== 'undefined' ?
					parseInt(result.new_comment_count, 10) :
					this._getUpdatedCommentCountFallback(postElement, -1);
				this._updatePostCommentCountsUI(postElement, newCommentCount);
			}, 300);

		} catch (error) {
			console.error('Error deleting comment:', error);
			this._showInternalGenericModal('Error', `Failed to delete comment: ${error.message}`, 'error');
			buttonElement.disabled = false;
			if (icon && originalIconClass) icon.className = originalIconClass;
		}
	}

	_getUpdatedCommentCountFallback(postElement, change) {
		const commentCountSpanText = postElement.querySelector('.comment-count-display-text');
		let currentCount = 0;
		if (commentCountSpanText) {
			const countMatch = commentCountSpanText.textContent.match(/(\d+)/);
			if (countMatch) currentCount = parseInt(countMatch[0], 10);
		}
		return Math.max(0, currentCount + change);
	}

	_updatePostCommentCountsUI(postElement, newCommentCount) {
		if (!postElement) return;

		const commentCountSpanText = postElement.querySelector('.comment-count-display-text');
		const viewCommentsButton = postElement.querySelector('.view-comments-button');
		const viewCommentsTrigger = postElement.querySelector('.view-comments-trigger');
		const commentsListArea = postElement.querySelector('.comments-list-area');

		const commentsPluralized = `comment${newCommentCount !== 1 ? 's' : ''}`;
		const viewCommentsTextDefault = `View ${newCommentCount} ${commentsPluralized}`;

		if (commentCountSpanText) {
			commentCountSpanText.textContent = `${newCommentCount} ${commentsPluralized}`;
		}

		if (viewCommentsButton) {
			viewCommentsButton.dataset.originalText = viewCommentsTextDefault;
			if (commentsListArea && !commentsListArea.classList.contains('hidden')) {
				if (newCommentCount === 0 && commentsListArea.querySelectorAll('.comment-item').length === 0) {
					viewCommentsButton.textContent = viewCommentsTextDefault;
					commentsListArea.innerHTML = `<p class="text-xs text-gray-500 dark:text-gray-400 p-2">No comments yet for this post.</p>`;
				} else if (viewCommentsButton.textContent.toLowerCase().startsWith('view')) {
					viewCommentsButton.textContent = viewCommentsTextDefault;
				}
			} else {
				viewCommentsButton.textContent = viewCommentsTextDefault;
			}
		}

		if (viewCommentsTrigger) {
			if (newCommentCount === 0) {
				viewCommentsTrigger.classList.add('hidden');
				if (commentsListArea && !commentsListArea.classList.contains('hidden')) {
					commentsListArea.classList.add('hidden');
					commentsListArea.classList.remove('comments-loaded');
				}
			} else {
				viewCommentsTrigger.classList.remove('hidden');
			}
		}

		if (commentsListArea && !commentsListArea.classList.contains('hidden') &&
			commentsListArea.querySelectorAll('.comment-item').length === 0 && newCommentCount === 0) {
			commentsListArea.innerHTML = `<p class="text-xs text-gray-500 dark:text-gray-400 p-2">No comments yet for this post.</p>`;
			if (viewCommentsButton && viewCommentsButton.textContent.toLowerCase().startsWith('hide')) {
				viewCommentsButton.textContent = viewCommentsButton.dataset.originalText || viewCommentsTextDefault;
			}
		}
	}
}


document.addEventListener('DOMContentLoaded', () => {
	if (document.getElementById('postsContainer')) {
		window.globalPostFeedManager = new PostFeedManager('postsContainer', 'loadingIndicator');
		const style = document.createElement('style');
		style.textContent = `
            .animate-fade-in-up { animation: fadeInUp 0.3s ease-out forwards; }
            .animate-fade-out-down { animation: fadeOutDown 0.3s ease-in forwards; }
            @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
            @keyframes fadeOutDown { from { opacity: 1; transform: translateY(0) scale(1); } to { opacity: 0; transform: translateY(20px) scale(0.95); } }
            .fade-out-post { transition: opacity 0.3s ease-out, transform 0.3s ease-out, max-height 0.3s ease-out, padding 0.3s ease-out, margin 0.3s ease-out; opacity: 0; transform: scale(0.95); max-height: 0px !important; padding-top: 0px !important; padding-bottom: 0px !important; margin-top: 0px !important; margin-bottom: 0px !important; overflow: hidden; border: none !important; }
        `; // Enhanced fade-out-post for smoother removal
		document.head.appendChild(style);
	} else {
		console.warn("PostFeedManager: Main 'postsContainer' div not found. Feed activities will not initialize.");
	}

	setInterval(() => {
		document.querySelectorAll('.comment-timeago[data-timestamp], .post-timeago[data-timestamp]').forEach(el => {
			if (el.offsetParent !== null) { // Check if element is visible
				const timestamp = el.dataset.timestamp;
                let originalTextContent = el.textContent;
                let mainTimeAgo = originalTextContent.split(' · edited')[0]; // Get the part before " · edited"
				
				let timeAgoFunc = null;
				if (window.globalPostFeedManager && typeof window.globalPostFeedManager._timeAgo === 'function') {
					timeAgoFunc = window.globalPostFeedManager._timeAgo.bind(window.globalPostFeedManager);
				} else if (window.SmartFed && typeof window.SmartFed._formatTimeAgo === 'function') { // Fallback if SmartFed is used elsewhere
					timeAgoFunc = window.SmartFed._formatTimeAgo.bind(window.SmartFed);
				}

				if (timestamp && timeAgoFunc) {
                    let newTimeAgo = timeAgoFunc(timestamp);
                    if (originalTextContent.includes(' · edited')) {
                        el.textContent = newTimeAgo + ' · edited';
                    } else {
                        el.textContent = newTimeAgo;
                    }
				}
			}
		});
	}, 60000); // Update every minute
});