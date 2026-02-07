// profile.js
// =================================================================================
// START: COMPLETE AND UNTRUNCATED JAVASCRIPT (FULLY REFACTORED)
// =================================================================================

/**
 * Global Fetch Wrapper
 * Overrides the default window.fetch to automatically add CSRF tokens
 * and common headers to same-origin requests. It does NOT handle
 * response errors, as that will be done within the application's API handler.
 */
(function(window) {
    'use strict';
    const originalFetch = window.fetch;
    const siteBaseUrl = window.location.origin;

    window.fetch = function(url, options = {}) {
        const absoluteUrl = new URL(url, siteBaseUrl).href;

        if (absoluteUrl.startsWith(siteBaseUrl)) {
            options.headers = options.headers || {};
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const method = (options.method || 'GET').toUpperCase();

            if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                options.headers['X-CSRF-TOKEN'] = csrfToken;
            }
            options.headers['X-Requested-With'] = 'XMLHttpRequest';
            options.headers['Accept'] = 'application/json';
        }

        return originalFetch.call(this, url, options);
    };
})(window);


/**
 * ProfileApp Class
 * A single controller to manage all dynamic functionality on the profile page.
 */
(function(window, document, undefined) {
    'use strict';

    class ProfileApp {
        constructor() {
            // Check for required global variables set by the server
            if (typeof IS_OWN_PROFILE === 'undefined' || typeof PROFILE_USER_DATA === 'undefined' || typeof LOGGED_IN_USER_DATA === 'undefined') {
                console.error('ProfileApp cannot initialize: Required global variables are missing.');
                return;
            }
            
            this.isOwnProfile = IS_OWN_PROFILE;
            this.profileUserData = PROFILE_USER_DATA;
            this.loggedInUserData = LOGGED_IN_USER_DATA;
            
            this._cacheDOMElements();
            this._bindEvents();
            this._initCoreFeatures();
        }

        /**
         * Caches frequently accessed DOM elements.
         */
        _cacheDOMElements() {
            // General
            this.darkModeToggleBtn = document.getElementById('darkModeToggle');
            
            // Profile display elements
            this.profileCoverImageEl = document.getElementById('profileCoverImage');
            this.profilePictureImgEl = document.getElementById('profilePictureImg');
            this.createPostAvatarEl = document.getElementById('createPostAvatar');
            
            // Editing elements (only exist for own profile)
            this.editCoverPhotoButtonEl = document.getElementById('editCoverPhotoButton');
            this.coverPhotoInputEl = document.getElementById('coverPhotoInput');
            this.editProfilePictureButtonEl = document.getElementById('editProfilePictureButton');
            this.profilePictureInputEl = document.getElementById('profilePictureInput');

            // Post creation elements
            this.postContentTextarea = document.getElementById('profile-post-textarea');
            this.postVisibilitySelect = document.getElementById('profile-post-visibility-select');
            this.postSubmitButton = document.getElementById('profile-post-submit-btn');
            this.askSaiButton = document.getElementById('profile-post-ask-sai-btn');

            // Friends card elements
            this.friendGridContainerEl = document.getElementById('friendGridContainer');

            // Action buttons (only exist on other's profiles)
            this.addFriendBtn = document.getElementById('addFriendBtn');
            this.unfriendBtn = document.getElementById('unfriendBtn');
            this.declineRequestBtn = document.getElementById('declineRequestBtn');
            this.photoGridContainerEl = document.getElementById('photoGridContainer');
            this.videoGridContainerEl = document.getElementById('videoGridContainer');
            this.checkinListContainer = document.getElementById('checkinListContainer');

            // check ins
            this.addLocationBtn = document.getElementById('addLocationBtn');
            this.postLocationDisplay = document.getElementById('postLocationDisplay');
            this.locationNameText = document.getElementById('locationNameText');
            this.removeLocationBtn = document.getElementById('removeLocationBtn');

            if (this.isOwnProfile) {
                this.editIntroButton = document.getElementById('editIntroButton');
                this.mainEditProfileBtn = document.getElementById('mainEditProfileBtn');
                this.addBioButton = document.getElementById('addBioButton');
                this.cancelIntroButton = document.getElementById('cancelIntroButton');
                this.saveIntroButton = document.getElementById('saveIntroButton');
                
                this.introViewState = document.getElementById('introViewState');
                this.introEditState = document.getElementById('introEditState');

                // View elements
                this.viewHeadline = document.getElementById('viewHeadline');
                this.viewBioContainer = document.getElementById('viewBioContainer');
                this.viewWorkPlaceItem = document.getElementById('viewWorkPlaceItem');
                this.viewEducationItem = document.getElementById('viewEducationItem');
                this.viewCurrentCityItem = document.getElementById('viewCurrentCityItem');
                
                // Edit inputs
                this.inputHeadline = document.getElementById('inputHeadline');
                this.inputBio = document.getElementById('inputBio');
                this.inputWorkPlace = document.getElementById('inputWorkPlace');
                this.inputEducation = document.getElementById('inputEducation');
                this.inputCurrentCity = document.getElementById('inputCurrentCity');
                this.profileIntroCard = document.getElementById('profileIntroCard');
            }
        }

        /**
         * Binds all event listeners for the page.
         */
        _bindEvents() {
            if (this.darkModeToggleBtn) {
                this.darkModeToggleBtn.addEventListener('click', () => this._toggleDarkMode());
            }

            if (this.isOwnProfile) {
                if (this.saveIntroButton) this.saveIntroButton.addEventListener('click', () => this._handleSaveIntro());
                if (this.editIntroButton) this.editIntroButton.addEventListener('click', () => this._toggleIntroEdit(true));
                if (this.editCoverPhotoButtonEl) this.editCoverPhotoButtonEl.addEventListener('click', () => this.coverPhotoInputEl.click());
                if (this.addBioButton) this.addBioButton.addEventListener('click', () => this._toggleIntroEdit(true));
                if (this.cancelIntroButton) this.cancelIntroButton.addEventListener('click', () => this._toggleIntroEdit(false));
                if (this.coverPhotoInputEl) this.coverPhotoInputEl.addEventListener('change', (e) => this._handleImageUpload(e, 'cover'));
                if (this.editProfilePictureButtonEl) this.editProfilePictureButtonEl.addEventListener('click', () => this.profilePictureInputEl.click());
                if (this.profilePictureInputEl) this.profilePictureInputEl.addEventListener('change', (e) => this._handleImageUpload(e, 'picture'));
            }

            if (this.postSubmitButton) this.postSubmitButton.addEventListener('click', () => this._handlePostSubmit());
            if (this.askSaiButton) this.askSaiButton.addEventListener('click', () => this._handleAskSai());

            if (this.addFriendBtn) this.addFriendBtn.addEventListener('click', () => this._handleAddFriend());
            if (this.unfriendBtn) {
                this.unfriendBtn.addEventListener('click', () => this._handleUnfriend());
            }
            if (this.declineRequestBtn) {
                this.declineRequestBtn.addEventListener('click', (event) => this._handleDeclineRequest(event));
            }

            if (this.aboutTab) {
                this.aboutTab.addEventListener('click', (event) => this._handleNavTabClick(event));
            }

            // --- THIS IS THE NEW CODE BLOCK ---
            if (this.mainEditProfileBtn) {

                this.mainEditProfileBtn.addEventListener('click', (event) => {
                    // event.preventDefault(); // Good practice to prevent any default browser action

                    // Find the pencil icon button and programmatically click it
                    if (this.editIntroButton) {
                        this.editIntroButton.click(); // This triggers the pencil's own click event
                    }

                    // 1. Switch the intro card to its edit state
                    this._toggleIntroEdit(true)

                    // 2. Smoothly scroll to the intro card
                    if (this.profileIntroCard) {
                        this.profileIntroCard.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center' // 'center' is often best to avoid sticky headers
                        });
                    }
                });
            }

            if (this.addLocationBtn) {
                this.addLocationBtn.addEventListener('click', () => this._handleAddLocation());
            }
            if (this.removeLocationBtn) {
                this.removeLocationBtn.addEventListener('click', () => this._handleRemoveLocation());
            }

            // NEW: Listen for clicks inside the posts container on the profile page
            const postsContainer = document.getElementById('postsContainer');
            if (postsContainer) {
                postsContainer.addEventListener('click', (event) => {
                    const shareButton = event.target.closest('.share-button');
                    if (shareButton) {
                        event.preventDefault();
                        this._handleSharePost(shareButton);
                    }
                    // You could also add handlers for like, comment, etc. here if needed
                });
            }
        }

        /**
         * Handles clicks on navigation tabs like "About".
         * @param {MouseEvent} event - The click event.
         */
        _handleNavTabClick(event) {
            // 1. Prevent the browser from jumping to the #hash link instantly
            event.preventDefault();

            const clickedTab = event.currentTarget;
            const targetId = new URL(clickedTab.href).hash; // Gets the #profileIntroCard part
            const targetElement = document.querySelector(targetId);

            // 2. Smoothly scroll to the target element
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start' // Aligns the top of the card with the top of the viewport
                });
            }

            // 3. Update the visual state to show this is the active tab
            this._setActiveTab(clickedTab);
        }

        /**
         * Manages the "active-tab" class for the profile navigation.
         * @param {HTMLElement} activeTab - The tab element that should be marked as active.
         */
        _setActiveTab(activeTab) {
            if (!this.profileNavContainer) return;

            // Find all link elements within the navigation container
            const allTabs = this.profileNavContainer.querySelectorAll('.profile-nav-link');

            // First, remove the active class from all tabs
            allTabs.forEach(tab => {
                tab.classList.remove('active-tab');
            });

            // Then, add the active class to the one that was just clicked
            activeTab.classList.add('active-tab');
        }

        /**
         * Toggles the intro card between view and edit modes.
         * @param {boolean} isEditing - True to show edit form, false to show view info.
         */
        _toggleIntroEdit(isEditing) {
            if (!this.introViewState || !this.introEditState) return;

            this.introViewState.classList.toggle('hidden', isEditing);
            this.introEditState.classList.toggle('hidden', !isEditing);
            
            // The "Edit Intro" button (pen icon) should only be visible in view mode.
            if (this.editIntroButton) {
                this.editIntroButton.classList.toggle('hidden', isEditing);
            }
        }

        /**
         * Gathers data from intro inputs, sends it to the server, and updates the UI.
         */
        async _handleSaveIntro() {
            const saveBtn = this.saveIntroButton;
            const buttonText = saveBtn.querySelector('.button-text');
            const spinner = saveBtn.querySelector('.fa-spinner');

            // Show loading state
            saveBtn.disabled = true;
            buttonText.classList.add('hidden');
            spinner.classList.remove('hidden');

            const introData = {
                // ADDED headline and bio
                headline: this.inputHeadline.value.trim(),
                bio: this.inputBio.value.trim(),
                work_place: this.inputWorkPlace.value.trim(),
                education: this.inputEducation.value.trim(),
                current_city: this.inputCurrentCity.value.trim(),
            };

            try {
                const response = await this._fetchJsonApi('/profile/intro', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(introData)
                });
                
                // Update the view state with the new, sanitized data from the server
                this._updateIntroView(response.updated_data);
                
                this._showTemporaryMessage('Intro updated successfully!', 'success');
                this._toggleIntroEdit(false); // Switch back to view mode

            } catch (error) {
                this._showNotificationModal('Error', error.message, 'error');
            } finally {
                // Restore button state
                saveBtn.disabled = false;
                buttonText.classList.remove('hidden');
                spinner.classList.add('hidden');
            }
        }

        /**
         * Helper function to dynamically update the intro view state after saving.
         * @param {object} data - The sanitized data object from the server.
         */
        _updateIntroView(data) {
            // --- Update Headline ---
            if (data.headline) {
                this.viewHeadline.textContent = data.headline;
                this.viewHeadline.classList.remove('hidden');
            } else {
                this.viewHeadline.textContent = '';
                this.viewHeadline.classList.add('hidden');
            }

            // --- Update Bio ---
            this.viewBioContainer.innerHTML = ''; // Clear previous content
            if (data.bio) {
                const p = document.createElement('p');
                p.id = 'viewBio';
                p.className = 'text-center text-gray-600 dark:text-gray-400';
                p.innerHTML = this._sanitizeHTML(data.bio).replace(/\n/g, '<br>');
                this.viewBioContainer.appendChild(p);
            } else if (this.isOwnProfile) {
                // Re-create the "Add Bio" button if the bio is now empty
                const button = document.createElement('button');
                button.id = 'addBioButton';
                button.className = 'w-full bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 text-black dark:text-white font-semibold py-2 px-4 rounded-md';
                button.textContent = 'Add Bio';
                button.addEventListener('click', () => this._toggleIntroEdit(true));
                this.viewBioContainer.appendChild(button);
                this.addBioButton = button; // Re-cache the new button
            }

            // --- Update List Items (Work, Education, City) ---
            const updateListItem = (itemEl, textPrefix, value) => {
                if (!itemEl) return;
                const span = itemEl.querySelector('span');
                if (value) {
                    span.textContent = `${textPrefix} ${value}`;
                    itemEl.classList.remove('hidden');
                } else {
                    itemEl.classList.add('hidden');
                }
            };

            updateListItem(this.viewWorkPlaceItem, 'Works at', data.work_place);
            updateListItem(this.viewEducationItem, 'Studied at', data.education);
            updateListItem(this.viewCurrentCityItem, 'Lives in', data.current_city);
        }

        /**
         * Initializes all core features on page load.
         */
        _initCoreFeatures() {
            this._applyDarkModePreference();
            this._initializeProfileImages();
            this._initFriendCard();
            this._initPhotosCard();
            this._initVideosCard();
            this._initCheckinsCard();
        }

        /**
         * Fetches and displays the latest check-in posts for the user's profile.
         */
        _initCheckinsCard() {
            if (!this.checkinListContainer || !this.profileUserData?.id) return;

            this._fetchJsonApi(`/profile/${this.profileUserData.id}/checkins`)
                .then(data => {
                    this.checkinListContainer.innerHTML = ''; // Clear "Loading..."

                    if (data.checkins && data.checkins.length > 0) {
                        data.checkins.forEach(checkin => {
                            const postLink = `/post/${checkin.id}`;
                            const relativeTime = this._formatRelativeTime(checkin.created_at);

                            const checkinEl = document.createElement('div');
                            checkinEl.className = 'flex items-start space-x-3';
                            
                            checkinEl.innerHTML = `
                                <div class="mt-1 flex-shrink-0">
                                    <span class="bg-gray-200 dark:bg-dark-600 rounded-full h-8 w-8 flex items-center justify-center">
                                        <i class="fas fa-map-marker-alt text-gray-600 dark:text-gray-300"></i>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        <a href="${postLink}" class="hover:underline">${this._sanitizeHTML(checkin.location_name)}</a>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ${this._sanitizeHTML(checkin.content)}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        ${relativeTime}
                                    </p>
                                </div>
                            `;
                            this.checkinListContainer.appendChild(checkinEl);
                        });
                    } else {
                        this.checkinListContainer.innerHTML = `<p class="text-sm text-gray-500 dark:text-gray-400">No check-ins to show.</p>`;
                    }
                })
                .catch(error => {
                    console.error("Failed to load profile check-ins:", error);
                    this.checkinListContainer.innerHTML = `<p class="text-sm text-red-500">Could not load check-ins.</p>`;
                });
        }

        /**
         * Formats a date string into a relative time (e.g., "5 hours ago").
         * @param {string} dateString - An ISO 8601 date string (YYYY-MM-DD HH:MM:SS).
         * @returns {string} The formatted relative time.
         */
        _formatRelativeTime(dateString) {
            const now = new Date();
            const past = new Date(dateString.replace(' ', 'T') + 'Z'); // Handle UTC
            const seconds = Math.floor((now - past) / 1000);

            let interval = seconds / 31536000;
            if (interval > 1) return past.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
            
            interval = seconds / 2592000;
            if (interval > 1) return past.toLocaleDateString(undefined, { month: 'long', day: 'numeric' });

            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + " days ago";
            
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + " hours ago";
            
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + " minutes ago";
            
            return "Just now";
        }

        /**
         * Fetches and displays the latest 9 videos for the user's profile.
         * Renders a muted, looping <video> tag for each item in the grid.
         */
        _initVideosCard() {
            if (!this.videoGridContainerEl || !this.profileUserData?.id) return;

            this._fetchJsonApi(`/profile/${this.profileUserData.id}/videos`)
                .then(data => {
                    this.videoGridContainerEl.innerHTML = ''; // Clear "Loading..."

                    if (data.videos && data.videos.length > 0) {
                        data.videos.forEach(video => {
                            const videoUrl = video.image; // The direct URL to the .mp4 file
                            const postUrl = `/post/${video.id}`; // Link to the specific post page

                            // Create a container that links to the post
                            const videoLinkContainer = document.createElement('a');
                            videoLinkContainer.href = postUrl;
                            videoLinkContainer.className = 'relative block aspect-w-1 aspect-h-1 group bg-black rounded-md overflow-hidden'; // Square aspect ratio

                            // Create the video element itself
                            const videoElement = document.createElement('video');
                            videoElement.src = this._sanitizeHTML(videoUrl);
                            videoElement.className = 'w-full h-full object-cover';
                            
                            // --- Attributes to make it behave like a thumbnail GIF ---
                            videoElement.muted = true;      // Essential for autoplay in most browsers
                            videoElement.loop = true;       // Makes it loop like a GIF
                            videoElement.playsInline = true; // Important for iOS
                            videoElement.preload = 'metadata'; // Only load enough to show the first frame

                            // Play the video when the user hovers over it
                            videoLinkContainer.addEventListener('mouseenter', () => videoElement.play());
                            videoLinkContainer.addEventListener('mouseleave', () => videoElement.pause());

                            // Add a "Play" icon overlay that shows on hover
                            const overlay = document.createElement('div');
                            overlay.className = 'absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 flex items-center justify-center transition-all duration-300';
                            overlay.innerHTML = '<i class="fas fa-play text-white text-3xl opacity-0 group-hover:opacity-100 transition-opacity"></i>';

                            // Assemble the pieces
                            videoLinkContainer.appendChild(videoElement);
                            videoLinkContainer.appendChild(overlay);
                            this.videoGridContainerEl.appendChild(videoLinkContainer);
                        });
                    } else {
                        this.videoGridContainerEl.innerHTML = `<p class="col-span-3 text-sm text-gray-500 dark:text-gray-400">No videos to show.</p>`;
                    }
                })
                .catch(error => {
                    console.error("Failed to load profile videos:", error);
                    this.videoGridContainerEl.innerHTML = `<p class="col-span-3 text-sm text-red-500">Could not load videos.</p>`;
                });
        }

        /**
         * A centralized, robust method for handling all API calls.
         * It handles errors, authentication issues, and JSON parsing.
         */
        async _fetchJsonApi(endpoint, options = {}) {
            try {
                const response = await fetch(endpoint, options);
                
                if (response.status === 401) {
                    this._showNotificationModal('Session Expired', 'Your session has ended. Please log in again.', 'error');
                    setTimeout(() => window.location.href = '/login', 3000);
                    throw new Error('Unauthorized');
                }
                if (response.status === 403) {
                     this._showNotificationModal('Security Error', 'A security error (e.g., CSRF) occurred. Please refresh the page and try again.', 'error');
                     throw new Error('Forbidden');
                }

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || `Server returned status ${response.status}`);
                }
                if (data.success === false) {
                    throw new Error(data.message || 'The server reported a failure.');
                }
                return data;

            } catch (error) {
                // Prevent logging for auth errors we already handled with a modal
                if (error.message !== 'Unauthorized' && error.message !== 'Forbidden') {
                    console.error(`API Error on ${options.method || 'GET'} ${endpoint}:`, error);
                }
                throw error; // Re-throw to be caught by the calling function
            }
        }

        // --- FEATURE: Dark Mode ---
        
        _applyDarkModePreference() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            document.documentElement.classList.toggle('dark', isDark);
            if(this.darkModeToggleBtn) {
                this.darkModeToggleBtn.querySelector('.light-mode-text')?.classList.toggle('hidden', isDark);
                this.darkModeToggleBtn.querySelector('.dark-mode-text')?.classList.toggle('hidden', !isDark);
            }
        }

        _toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
            this._applyDarkModePreference();
        }
        
        // --- FEATURE: Profile Image Management ---

        _initializeProfileImages() {
            // Set Cover Photo
            if (this.profileCoverImageEl && this.profileUserData.cover_photo) {
                this.profileCoverImageEl.style.backgroundImage = `url('${this._sanitizeHTML(this.profileUserData.cover_photo)}')`;
            }
            // Set Profile Picture
            if (this.profilePictureImgEl) {
                this.profilePictureImgEl.src = this.profileUserData.profile_picture || this._pm_generateFallbackAvatar(this.profileUserData.full_name);
            }
            // Set Post Creator Avatar
            if (this.createPostAvatarEl) {
                const avatarSrc = this.isOwnProfile ? this.profileUserData.profile_picture : this.loggedInUserData.profile_picture;
                const avatarName = this.isOwnProfile ? this.profileUserData.full_name : this.loggedInUserData.user_full_name;
                this.createPostAvatarEl.src = avatarSrc || this._pm_generateFallbackAvatar(avatarName);
            }
        }

        _handleImageUpload(event, type) {
            const file = event.target.files[0];
            const buttonEl = type === 'cover' ? this.editCoverPhotoButtonEl : this.editProfilePictureButtonEl;
            if (!file || !buttonEl) return;

            const maxSize = type === 'cover' ? 10 * 1024 * 1024 : 8 * 1024 * 1024; // 10MB for cover, 8MB for profile
            if (file.size > maxSize) {
                this._showNotificationModal('Error', `File is too large. Max size is ${maxSize / 1024 / 1024}MB.`, 'error');
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                this._showNotificationModal('Error', 'Invalid file type. Please use JPG, PNG, GIF, or WebP.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append(type === 'cover' ? 'cover_photo_file' : 'profile_picture_file', file);

            const originalButtonContent = buttonEl.innerHTML;
            buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            buttonEl.disabled = true;

            const endpoint = type === 'cover' ? '/profile/cover' : '/profile/picture';
            
            this._fetchJsonApi(endpoint, { method: 'POST', body: formData })
                .then(data => {
                    const newUrl = data.new_cover_photo_url || data.new_profile_picture_url;
                    if (type === 'cover') {
                        this.profileCoverImageEl.style.backgroundImage = `url('${this._sanitizeHTML(newUrl)}')`;
                    } else {
                        this.profilePictureImgEl.src = newUrl;
                        if (this.createPostAvatarEl) this.createPostAvatarEl.src = newUrl;
                        // Also update header avatar if it exists
                        const headerAvatar = document.getElementById('headerUserMenuImage');
                        if (headerAvatar) headerAvatar.src = newUrl;
                    }
                    this._showTemporaryMessage(data.message || 'Image updated!', 'success', buttonEl);
                })
                .catch(error => this._showNotificationModal('Upload Failed', error.message, 'error'))
                .finally(() => {
                    buttonEl.innerHTML = originalButtonContent;
                    buttonEl.disabled = false;
                    event.target.value = ''; // Clear file input
                });
        }
        
        // --- FEATURE: Post Creation ---

        async _handlePostSubmit() {
            if (!this.postContentTextarea) {
                console.error("Post content textarea not found.");
                return;
            }

            const content = this.postContentTextarea.value.trim();
            const locationName = this.currentPostLocation;

            if (!content && !locationName) {
                this._showNotificationModal('Empty Post', 'A post must have text content or a location.', 'error');
                return;
            }

            const originalButtonHTML = this.postSubmitButton.innerHTML;
            this.postSubmitButton.disabled = true;
            this.postSubmitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

            const postData = {
                content: content,
                visibility: this.postVisibilitySelect.value,
                post_type: 'text',
                profile_owner_id: this.profileUserData.id,
                location_name: locationName
            };
            
            // THE CRITICAL CHANGE IS HERE
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('/post', { // Keep using the unified /post endpoint
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(postData)
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Server error');
                
                // This is the key. Tell the global manager to handle the new post.
                if (window.globalPostFeedManager) {
                    window.globalPostFeedManager.prependNewPost(result.post);
                } else {
                    console.warn("Global feed manager not found. Reloading page.");
                    window.location.reload();
                }

                // Reset the UI
                this.postContentTextarea.value = '';
                this._handleRemoveLocation();

            } catch (error) {
                this._showNotificationModal('Post Failed', error.message, 'error');
            } finally {
                this.postSubmitButton.disabled = false;
                this.postSubmitButton.innerHTML = originalButtonHTML;
            }
        }
                
        /**
         * Opens a modal to ask for a location and updates the UI.
         * Now with safety checks for all DOM manipulations.
         */
        async _handleAddLocation() {
            const locationName = await this._showInputModal('Where are you?', {
                placeholder: 'e.g., Eiffel Tower, Paris',
                initialValue: this.currentPostLocation || '',
                confirmText: 'Set Location'
            });

            if (locationName === null) {
                return;
            }

            const trimmedLocation = locationName.trim();
            
            // --- THIS IS THE FIX ---
            // Check if the UI elements exist before trying to update them.
            if (this.locationNameText && this.postLocationDisplay) {
                if (trimmedLocation) {
                    this.currentPostLocation = trimmedLocation;
                    this.locationNameText.textContent = this.currentPostLocation;
                    this.postLocationDisplay.classList.remove('hidden');
                    this.postLocationDisplay.classList.add('flex');
                } else {
                    this._handleRemoveLocation();
                }
            } else {
                // If the elements don't exist, we can still store the value,
                // but we can't update the UI. This prevents the error.
                this.currentPostLocation = trimmedLocation || null;
            }
        }

        /**
         * Displays an elegant modal with a live location search input.
         * Fetches suggestions from the local backend API as the user types.
         * @param {string} title - The title of the modal.
         * @param {object} options - Configuration options.
         * @returns {Promise<string|null>} A promise that resolves with the selected location name, or null if canceled.
         */
        _showInputModal(title, options = {}) {
            const {
                placeholder = '',
                initialValue = '',
                confirmText = 'Confirm',
                cancelText = 'Cancel'
            } = options;

            return new Promise((resolve) => {
                const existingModal = document.getElementById('genericInputModal');
                if (existingModal) existingModal.remove();

                const modalHTML = `
                    <div id="genericInputModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0" role="dialog" aria-modal="true">
                        <div class="bg-white dark:bg-dark-700 rounded-lg shadow-xl w-full max-w-sm m-4 transform transition-transform duration-300 scale-95">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">${this._sanitizeHTML(title)}</h3>
                                
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-map-marker-alt text-gray-400"></i>
                                    </div>
                                    <input type="text" id="modalTextInput" class="w-full pl-10 p-2 rounded-md border-gray-300 dark:border-dark-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-dark-600 dark:text-white focus:outline-none" placeholder="${this._sanitizeHTML(placeholder)}" value="${this._sanitizeHTML(initialValue)}" autocomplete="off">
                                </div>

                                <ul id="locationResultsList" class="mt-2 border dark:border-dark-500 rounded-md max-h-48 overflow-y-auto hidden">
                                    <!-- Live search results will be injected here -->
                                </ul>

                                <div class="flex justify-end space-x-4 mt-6">
                                    <button id="modalCancelBtn" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 font-semibold">${this._sanitizeHTML(cancelText)}</button>
                                    <button id="modalConfirmBtn" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold">${this._sanitizeHTML(confirmText)}</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                
                // Get all necessary DOM elements
                const modalEl = document.getElementById('genericInputModal');
                const modalContent = modalEl.querySelector('.transform');
                const confirmBtn = document.getElementById('modalConfirmBtn');
                const cancelBtn = document.getElementById('modalCancelBtn');
                const inputEl = document.getElementById('modalTextInput');
                const resultsListEl = document.getElementById('locationResultsList');

                // Debounce helper to prevent API calls on every keystroke
                let debounceTimeout;
                const debounce = (func, delay) => {
                    return (...args) => {
                        clearTimeout(debounceTimeout);
                        debounceTimeout = setTimeout(() => func.apply(this, args), delay);
                    };
                };

                const closeModal = (resolutionValue) => {
                    modalEl.classList.add('opacity-0');
                    modalContent.classList.add('scale-95');
                    // Remove the keydown listener to prevent memory leaks
                    document.removeEventListener('keydown', keydownHandler);
                    setTimeout(() => {
                        modalEl.remove();
                        resolve(resolutionValue);
                    }, 300);
                };

                // Live Location Search Logic
                const performLocationSearch = async (searchText) => {
                    if (searchText.length < 2) {
                        resultsListEl.classList.add('hidden');
                        return;
                    }
                    
                    resultsListEl.classList.remove('hidden');
                    resultsListEl.innerHTML = '<li class="p-2 text-sm text-gray-500">Searching...</li>';

                    try {
                        const data = await this._fetchJsonApi('/location/search', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: searchText })
                        });

                        resultsListEl.innerHTML = ''; 

                        if (data.locations && data.locations.length > 0) {
                            data.locations.forEach(location => {
                                const li = document.createElement('li');
                                li.className = 'px-3 py-2 text-sm cursor-pointer text-gray-900 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600';
                                li.textContent = location.name;
                                
                                li.addEventListener('click', () => {
                                    inputEl.value = location.name;
                                    resultsListEl.classList.add('hidden');
                                    inputEl.focus(); // Keep focus on the input after selection
                                });
                                resultsListEl.appendChild(li);
                            });
                        } else {
                            resultsListEl.innerHTML = '<li class="p-2 text-sm text-gray-500">No results found.</li>';
                        }

                    } catch (error) {
                        resultsListEl.innerHTML = '<li class="p-2 text-sm text-red-500">Error fetching locations.</li>';
                        console.error(error);
                    }
                };
                
                // Event Listeners
                const confirmAction = () => closeModal(inputEl.value);

                inputEl.addEventListener('input', debounce((e) => {
                    performLocationSearch(e.target.value);
                }, 300)); 

                confirmBtn.addEventListener('click', confirmAction);
                cancelBtn.addEventListener('click', () => closeModal(null));
                modalEl.addEventListener('click', (e) => {
                    if (!modalContent.contains(e.target)) {
                        closeModal(null);
                    }
                });

                const keydownHandler = (e) => {
                    if (e.key === 'Escape') closeModal(null);
                    if (e.key === 'Enter' && e.target === inputEl) {
                        e.preventDefault();
                        confirmAction();
                    }
                };
                document.addEventListener('keydown', keydownHandler);
                
                requestAnimationFrame(() => {
                    modalEl.classList.remove('opacity-0');
                    modalContent.classList.remove('scale-95');
                    inputEl.focus();
                });
            });
        }

        /**
         * Removes the location data from the current post and hides the display.
         * THIS FUNCTION HAS BEEN FIXED.
         */
        _handleRemoveLocation() {
            this.currentPostLocation = null;
            if (this.postLocationDisplay) {
                this.postLocationDisplay.classList.add('hidden');
                this.postLocationDisplay.classList.remove('flex');
            }
            if (this.locationNameText) {
                this.locationNameText.textContent = '';
            }
        }
        
        _handleAskSai() {
            // Placeholder for Ask Sai functionality
            const prompt = this.postContentTextarea?.value.trim();
            if(!prompt) {
                this._showNotificationModal('Prompt Required', 'Please enter a topic for Sai to write about.', 'info');
                return;
            }
            this._showNotificationModal('AI Feature', 'The "Ask Sai" feature is not yet implemented.', 'info');
        }

        // --- FEATURE: Friends Card and Actions ---

        _initFriendCard() {
            if (!this.friendGridContainerEl || !this.profileUserData?.id) return;

            // Fetch friends from our updated API endpoint
            this._fetchJsonApi(`/profile/${this.profileUserData.id}/friends`)
                .then(data => {
                    this.friendGridContainerEl.innerHTML = ''; 
                    if (data.friends.length === 0) {
                        this.friendGridContainerEl.innerHTML = '<p class="col-span-3 text-gray-500 dark:text-gray-400 text-sm">No friends to show.</p>';
                    } else {
                        data.friends.forEach(friend => {
                            const friendLink = document.createElement('a');
                            friendLink.href = `/profile/${friend.id}`;
                            friendLink.className = 'friend-item group';

                            // --- THE FIX ---
                            // No fallback needed here anymore. `friend.profile_picture` will always have a valid src.
                            friendLink.innerHTML = `
                                <img src="${this._sanitizeHTML(friend.profile_picture)}" alt="${this._sanitizeHTML(friend.full_name)}" class="rounded-md w-full h-full object-cover">
                                <div class="absolute bottom-0 left-0 right-0 p-1 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all text-white text-center">
                                    <p class="text-xs truncate font-semibold">${this._sanitizeHTML(friend.full_name)}</p>
                                </div>`;
                            this.friendGridContainerEl.appendChild(friendLink);
                        });
                    }
                })
                .catch(error => {
                    // ... error handling ...
                });
        }

        async _handleAddFriend() {
            const button = this.addFriendBtn;
            if (!button) return;
            const targetUserId = button.dataset.userId;
            const icon = button.querySelector('i');
            const text = button.querySelector('span');

            button.disabled = true;
            icon.className = 'fas fa-spinner fa-spin mr-2';
            text.textContent = 'Sending...';

            try {
                // *** THIS IS THE FIX: Call the new API endpoint ***
                await this._fetchJsonApi(`/friends/requesting/${targetUserId}`, { method: 'POST' });
                
                // If the fetch succeeds without throwing, update the UI
                button.className = 'bg-gray-200 dark:bg-dark-600 text-black dark:text-white px-4 py-2 rounded-md font-semibold flex items-center justify-center';
                icon.className = 'fas fa-check mr-2';
                text.textContent = 'Request Sent';

            } catch (error) {
                // _fetchJsonApi correctly parses the JSON error from our new API method
                this._showNotificationModal('Error', error.message, 'error');
                
                // Revert button to its original state on failure
                button.disabled = false;
                button.className = 'bg-facebook hover:bg-facebook-dark text-white px-4 py-2 rounded-md font-semibold flex items-center justify-center transition-colors duration-200';
                icon.className = 'fas fa-user-plus mr-2';
                text.textContent = 'Add Friend';
            }
        }
        
        // --- UI UTILITIES: Modals, Messages, Helpers ---
        
        _showNotificationModal(title, message, type = 'info') {
            const existingModal = document.getElementById('genericNotificationModal');
            if (existingModal) existingModal.remove();
            
            const themes = {
                success: { icon: 'fa-check-circle', color: 'text-green-500' },
                error: { icon: 'fa-exclamation-triangle', color: 'text-red-500' },
                info: { icon: 'fa-info-circle', color: 'text-blue-500' }
            };
            const theme = themes[type] || themes.info;

            const modalEl = document.createElement('div');
            modalEl.id = 'genericNotificationModal';
            modalEl.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0';
            modalEl.setAttribute('role', 'alertdialog');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.innerHTML = `
                <div class="bg-white dark:bg-dark-700 rounded-lg shadow-xl w-full max-w-sm m-4 transform transition-transform duration-300 scale-95">
                    <div class="p-6 text-center">
                        <div class="text-4xl ${theme.color} mx-auto mb-4"><i class="fas ${theme.icon}"></i></div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">${this._sanitizeHTML(title)}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">${this._sanitizeHTML(message)}</p>
                        <button id="modalCloseBtn" class="w-full bg-facebook hover:bg-facebook-dark text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-facebook-dark">OK</button>
                    </div>
                </div>`;
            document.body.appendChild(modalEl);

            const modalContent = modalEl.querySelector('.transform');
            const closeModal = () => {
                modalEl.classList.add('opacity-0');
                modalContent.classList.add('scale-95');
                setTimeout(() => modalEl.remove(), 300);
            };
            
            modalEl.querySelector('#modalCloseBtn').addEventListener('click', closeModal);
            modalEl.addEventListener('click', e => e.target === modalEl && closeModal());
            document.addEventListener('keydown', e => e.key === 'Escape' && closeModal(), { once: true });
            
            requestAnimationFrame(() => {
                modalEl.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
            });
        }

        _showTemporaryMessage(message, type = 'success', anchorElement) {
            // A simple, non-blocking feedback mechanism, good for successful actions.
            const msgDiv = document.createElement('div');
            // ... (implementation for a small toast/snackbar) ...
        }

        _pm_generateFallbackAvatar(name, size = 32) {
            // ... (your existing avatar generation code) ...
            let initial = String(name || 'U').charAt(0).toUpperCase();
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="#cccccc"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-size="50" fill="#ffffff">${this._sanitizeHTML(initial)}</text></svg>`;
            return 'data:image/svg+xml;charset=utf-8;base64,' + btoa(unescape(encodeURIComponent(svg)));
        }

        _sanitizeHTML(str) {
            const temp = document.createElement('div');
            temp.textContent = String(str || '');
            return temp.innerHTML;
        }

        async _handleUnfriend() {
            const button = this.unfriendBtn;
            if (!button) return;

            const targetUserId = button.dataset.userId;
            const targetUserName = this.profileUserData?.full_name || 'this user';

            const confirmed = await this._showConfirmationModal(
                `Unfriending ${this._sanitizeHTML(targetUserName)}`,
                `Are you sure you want to remove this person from your friends? This action cannot be undone.`
            );

            if (!confirmed) return;

            const originalButtonText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                // CORRECTED API ENDPOINT
                await this._fetchJsonApi(`/friends/unfriending/${targetUserId}`, { method: 'POST' });

                this._showNotificationModal('Success', `${this._sanitizeHTML(targetUserName)} has been unfriended. The page will now reload.`, 'success');
                setTimeout(() => window.location.reload(), 2000);

            } catch (error) {
                this._showNotificationModal('Error', error.message, 'error');
                button.disabled = false;
                button.innerHTML = originalButtonText;
            }
        }

        // ===============================================
        // START: REVISED CONFIRMATION MODAL (WITH ICONS & THEMES)
        // ===================================================================
        _showConfirmationModal(title, message, options = {}) {
            const {
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                type = 'warning' // 'warning' (red), 'info' (blue)
            } = options;

            const themes = {
                warning: {
                    icon: 'fa-exclamation-triangle',
                    iconColor: 'text-red-500',
                    buttonClass: 'bg-red-500 hover:bg-red-600'
                },
                info: {
                    icon: 'fa-info-circle',
                    iconColor: 'text-blue-500',
                    buttonClass: 'bg-facebook hover:bg-facebook-dark'
                }
            };
            const theme = themes[type] || themes.info;

            return new Promise((resolve) => {
                const existingModal = document.getElementById('genericConfirmationModal');
                if (existingModal) existingModal.remove();

                const modalHTML = `
                    <div id="genericConfirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0" role="alertdialog" aria-modal="true">
                        <div class="bg-white dark:bg-dark-700 rounded-lg shadow-xl w-full max-w-sm m-4 transform transition-transform duration-300 scale-95">
                            <div class="p-6 text-center">
                                <div class="text-4xl ${theme.iconColor} mx-auto mb-4">
                                    <i class="fas ${theme.icon}"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">${this._sanitizeHTML(title)}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">${this._sanitizeHTML(message)}</p>
                                <div class="flex justify-center space-x-4">
                                    <button id="modalCancelBtn" class="px-6 py-2 rounded-lg bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 font-semibold">${this._sanitizeHTML(cancelText)}</button>
                                    <button id="modalConfirmBtn" class="px-6 py-2 rounded-lg ${theme.buttonClass} text-white font-semibold">${this._sanitizeHTML(confirmText)}</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                
                const modalEl = document.getElementById('genericConfirmationModal');
                const modalContent = modalEl.querySelector('.transform');
                const confirmBtn = document.getElementById('modalConfirmBtn');
                const cancelBtn = document.getElementById('modalCancelBtn');

                const closeModal = (resolution) => {
                    modalEl.classList.add('opacity-0');
                    modalContent.classList.add('scale-95');
                    setTimeout(() => {
                        modalEl.remove();
                        resolve(resolution);
                    }, 300);
                };

                confirmBtn.addEventListener('click', () => closeModal(true));
                cancelBtn.addEventListener('click', () => closeModal(false));
                modalEl.addEventListener('click', (e) => (e.target === modalEl) && closeModal(false));
                document.addEventListener('keydown', (e) => (e.key === 'Escape') && closeModal(false), { once: true });

                requestAnimationFrame(() => {
                    modalEl.classList.remove('opacity-0');
                    modalContent.classList.remove('scale-95');
                });
            });
        }

        /**
         * Fetches and displays the latest 9 photos for the user's profile.
         */
        _initPhotosCard() {
            if (!this.photoGridContainerEl || !this.profileUserData?.id) return;

            this._fetchJsonApi(`/profile/${this.profileUserData.id}/photos`)
                .then(data => {
                    this.photoGridContainerEl.innerHTML = ''; 

                    if (data.photos && data.photos.length > 0) {
                        // --- THIS IS THE FIX ---
                        data.photos.forEach(photoObject => { // Renamed for clarity
                            // Get the URL string from the 'image' property of the object
                            const photoUrl = photoObject.image; 

                            const photoDiv = document.createElement('div');
                            photoDiv.className = 'aspect-w-1 aspect-h-1';
                            
                            // Sanitize the URL string before use
                            const sanitizedUrl = this._sanitizeHTML(photoUrl);

                            photoDiv.innerHTML = `
                                <img src="${sanitizedUrl}" 
                                     alt="User Photo" 
                                     class="w-full h-full object-cover rounded" 
                                     loading="lazy">
                            `;
                            this.photoGridContainerEl.appendChild(photoDiv);
                        });
                        // --- END OF FIX ---
                    } else {
                        this.photoGridContainerEl.innerHTML = `
                            <p class="col-span-3 text-sm text-gray-500 dark:text-gray-400">No photos to show.</p>
                        `;
                    }
                })
                .catch(error => {
                    console.error("Failed to load profile photos:", error);
                    this.photoGridContainerEl.innerHTML = `
                        <p class="col-span-3 text-sm text-red-500">Could not load photos.</p>
                    `;
                });
        }

        // Add this new method inside your ProfileApp class in profile.js
        _handleSharePost(buttonElement) {
            const postIdToShare = buttonElement.dataset.postId;
            if (!postIdToShare) {
                this._showNotificationModal('Error', 'Cannot share: Post ID is missing.', 'error');
                return;
            }

            // This assumes SmartFed is globally available
            if (window.SmartFed && typeof window.SmartFed.openPostModal === 'function') {
                window.SmartFed.openPostModal({ isSharing: true, originalPostId: postIdToShare });
            } else {
                this._showNotificationModal('Error', 'Could not open the post creation form.', 'error');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // window.ProfileAppInstance = new ProfileApp();

        if (typeof IS_PROFILE_PAGE !== 'undefined' && IS_PROFILE_PAGE) {
            window.ProfileAppInstance = new ProfileApp();
        }
    });

})(window, document);