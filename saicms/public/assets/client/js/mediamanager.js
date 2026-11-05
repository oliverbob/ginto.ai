// =========== MediaUploader Class Definition ===========
class MediaUploader {
    constructor(triggerButtonId, feedContainerId = 'postsContainer') {
        console.log("MediaUploader: Constructor. Trigger:", triggerButtonId, "Feed (info only):", feedContainerId);
        this.triggerButton = document.getElementById(triggerButtonId);

        this.currentUserId = window.APP_USER_ID || null;
        this.sessionUserFullName = window.APP_USER_FULL_NAME || 'User';
        this.sessionUserAvatar = window.APP_USER_AVATAR || this._generateFallbackAvatarSVG(this.sessionUserFullName, 32);

        this.activeFormModalId = null;
        this.activeGenericModalId = null;
        this.previewObjectUrls = [];
        this.fileInput = null;

        if (!this.triggerButton) {
            console.warn(`MediaUploader: Trigger button '${triggerButtonId}' not found. Uploader will not initialize.`);
            return;
        }
        this._bindTrigger();
    }

    _bindTrigger() {
        if (!this.triggerButton) {
            console.error("MediaUploader: _bindTrigger - this.triggerButton is null. Cannot bind click listener.");
            return;
        }
        this.triggerButton.addEventListener('click', (e) => {
            console.log("MediaUploader: 'Photo/Video' TRIGGER BUTTON CLICKED!");
            e.preventDefault();
            const currentDynamicUserId = window.APP_USER_ID || this.currentUserId;
            if (!currentDynamicUserId) {
                this._muShowGenericModal('Login Required', 'Please log in to create a post.', 'warning');
                return;
            }
            this.currentUserId = currentDynamicUserId;
            this.sessionUserFullName = window.APP_USER_FULL_NAME || 'User';
            this.sessionUserAvatar = window.APP_USER_AVATAR || this._generateFallbackAvatarSVG(this.sessionUserFullName, 32);
            
            this._showUploadFormModal();
        });
        console.log(`MediaUploader: Click event listener ADDED to '${this.triggerButton.id || "trigger button"}' trigger button.`);
    }
    
    _showUploadFormModal() {
        if (this.activeFormModalId) this._removeFormModal();
        this._cleanupPreviewObjectUrls();
        const modalId = 'mu-fb-style-modal-' + Date.now();
        this.activeFormModalId = modalId;
        const overlay = document.createElement('div');
        overlay.id = modalId;
        overlay.className = 'fixed inset-0 bg-gray-800 bg-opacity-75 flex justify-center items-center p-4 z-[1050] mu-overlay opacity-0 transition-opacity duration-300';
        overlay.style.alignItems = 'flex-start'; 
        overlay.style.paddingTop = '5vh'; 
        const modalContent = document.createElement('div');
        modalContent.className = 'bg-white dark:bg-dark-800 rounded-lg shadow-xl w-full transform scale-95 transition-all duration-300';
        modalContent.style.maxWidth = '500px';
        modalContent.style.margin = '0 auto';
        const header = document.createElement('div');
        header.className = 'relative px-4 py-3.5 border-b border-gray-200 dark:border-dark-700 flex justify-center items-center';
        const titleElement = document.createElement('h3');
        titleElement.className = 'text-xl font-bold text-gray-800 dark:text-gray-100';
        titleElement.textContent = 'Create post';
        const closeButton = document.createElement('button');
        closeButton.className = 'absolute top-1/2 right-3 transform -translate-y-1/2 bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 rounded-full p-2 focus:outline-none';
        closeButton.innerHTML = '<svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';
        closeButton.onclick = () => this._animateAndRemoveFormModal();
        header.appendChild(titleElement);
        header.appendChild(closeButton);
        const body = document.createElement('div');
        body.className = 'p-4 space-y-4';
        body.style.maxHeight = 'calc(90vh - 150px)'; 
        body.style.overflowY = 'auto';
        const actions = document.createElement('div');
        actions.className = 'px-4 py-3 border-t border-gray-200 dark:border-dark-700';
        modalContent.appendChild(header);
        modalContent.appendChild(body);
        modalContent.appendChild(actions);
        overlay.appendChild(modalContent);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
        }, 10);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this._animateAndRemoveFormModal();
        });
        this._populateUploadForm(body, actions);
    }

    _populateUploadForm(bodyContainer, actionsContainer) {
        bodyContainer.innerHTML = ''; 
        const userInfoDiv = document.createElement('div');
        userInfoDiv.className = 'flex items-center space-x-3';
        const userAvatarImg = document.createElement('img');
        userAvatarImg.src = this.sessionUserAvatar || this._generateFallbackAvatarSVG(this.sessionUserFullName, 40);
        userAvatarImg.alt = this.sessionUserFullName;
        userAvatarImg.className = 'w-10 h-10 rounded-full object-cover';
        const userNameAndVisibilityDiv = document.createElement('div');
        const userNameP = document.createElement('p');
        userNameP.className = 'font-semibold text-gray-800 dark:text-gray-100';
        userNameP.textContent = this.sessionUserFullName;
        
        const visibilitySelect = document.createElement('select');
        visibilitySelect.id = 'post-media-visibility';
        visibilitySelect.className = 'text-xs bg-gray-200 dark:bg-dark-600 text-gray-700 dark:text-gray-200 rounded focus:outline-none appearance-none';
        visibilitySelect.innerHTML = `<option value="public">Public</option><option value="friends">Friends</option><option value="private">Only Me</option>`;
        visibilitySelect.style.paddingTop = '0.125rem'; visibilitySelect.style.paddingBottom = '0.125rem';
        visibilitySelect.style.paddingLeft = '0.5rem';  visibilitySelect.style.paddingRight = '1.75rem';
        visibilitySelect.style.backgroundImage = `url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e")`;
        visibilitySelect.style.backgroundPosition = 'right 0.5rem center'; visibilitySelect.style.backgroundRepeat = 'no-repeat';
        visibilitySelect.style.backgroundSize = '1em 1em';
        if (document.documentElement.classList.contains('dark')) {
            visibilitySelect.style.backgroundImage = `url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e")`;
        }

        userNameAndVisibilityDiv.appendChild(userNameP);
        userNameAndVisibilityDiv.appendChild(visibilitySelect);
        userInfoDiv.appendChild(userAvatarImg);
        userInfoDiv.appendChild(userNameAndVisibilityDiv);
        bodyContainer.appendChild(userInfoDiv);

        const captionTextarea = document.createElement('textarea');
        captionTextarea.id = 'post-media-caption';
        captionTextarea.rows = 3;
        captionTextarea.className = 'w-full p-0 text-lg dark:text-gray-200 bg-transparent border-none focus:outline-none resize-none placeholder-gray-500 dark:placeholder-gray-400';
        captionTextarea.placeholder = `What's on your mind, ${this.sessionUserFullName.split(' ')[0]}?`;
        captionTextarea.style.minHeight = '80px';
        captionTextarea.style.setProperty('--tw-ring-offset-shadow', '0 0 #0000');
        captionTextarea.style.setProperty('--tw-ring-shadow', '0 0 #0000');
        captionTextarea.style.boxShadow = 'var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow, 0 0 #0000)';
        bodyContainer.appendChild(captionTextarea);

        const mediaDropZoneAndPreviewArea = document.createElement('div');
        mediaDropZoneAndPreviewArea.id = 'media-dropzone-preview';
        mediaDropZoneAndPreviewArea.className = 'mt-3 p-4 border-2 border-dashed border-gray-300 dark:border-dark-600 rounded-lg text-center cursor-pointer hover:border-blue-500 dark:hover:border-blue-400';
        mediaDropZoneAndPreviewArea.style.minHeight = '200px'; mediaDropZoneAndPreviewArea.style.display = 'flex';
        mediaDropZoneAndPreviewArea.style.flexDirection = 'column'; mediaDropZoneAndPreviewArea.style.justifyContent = 'center';
        mediaDropZoneAndPreviewArea.style.alignItems = 'center'; mediaDropZoneAndPreviewArea.style.position = 'relative';

        this.fileInput = document.createElement('input');
        this.fileInput.type = 'file'; this.fileInput.id = 'post-media-file-hidden'; 
        this.fileInput.accept = 'image/*,video/*'; this.fileInput.style.display = 'none';
        bodyContainer.appendChild(this.fileInput);

        const initialMediaContent = document.createElement('div');
        initialMediaContent.id = 'initial-media-content';
        initialMediaContent.className = 'flex flex-col items-center justify-center space-y-2 text-gray-500 dark:text-gray-400';
        initialMediaContent.innerHTML = `<div class="bg-gray-200 dark:bg-dark-500 p-3 rounded-full"><svg class="w-8 h-8 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M13 8V0L8.11 5.87A4.002 4.002 0 004 8H0v12h20V8h-7zm3 10H4V9.98A2.002 2.002 0 015.89 8H14v10zm-3-8a2 2 0 100-4 2 2 0 000 4z"/></svg></div><p class="font-semibold">Add photos/videos</p><p class="text-xs">or drag and drop</p>`;
        mediaDropZoneAndPreviewArea.appendChild(initialMediaContent);

        const imagePreview = document.createElement('img'); imagePreview.id = 'post-media-preview-fb';
        imagePreview.className = 'hidden object-contain rounded-md'; imagePreview.style.maxHeight = '300px'; imagePreview.style.maxWidth = '100%';
        mediaDropZoneAndPreviewArea.appendChild(imagePreview);

        const videoPreview = document.createElement('video'); videoPreview.id = 'post-media-video-preview-fb';
        videoPreview.className = 'hidden object-contain rounded-md'; videoPreview.style.maxHeight = '300px'; videoPreview.style.maxWidth = '100%';
        videoPreview.controls = true; videoPreview.preload = 'metadata';
        mediaDropZoneAndPreviewArea.appendChild(videoPreview);
        
        const clearMediaButton = document.createElement('button');
        clearMediaButton.innerHTML = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';
        clearMediaButton.className = 'absolute top-2 right-2 bg-gray-700 bg-opacity-50 text-white rounded-full p-1.5 hover:bg-opacity-75 hidden focus:outline-none';
        clearMediaButton.onclick = (e) => {
            e.stopPropagation(); if (this.fileInput) this.fileInput.value = '';
            imagePreview.classList.add('hidden'); videoPreview.classList.add('hidden'); imagePreview.src = '#'; videoPreview.src = '#';
            initialMediaContent.style.display = 'flex'; clearMediaButton.classList.add('hidden');
            mediaDropZoneAndPreviewArea.className = 'mt-3 p-4 border-2 border-dashed border-gray-300 dark:border-dark-600 rounded-lg text-center cursor-pointer hover:border-blue-500 dark:hover:border-blue-400';
            this._cleanupPreviewObjectUrls(); updatePostButtonState();
        };
        mediaDropZoneAndPreviewArea.appendChild(clearMediaButton);
        mediaDropZoneAndPreviewArea.onclick = () => { if (this.fileInput) this.fileInput.click(); };
        bodyContainer.appendChild(mediaDropZoneAndPreviewArea);

        this.fileInput.addEventListener('change', (event) => {
            this._cleanupPreviewObjectUrls(); imagePreview.classList.add('hidden'); videoPreview.classList.add('hidden');
            imagePreview.src = '#'; videoPreview.src = '#';
            const file = event.target.files[0];
            if (file) {
                initialMediaContent.style.display = 'none'; clearMediaButton.classList.remove('hidden');
                mediaDropZoneAndPreviewArea.className = 'mt-3 p-1 border border-gray-300 dark:border-dark-600 rounded-lg text-center';
                mediaDropZoneAndPreviewArea.style.cursor = 'default';
                const objectURL = URL.createObjectURL(file); this._addPreviewObjectUrl(objectURL);
                if (file.type.startsWith('image/')) { imagePreview.src = objectURL; imagePreview.classList.remove('hidden'); }
                else if (file.type.startsWith('video/')) { videoPreview.src = objectURL; videoPreview.classList.remove('hidden'); }
            } else {
                initialMediaContent.style.display = 'flex'; clearMediaButton.classList.add('hidden');
                mediaDropZoneAndPreviewArea.className = 'mt-3 p-4 border-2 border-dashed border-gray-300 dark:border-dark-600 rounded-lg text-center cursor-pointer hover:border-blue-500 dark:hover:border-blue-400';
                mediaDropZoneAndPreviewArea.style.cursor = 'pointer';
            }
            updatePostButtonState();
        });
        
        const addToPostBar = document.createElement('div');
        addToPostBar.className = 'mt-3 px-3 py-2.5 border border-gray-300 dark:border-dark-600 rounded-lg flex justify-between items-center';
        const addToPostText = document.createElement('span');
        addToPostText.className = 'font-semibold text-gray-700 dark:text-gray-200 text-sm'; addToPostText.textContent = 'Add to your post';
        const addToPostIcons = document.createElement('div'); addToPostIcons.className = 'flex space-x-2.5';
        const iconsData = [ { label: '🖼️', title: 'Photo/Video' }, { label: '👥', title: 'Tag Friends' }, { label: '😊', title: 'Feeling/Activity' }, { label: '📍', title: 'Check In' }, { label: '...', title: 'More' }];
        iconsData.forEach(iconData => {
            const iconButton = document.createElement('button');
            iconButton.className = 'text-xl hover:bg-gray-200 dark:hover:bg-dark-500 p-1 rounded-full focus:outline-none';
            iconButton.title = iconData.title; iconButton.textContent = iconData.label; 
            if (iconData.title === 'Photo/Video') { iconButton.onclick = (e) => { e.preventDefault(); if(this.fileInput) this.fileInput.click(); }; }
            addToPostIcons.appendChild(iconButton);
        });
        addToPostBar.appendChild(addToPostText); addToPostBar.appendChild(addToPostIcons); bodyContainer.appendChild(addToPostBar);

        actionsContainer.innerHTML = ''; const postButton = document.createElement('button');
        postButton.textContent = 'Post';
        postButton.className = 'w-full px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-dark-800 disabled:opacity-50 disabled:cursor-not-allowed';
        postButton.id = 'mu-submit-fb-style-post-btn'; postButton.disabled = true;

        const updatePostButtonState = () => {
            const hasCaption = captionTextarea.value.trim().length > 0;
            const hasFile = this.fileInput && this.fileInput.files && this.fileInput.files.length > 0;
            postButton.disabled = !(hasCaption || hasFile);
        };
        captionTextarea.addEventListener('input', updatePostButtonState); updatePostButtonState();

        postButton.onclick = async (e) => {
            e.stopPropagation();
            const currentCaption = captionTextarea.value.trim(); const currentVisibility = visibilitySelect.value;
            const file = this.fileInput.files[0];
            if (!currentCaption && !file) { this._muShowGenericModal('Nothing to post', 'Please add a caption or select a media file to post.', 'warning'); return; }
            if (file && file.size > (50 * 1024 * 1024)) { this._muShowGenericModal('File Too Large', `The selected file exceeds the 50MB size limit. Please choose a smaller file.`, 'warning'); return; }
            postButton.disabled = true; const originalPostButtonText = postButton.textContent;
            postButton.innerHTML = '<span class="loading-spinner-white-xs inline-block mr-1.5 align-middle"></span>Preparing...';
            if (window.globalPostFeedManager && typeof window.globalPostFeedManager.handleNewPostSubmissionFromUploader === 'function') {
                try {
                    await window.globalPostFeedManager.handleNewPostSubmissionFromUploader({ caption: currentCaption, visibility: currentVisibility, file: file });
                    this._animateAndRemoveFormModal(); 
                } catch (pfmError) {
                    console.error("MediaUploader: Error during handoff to or processing by PostFeedManager:", pfmError);
                    postButton.disabled = false; postButton.innerHTML = originalPostButtonText; updatePostButtonState();
                }
            } else {
                this._muShowGenericModal('System Error', 'Cannot submit post. The feed processing component is not available.', 'error');
                console.error("MediaUploader: CRITICAL - globalPostFeedManager or its handleNewPostSubmissionFromUploader method is not found.");
                postButton.disabled = false; postButton.innerHTML = originalPostButtonText; updatePostButtonState();
            }
        };
        actionsContainer.appendChild(postButton);
    }
    
    _animateAndRemoveFormModal() {
        const modal = document.getElementById(this.activeFormModalId);
        if (modal) {
            const content = modal.querySelector('.bg-white, .dark\\:bg-dark-800');
            modal.classList.add('opacity-0'); if (content) content.classList.add('scale-95');
            setTimeout(() => this._removeFormModal(), 300);
        }
    }
    _removeFormModal() {
        if (this.activeFormModalId) {
            const modal = document.getElementById(this.activeFormModalId);
            if (modal) modal.remove(); this._cleanupPreviewObjectUrls();
            if (this.fileInput) this.fileInput.value = ''; this.activeFormModalId = null; this._checkAndRestoreScroll();
        }
    }
    _checkAndRestoreScroll() { // Updated to include LSU modals
        const pfmModalActive = document.querySelector('.pfm-modal-overlay');
        const muGenericModalActive = document.querySelector('.mu-generic-modal-overlay');
        const lsuFormModalActive = document.querySelector('.lsu-overlay');
        const lsuGenericModalActive = document.querySelector('.lsu-generic-modal-overlay');
        if (!this.activeFormModalId && !this.activeGenericModalId && 
            !pfmModalActive && !muGenericModalActive && !lsuFormModalActive && !lsuGenericModalActive) {
            document.body.style.overflow = '';
        }
    }
    _cleanupPreviewObjectUrls() { this.previewObjectUrls.forEach(url => { if (url && url.startsWith('blob:')) URL.revokeObjectURL(url); }); this.previewObjectUrls = []; }
    _addPreviewObjectUrl(url) { if (url && url.startsWith('blob:')) this.previewObjectUrls.push(url); }
    _sanitizeHTML(str, allowBreaks = false) { if (typeof str !== 'string') str = String(str || ''); const temp = document.createElement('div'); temp.textContent = str; let s = temp.innerHTML; if (allowBreaks) s = s.replace(/\n/g, '<br>'); return s; }
    _generateFallbackAvatarSVG(name, size = 32) { const i = name ? name.trim().toUpperCase().charAt(0) : '?'; const c = ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#34495e']; const cc = i.charCodeAt(0) || 0; const bc = c[cc % c.length]; const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${bc}"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="50" fill="white" font-weight="bold">${i}</text></svg>`; return "data:image/svg+xml;base64," + btoa(svg); }
    _muShowGenericModal(title, message, type = 'info') {
        if (this.activeGenericModalId) { const e = document.getElementById(this.activeGenericModalId); if (e) e.remove(); }
        const genericModalId = 'mu-generic-modal-active-' + Date.now(); this.activeGenericModalId = genericModalId; 
        const overlay = document.createElement('div'); overlay.id = this.activeGenericModalId;
        overlay.className = 'mu-generic-modal-overlay fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-[1060] p-4 opacity-0 transition-opacity duration-300';
        let tc = 'text-gray-900 dark:text-white', iconHTML = '';
        switch (type) {
            case 'success': tc = 'text-green-600 dark:text-green-400'; iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 mb-3"><svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></div>'; break;
            case 'warning': tc = 'text-yellow-600 dark:text-yellow-400'; iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 mb-3"><svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>'; break;
            case 'error': tc = 'text-red-600 dark:text-red-400'; iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 mb-3"><svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>'; break;
            default: iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3"><svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>'; break;
        }
        const modalContentDiv = document.createElement('div'); modalContentDiv.className = 'bg-white dark:bg-dark-800 p-6 rounded-lg shadow-xl w-full max-w-sm text-center transform scale-95 transition-all duration-300';
        modalContentDiv.innerHTML = `${iconHTML}<h3 class="text-lg font-medium ${tc} mb-2">${this._sanitizeHTML(title)}</h3><p class="text-sm text-gray-600 dark:text-gray-300 mb-4">${this._sanitizeHTML(message, true)}</p><button id="mu-generic-modal-close-${genericModalId}" class="w-full px-4 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-dark-800">OK</button>`;
        overlay.appendChild(modalContentDiv); document.body.appendChild(overlay); document.body.style.overflow = 'hidden';
        setTimeout(() => { overlay.classList.remove('opacity-0'); modalContentDiv.classList.remove('scale-95'); }, 10);
        const cb = document.getElementById(`mu-generic-modal-close-${genericModalId}`);
        if (cb) { cb.focus(); cb.onclick = () => { overlay.classList.add('opacity-0'); modalContentDiv.classList.add('scale-95'); setTimeout(() => { overlay.remove(); this.activeGenericModalId = null; this._checkAndRestoreScroll(); }, 300); }; }
        if (type === 'success' || type === 'info') { setTimeout(() => { if (document.getElementById(genericModalId)) { cb?.click(); } }, 3000); }
    }
}

// (Facebook UI - MODAL AND DATA COLLECTION ONLY for Live Video)

class LiveStreamUploader {
    constructor(triggerButtonId) {
        console.log("LiveStreamUploader: Constructor. Trigger:", triggerButtonId);
        this.triggerButton = document.getElementById(triggerButtonId);

        this.currentUserId = window.APP_USER_ID || null;
        this.sessionUserFullName = window.APP_USER_FULL_NAME || 'User';
        this.sessionUserAvatar = window.APP_USER_AVATAR || this._generateFallbackAvatarSVG(this.sessionUserFullName, 32);

        this.activeFormModalId = null;
        this.activeGenericModalId = null;
        this.webcamVideoElement = null; // For local preview
        this.mediaStream = null;        // Local MediaStream
        this.peerConnection = null;     // WebRTC PeerConnection
        this.streamData = null;         // To store WHIP URL, playback UID, liveInputId
        this.cfPlayerContainer = null;  // Container for the Cloudflare player in modal
        this.getStreamDetailsUrl = '/post/stream?action=get_stream_details_for_view'; // Backend URL
        this.isStreamingToCF = false;

        this.playerInitialized = false; 
        this.playerTimeoutId = null;    

        if (!this.triggerButton) {
            console.warn(`LiveStreamUploader: Trigger button '${triggerButtonId}' not found. Uploader will not initialize.`);
            return;
        }
        this._bindTrigger();
    }

    _bindTrigger() {
        if (!this.triggerButton) {
            console.error("LiveStreamUploader: _bindTrigger - this.triggerButton is null.");
            return;
        }
        this.triggerButton.addEventListener('click', (e) => {
            console.log("LiveStreamUploader: 'Live Video' TRIGGER BUTTON CLICKED!");
            e.preventDefault();
            const currentDynamicUserId = window.APP_USER_ID || this.currentUserId;
            if (!currentDynamicUserId) {
                this._lsuShowGenericModal('Login Required', 'Please log in to start a live stream.', 'warning');
                return;
            }
            this.currentUserId = currentDynamicUserId;
            this.sessionUserFullName = window.APP_USER_FULL_NAME || 'User';
            this.sessionUserAvatar = window.APP_USER_AVATAR || this._generateFallbackAvatarSVG(this.sessionUserFullName, 32);
            
            this._showLiveStreamModal();
        });
        console.log(`LiveStreamUploader: Click event listener ADDED to '${this.triggerButton.id || "trigger button"}'.`);
    }
    
    _showLiveStreamModal() {
        if (this.activeFormModalId) this._removeFormModal();
        this._stopCloudflareStreamAndWebcam(); 

        const modalId = 'lsu-live-stream-modal-' + Date.now();
        this.activeFormModalId = modalId;

        const overlay = document.createElement('div');
        overlay.id = modalId;
        overlay.className = 'fixed inset-0 bg-gray-800 bg-opacity-75 flex justify-center items-center p-4 z-[1050] lsu-overlay opacity-0 transition-opacity duration-300';
        overlay.style.alignItems = 'flex-start'; 
        overlay.style.paddingTop = '5vh'; 
        
        const modalContent = document.createElement('div');
        modalContent.className = 'bg-white dark:bg-dark-800 rounded-lg shadow-xl w-full transform scale-95 transition-all duration-300';
        modalContent.style.maxWidth = '500px'; 
        modalContent.style.margin = '0 auto';

        const header = document.createElement('div');
        header.className = 'relative px-4 py-3.5 border-b border-gray-200 dark:border-dark-700 flex justify-center items-center';
        const titleElement = document.createElement('h3');
        titleElement.className = 'text-xl font-bold text-gray-800 dark:text-gray-100';
        titleElement.textContent = 'Create live video';
        
        const closeButton = document.createElement('button');
        closeButton.className = 'absolute top-1/2 right-3 transform -translate-y-1/2 bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 rounded-full p-2 focus:outline-none';
        closeButton.innerHTML = '<svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';
        closeButton.onclick = () => this._animateAndRemoveFormModal();
        
        header.appendChild(titleElement);
        header.appendChild(closeButton);

        const body = document.createElement('div');
        body.className = 'p-4 space-y-4';
        body.style.maxHeight = 'calc(90vh - 150px)'; 
        body.style.overflowY = 'auto';
        
        const actions = document.createElement('div');
        actions.className = 'px-4 py-3 border-t border-gray-200 dark:border-dark-700';

        modalContent.appendChild(header);
        modalContent.appendChild(body);
        modalContent.appendChild(actions);
        overlay.appendChild(modalContent);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
        }, 10);

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                console.log("LiveStreamUploader: Overlay clicked, modal remains open.");
            }
        });
        
        this._populateLiveStreamForm(body, actions);
    }

    _updateModalStreamStatus(message, type = 'info', container) {
        const statusEl = container || document.getElementById('lsu-stream-status-message');
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.className = 'text-xs mt-1'; 
            if (type === 'error') statusEl.classList.add('text-red-500', 'dark:text-red-400');
            else if (type === 'success') statusEl.classList.add('text-green-500', 'dark:text-green-400');
            else statusEl.classList.add('text-gray-500', 'dark:text-gray-400');
            statusEl.style.display = 'block';
        }
    }
    
    async _fetchStreamDetails() {
        try {
            this._updateModalStreamStatus('Fetching stream endpoint...', 'info');
            const response = await fetch(this.getStreamDetailsUrl);
            if (!response.ok) {
                let errorMsg = `Server error ${response.status}`;
                try { const errData = await response.json(); errorMsg = errData.error || errorMsg; } catch(e){}
                throw new Error(errorMsg);
            }
            this.streamData = await response.json();
            // MODIFICATION START
            if (!this.streamData.success || !this.streamData.whipUrl || !this.streamData.liveInputId) { // Removed apiTokenForWhip check
                throw new Error(this.streamData.error || 'Invalid stream details. WHIP URL or Live Input ID missing.'); // Updated error message
            }
            // MODIFICATION END
            this._updateModalStreamStatus('Stream endpoint acquired.', 'success');
            console.log("Stream Details Acquired:", this.streamData);
            return true;
        } catch (error) {
            console.error("LiveStreamUploader: Error fetching stream details:", error);
            this._updateModalStreamStatus(`Error fetching stream details: ${error.message}`, 'error');
            this._lsuShowGenericModal('Stream Setup Error', `Could not get stream details: ${error.message}`, 'error');
            return false;
        }
    }

    _populateLiveStreamForm(bodyContainer, actionsContainer) {
        bodyContainer.innerHTML = ''; 

        const userInfoDiv = document.createElement('div'); 
        userInfoDiv.className = 'flex items-center space-x-3';
        const userAvatarImg = document.createElement('img');
        userAvatarImg.src = this.sessionUserAvatar || this._generateFallbackAvatarSVG(this.sessionUserFullName, 40);
        userAvatarImg.alt = this.sessionUserFullName;
        userAvatarImg.className = 'w-10 h-10 rounded-full object-cover';
        const userNameAndVisibilityDiv = document.createElement('div');
        const userNameP = document.createElement('p');
        userNameP.className = 'font-semibold text-gray-800 dark:text-gray-100';
        userNameP.textContent = this.sessionUserFullName;
        const visibilitySelect = document.createElement('select');
        visibilitySelect.id = 'live-stream-visibility';
        visibilitySelect.className = 'text-xs bg-gray-200 dark:bg-dark-600 text-gray-700 dark:text-gray-200 rounded focus:outline-none appearance-none';
        visibilitySelect.innerHTML = `<option value="public">Public</option><option value="friends">Friends</option><option value="private">Only Me (Test Stream)</option>`;
        this._applySelectStyles(visibilitySelect); 
        userNameAndVisibilityDiv.appendChild(userNameP); userNameAndVisibilityDiv.appendChild(visibilitySelect);
        userInfoDiv.appendChild(userAvatarImg); userInfoDiv.appendChild(userNameAndVisibilityDiv);
        bodyContainer.appendChild(userInfoDiv);


        const streamDescriptionTextarea = document.createElement('textarea');
        streamDescriptionTextarea.id = 'live-stream-description';
        streamDescriptionTextarea.rows = 2; 
        streamDescriptionTextarea.className = 'w-full p-0 text-lg dark:text-gray-200 bg-transparent border-none focus:outline-none resize-none placeholder-gray-500 dark:placeholder-gray-400';
        streamDescriptionTextarea.placeholder = `What's this live video about, ${this.sessionUserFullName.split(' ')[0]}?`;
        streamDescriptionTextarea.style.minHeight = '60px';
        this._applyTextareaFocusStyles(streamDescriptionTextarea);
        bodyContainer.appendChild(streamDescriptionTextarea);

        const streamAreaContainer = document.createElement('div');
        streamAreaContainer.id = 'lsu-stream-area-container';
        streamAreaContainer.className = 'mt-3 p-2 border border-gray-300 dark:border-dark-600 rounded-lg text-center space-y-2';
        streamAreaContainer.style.minHeight = '280px'; 

            this.webcamVideoElement = document.createElement('video');
            this.webcamVideoElement.id = 'lsu-webcam-preview-modal';
            this.webcamVideoElement.className = 'w-full h-48 object-cover rounded-md bg-black'; 
            this.webcamVideoElement.autoplay = true; this.webcamVideoElement.muted = true; this.webcamVideoElement.playsInline = true;
            streamAreaContainer.appendChild(this.webcamVideoElement);
            
            this.cfPlayerContainer = document.createElement('div');
            this.cfPlayerContainer.id = 'lsu-cf-player-in-modal';
            this.cfPlayerContainer.className = 'w-full h-48 bg-gray-200 dark:bg-dark-700 rounded-md flex items-center justify-center text-gray-500 dark:text-gray-400 text-sm';
            this.cfPlayerContainer.innerHTML = '<span>Cloudflare Stream Preview (when live)</span>';
            this.cfPlayerContainer.style.display = 'none'; 
            streamAreaContainer.appendChild(this.cfPlayerContainer);

            const streamStatusMessage = document.createElement('p');
            streamStatusMessage.id = 'lsu-stream-status-message';
            streamStatusMessage.className = 'text-xs text-gray-500 dark:text-gray-400 mt-1';
            streamStatusMessage.textContent = 'Ready to start your webcam.';
            streamAreaContainer.appendChild(streamStatusMessage);
            
        bodyContainer.appendChild(streamAreaContainer);

        const addToPostBar = document.createElement('div'); 
        addToPostBar.className = 'mt-3 px-3 py-2.5 border border-gray-300 dark:border-dark-600 rounded-lg flex justify-between items-center';
        addToPostBar.innerHTML = `<span class="font-semibold text-gray-700 dark:text-gray-200 text-sm">Live Stream Options</span>
                                 <div class="flex space-x-2.5"> 
                                     <button title="Not Implemented" class="text-xl p-1 rounded-full focus:outline-none">⚙️</button>
                                 </div>`;
        bodyContainer.appendChild(addToPostBar);


        actionsContainer.innerHTML = '';
        const startWebcamAndFetchButton = document.createElement('button');
        startWebcamAndFetchButton.innerHTML = '🎙️ Start Camera & Prepare Stream';
        startWebcamAndFetchButton.id = 'lsu-start-webcam-prepare-btn';
        startWebcamAndFetchButton.className = 'w-full px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none';
        
        const goLiveActualButton = document.createElement('button');
        goLiveActualButton.textContent = '🚀 Go Live!';
        goLiveActualButton.id = 'lsu-go-live-actual-btn';
        goLiveActualButton.className = 'w-full px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';
        goLiveActualButton.disabled = true; 
        goLiveActualButton.style.display = 'none'; 

        startWebcamAndFetchButton.onclick = async () => {
            startWebcamAndFetchButton.disabled = true;
            startWebcamAndFetchButton.innerHTML = '<span class="loading-spinner-white-xs inline-block mr-1.5 align-middle"></span>Preparing...';
            this._updateModalStreamStatus('Starting webcam and fetching stream details...', 'info', streamStatusMessage);

            const streamDetailsFetched = await this._fetchStreamDetails();
            if (!streamDetailsFetched) {
                startWebcamAndFetchButton.disabled = false;
                startWebcamAndFetchButton.innerHTML = '🎙️ Start Camera & Prepare Stream';
                return;
            }

            try {
                this.mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: true });
                this.webcamVideoElement.srcObject = this.mediaStream;
                this._updateModalStreamStatus('Webcam active. Ready to go live.', 'success', streamStatusMessage);
                startWebcamAndFetchButton.style.display = 'none';
                goLiveActualButton.style.display = 'block';
                goLiveActualButton.disabled = false;
            } catch (err) {
                const friendlyError = this._getFriendlyWebcamError(err);
                this._updateModalStreamStatus(`Webcam error: ${friendlyError}`, 'error', streamStatusMessage);
                this._lsuShowGenericModal('Webcam Error', friendlyError, 'error');
                startWebcamAndFetchButton.disabled = false;
                startWebcamAndFetchButton.innerHTML = '🎙️ Start Camera & Prepare Stream';
                this._stopCloudflareStreamAndWebcam(); 
            }
        };

        goLiveActualButton.onclick = async () => {
            if (!this.mediaStream || !this.streamData) {
                this._lsuShowGenericModal('Setup Incomplete', 'Webcam or stream details are not ready. Please click "Start Camera & Prepare Stream" first.', 'warning');
                return;
            }
            goLiveActualButton.disabled = true;
            goLiveActualButton.innerHTML = '<span class="loading-spinner-white-xs inline-block mr-1.5 align-middle"></span>Preparing...';
            
            const streamDescription = document.getElementById('live-stream-description').value.trim();
            const streamVisibility = document.getElementById('live-stream-visibility').value;

            this._updateModalStreamStatus('Creating backend post...', 'info', streamStatusMessage);
            const postCreated = await this._createStreamPostOnBackend(streamDescription, streamVisibility);

            if (!postCreated) {
                goLiveActualButton.disabled = false;
                goLiveActualButton.innerHTML = '🚀 Go Live!';
                this._updateModalStreamStatus('Failed to create stream post. Cannot go live.', 'error', streamStatusMessage);
                return; 
            }
            
            goLiveActualButton.innerHTML = '<span class="loading-spinner-white-xs inline-block mr-1.5 align-middle"></span>Going Live...';
            this._updateModalStreamStatus('Connecting to Cloudflare Stream...', 'info', streamStatusMessage);

            try {
                await this._initiateWebRTCConnectionToCF();
            } catch (error) {
                this._updateModalStreamStatus(`Failed to go live: ${error.message}`, 'error', streamStatusMessage);
                this._lsuShowGenericModal('Streaming Error', `Could not connect to stream: ${error.message}`, 'error');
                goLiveActualButton.disabled = false;
                goLiveActualButton.innerHTML = '🚀 Go Live!';
                this._stopCloudflareStreamAndWebcam(true); 
            }
        };
        
        actionsContainer.appendChild(startWebcamAndFetchButton);
        actionsContainer.appendChild(goLiveActualButton);
    }

    async _initiateWebRTCConnectionToCF() {
        // MODIFICATION START
        if (!this.streamData || !this.streamData.whipUrl) { // Removed apiTokenForWhip check
            throw new Error("Missing WHIP URL from stream data."); // Updated error message
        }
        // MODIFICATION END
        if (!this.mediaStream) {
            throw new Error("Local media stream (webcam/mic) is not active.");
        }

        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }

        this.peerConnection = new RTCPeerConnection({
            iceServers: [ { urls: 'stun:stun.l.google.com:19302' } ] 
        });

        this.peerConnection.onicecandidate = event => {
            if (event.candidate) {
                console.log("LSU: ICE Candidate:", event.candidate.candidate);
            } else {
                console.log("LSU: All ICE candidates have been sent.");
            }
        };

        this.peerConnection.oniceconnectionstatechange = () => {
            const state = this.peerConnection ? this.peerConnection.iceConnectionState : 'closed';
            this._updateModalStreamStatus(`Stream Connection: ${state}`, 'info');
            console.log(`LSU: ICE Connection State: ${state}`);
            const goLiveBtn = document.getElementById('lsu-go-live-actual-btn');

            if (state === 'connected' || state === 'completed') {
                this.isStreamingToCF = true;
                this.playerInitialized = false; 
                this._updateModalStreamStatus('LIVE STREAMING to Cloudflare! Player will load shortly.', 'success');
                 if (goLiveBtn) {
                    goLiveBtn.innerHTML = '⏹️ Stop Streaming';
                    goLiveBtn.disabled = false;
                    goLiveBtn.className = 'w-full px-4 py-2 text-sm font-semibold text-white bg-gray-600 hover:bg-gray-700 rounded-md focus:outline-none';
                    goLiveBtn.onclick = () => { 
                        this._stopCloudflareStreamAndWebcam();
                    };
                }
                
                this.webcamVideoElement.style.display = 'none'; 
                this.cfPlayerContainer.style.display = 'flex'; 
                this.cfPlayerContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-full">
                        <span class="loading-spinner-white-xs" style="width: 2.5em; height: 2.5em; border-color: rgba(150,150,150,0.3); border-left-color: #888;"></span>
                        <span class="mt-3 text-sm text-gray-500 dark:text-gray-400">Player loading... stream may take a moment.</span>
                    </div>`; 
                
                if (this.playerTimeoutId) clearTimeout(this.playerTimeoutId);

                this.playerTimeoutId = setTimeout(() => {
                    if (this.isStreamingToCF && this.peerConnection && 
                        (this.peerConnection.iceConnectionState === 'connected' || this.peerConnection.iceConnectionState === 'completed') &&
                        !this.playerInitialized) {
                        this._showCloudflarePlayerInModal();
                        this.playerInitialized = true;
                    } else if (!this.isStreamingToCF) {
                        console.log("LSU: Stream was stopped before player could initialize.");
                         if (this.cfPlayerContainer) this.cfPlayerContainer.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">Stream ended.</span>';
                    }
                }, 5000); 

            } else if (['failed', 'disconnected', 'closed'].includes(state)) {
                this.isStreamingToCF = false;
                 if (this.playerTimeoutId) clearTimeout(this.playerTimeoutId); 
                this._updateModalStreamStatus(`Stream Disconnected: ${state}.`, 'error');
                 if (goLiveBtn && goLiveBtn.textContent.includes('Stop')) { 
                     this._stopCloudflareStreamAndWebcam(false); 
                }
                if (this.cfPlayerContainer) this.cfPlayerContainer.style.display = 'none';
                if (this.webcamVideoElement) this.webcamVideoElement.style.display = 'block';
                this.playerInitialized = false;
            }
        };
        
        this.mediaStream.getTracks().forEach(track => this.peerConnection.addTrack(track, this.mediaStream));

        const offer = await this.peerConnection.createOffer();
        await this.peerConnection.setLocalDescription(offer);
        this._updateModalStreamStatus('SDP Offer created. Sending to WHIP endpoint...', 'info');

        // MODIFICATION START
        const whipResponse = await fetch(this.streamData.whipUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/sdp' }, // Removed Authorization header
            body: this.peerConnection.localDescription.sdp
        });
        // MODIFICATION END

        if (!whipResponse.ok) {
            // Handle WHIP "204 No Content" with Location header for SDP answer (WHEP extension)
            // This is an alternative way some WHIP endpoints provide the answer if not in the body.
            if (whipResponse.status === 204 && whipResponse.headers.get('Location')) {
                const answerUrl = whipResponse.headers.get('Location');
                console.log("LSU: WHIP server returned 204 with Location header for SDP answer:", answerUrl);
                this._updateModalStreamStatus('WHIP resource created (204). Fetching SDP answer...', 'info');
                
                // Fetch the SDP answer from the Location URL.
                // This part is an addition to handle this WHIP flow.
                // It might require specific headers (like PATCH method if the server expects it, or GET)
                // For simplicity, assuming GET or that the initial POST was enough and ICE takes over.
                // If a PATCH with the offer is needed to this Location, the flow is more complex.
                // For now, we'll assume the 204 means the server accepted the offer and ICE will connect.
                // If it strictly needs a remote description set from this URL, more logic is needed here.
                // Cloudflare typically returns 201 with SDP in body, or just relies on ICE after 201.
                // This 204 handling is more for completeness with general WHIP/WHEP.
                // For Cloudflare, the original 201 handling below is more common.
                 this._updateModalStreamStatus('WHIP resource created (204). ICE will connect.', 'info');
                return; // Connection proceeds via ICE
            }
            const errorText = await whipResponse.text();
            throw new Error(`WHIP server error (${whipResponse.status}): ${errorText}`);
        }
        
        // Standard WHIP: 201 Created with SDP answer in the body
        const answerSdp = await whipResponse.text();
        if (!answerSdp && (whipResponse.status !== 201 && whipResponse.status !== 200)) { // Check status too
             throw new Error('WHIP server returned an empty SDP answer and unexpected status.');
        }

        if (answerSdp) { // Only set if SDP answer is provided
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp: answerSdp }));
            this._updateModalStreamStatus('SDP Answer received. WebRTC handshake complete.', 'info');
        } else if (whipResponse.status === 201) { // 201 but no SDP in body means ICE will handle it
             this._updateModalStreamStatus('WHIP endpoint acknowledged (201). ICE connection will establish.', 'info');
        } else {
             this._updateModalStreamStatus('SDP Answer was empty but status was not 201. Relying on ICE connection, but this is unusual.', 'warning');
        }
    }
    
    _showCloudflarePlayerInModal() {
        if (!this.isStreamingToCF) {
             this._updateModalStreamStatus('Stream is not active. Cannot show player.', 'warning');
            if(this.cfPlayerContainer) this.cfPlayerContainer.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">Stream not active.</span>';
            return;
        }
        if (this.cfPlayerContainer && this.streamData && this.streamData.playbackUid) {
            const uid = this.streamData.playbackUid;
            this.cfPlayerContainer.innerHTML = ''; 
            this.cfPlayerContainer.style.display = 'block';
            this.cfPlayerContainer.style.height = 'auto'; 
            
            const iframeResponsiveContainer = document.createElement('div');
            iframeResponsiveContainer.style.position = 'relative';
            iframeResponsiveContainer.style.paddingTop = '56.25%'; 
            iframeResponsiveContainer.style.height = '0';
            iframeResponsiveContainer.style.overflow = 'hidden';
            iframeResponsiveContainer.style.background = '#000'; 

            const iframe = document.createElement('iframe');
            const posterUrl = `https://videodelivery.net/${uid}/thumbnails/thumbnail.jpg?time=0s&height=360&width=640`;
            iframe.src = `https://iframe.videodelivery.net/${uid}?autoplay=true&muted=true&preload=metadata&poster=${encodeURIComponent(posterUrl)}`;
            iframe.style.position = 'absolute';
            iframe.style.top = '0';
            iframe.style.left = '0';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframe.allow = "accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;";
            iframe.allowFullscreen = true;

            iframeResponsiveContainer.appendChild(iframe);
            this.cfPlayerContainer.appendChild(iframeResponsiveContainer);
            this._updateModalStreamStatus('Livestream active', 'success'); 
        } else {
            const errorMsg = !this.streamData?.playbackUid ? 'Playback UID not available.' : 'Player container not found.';
            if (this.cfPlayerContainer) {
                 this.cfPlayerContainer.innerHTML = `<span class="text-sm text-red-500 dark:text-red-400">${errorMsg}</span>`;
                 this.cfPlayerContainer.style.display = 'flex';
            }
            this._updateModalStreamStatus(`Cannot show player: ${errorMsg}`, 'error');
        }
    }

    _stopCloudflareStreamAndWebcam(resetButtons = true) { 
        console.log("LSU: Stopping Cloudflare stream and webcam.");
        
        if (this.playerTimeoutId) {
            clearTimeout(this.playerTimeoutId);
            this.playerTimeoutId = null;
        }
        this.isStreamingToCF = false;
        this.playerInitialized = false;


        if (this.peerConnection) {
            try {
                this.peerConnection.getSenders().forEach(sender => {
                    if (sender.track && sender.track.readyState === 'live') sender.track.stop();
                });
                this.peerConnection.close();
            } catch (e) {
                console.warn("LSU: Error while closing peerConnection senders/tracks:", e);
            }
            this.peerConnection = null;
            console.log("LSU: PeerConnection closed.");
        }
        
        if (this.mediaStream) {
            this.mediaStream.getTracks().forEach(track => track.stop());
            this.mediaStream = null;
            console.log("LSU: Local media stream stopped.");
        }
        if (this.webcamVideoElement) {
            this.webcamVideoElement.srcObject = null;
            this.webcamVideoElement.style.display = 'block';
        }

        if (this.cfPlayerContainer) {
            this.cfPlayerContainer.innerHTML = '<span>Cloudflare Stream Preview (when live)</span>';
            this.cfPlayerContainer.style.display = 'none';
        }
        const statusEl = document.getElementById('lsu-stream-status-message');
        if(statusEl) this._updateModalStreamStatus('Stream stopped. Ready to start webcam.', 'info', statusEl);

        if (resetButtons) {
            const startWebcamBtn = document.getElementById('lsu-start-webcam-prepare-btn');
            const goLiveActualBtn = document.getElementById('lsu-go-live-actual-btn');
            if (startWebcamBtn) {
                startWebcamBtn.style.display = 'block';
                startWebcamBtn.disabled = false;
                startWebcamBtn.innerHTML = '🎙️ Start Camera & Prepare Stream';
            }
            if (goLiveActualBtn) {
                goLiveActualBtn.style.display = 'none';
                goLiveActualBtn.disabled = true;
                goLiveActualBtn.innerHTML = '🚀 Go Live!';
                goLiveActualBtn.className = 'w-full px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';
                // Re-attach original 'go live' functionality
                // This was a bit problematic, better to re-use the initial setup
                // For simplicity, the original onclick is already there from _populateLiveStreamForm
                // We just need to ensure its state is reset. The original click handler will be called if it's made visible and enabled again.
            }
        }
    }
    
    _animateAndRemoveFormModal() {
        this._stopCloudflareStreamAndWebcam(); 
        const modal = document.getElementById(this.activeFormModalId);
        if (modal) {
            const content = modal.querySelector('.bg-white, .dark\\:bg-dark-800');
            modal.classList.add('opacity-0'); if (content) content.classList.add('scale-95');
            setTimeout(() => this._removeFormModal(), 300);
        }
    }

    _removeFormModal() {
        this._stopCloudflareStreamAndWebcam(); 
        if (this.activeFormModalId) {
            const modal = document.getElementById(this.activeFormModalId);
            if (modal) modal.remove();
            this.activeFormModalId = null;
            this.streamData = null; 
            this._checkAndRestoreScroll();
        }
    }
    
    _checkAndRestoreScroll() {
        const muFormModalActive = document.querySelector('.mu-overlay'); 
        const lsuFormModalActive = this.activeFormModalId ? document.getElementById(this.activeFormModalId) : null; 
        const pfmModalActive = document.querySelector('.pfm-modal-overlay');
        const muGenericModalActive = document.querySelector('.mu-generic-modal-overlay'); 
        const lsuGenericModalActive = this.activeGenericModalId ? document.getElementById(this.activeGenericModalId) : null;

        if (!this.activeFormModalId && !this.activeGenericModalId &&
            !muFormModalActive && !lsuFormModalActive &&           
            !pfmModalActive && !muGenericModalActive && !lsuGenericModalActive) { 
            document.body.style.overflow = '';
        }
    }

    _getFriendlyWebcamError(err) { if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') return 'Camera/mic access denied.'; if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') return 'No camera/mic found.'; if (err.name === 'NotReadableError' || err.name === 'TrackStartError' || err.name === 'OverconstrainedError') return 'Camera/mic might be in use or unsupported settings.'; if (err.name === 'AbortError') return 'Camera/mic setup aborted.'; return `Error: ${err.name || 'Unknown'} (${err.message || 'No details'}).`; }
    _sanitizeHTML(str, allowBreaks = false) { if (typeof str !== 'string') str = String(str || ''); const temp = document.createElement('div'); temp.textContent = str; let s = temp.innerHTML; if (allowBreaks) s = s.replace(/\n/g, '<br>'); return s; }
    _generateFallbackAvatarSVG(name, size = 32) { const i = name ? name.trim().toUpperCase().charAt(0) : '?'; const c = ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#34495e']; const cc = i.charCodeAt(0) || 0; const bc = c[cc % c.length]; const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${bc}"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="50" fill="white" font-weight="bold">${i}</text></svg>`; return "data:image/svg+xml;base64," + btoa(svg); }
    
    _lsuShowGenericModal(title, message, type = 'info') {
        if (this.activeGenericModalId) { const e = document.getElementById(this.activeGenericModalId); if (e) e.remove(); }
        const genericModalId = 'lsu-generic-modal-active-' + Date.now(); this.activeGenericModalId = genericModalId; 
        const overlay = document.createElement('div'); overlay.id = this.activeGenericModalId;
        overlay.className = 'lsu-generic-modal-overlay fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-[1060] p-4 opacity-0 transition-opacity duration-300';
        let tc = 'text-gray-900 dark:text-white', iconHTML = '';
        switch (type) {
            case 'success': tc = 'text-green-600 dark:text-green-400'; iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 mb-3"><svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></div>'; break;
            case 'warning': tc = 'text-yellow-600 dark:text-yellow-400'; iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 mb-3"><svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>'; break;
            case 'error': tc = 'text-red-600 dark:text-red-400'; iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 mb-3"><svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>'; break;
            default: iconHTML = '<div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3"><svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>'; break;
        }
        const modalContentDiv = document.createElement('div'); modalContentDiv.className = 'bg-white dark:bg-dark-800 p-6 rounded-lg shadow-xl w-full max-w-sm text-center transform scale-95 transition-all duration-300';
        modalContentDiv.innerHTML = `${iconHTML}<h3 class="text-lg font-medium ${tc} mb-2">${this._sanitizeHTML(title)}</h3><p class="text-sm text-gray-600 dark:text-gray-300 mb-4">${this._sanitizeHTML(message, true)}</p><button id="lsu-generic-modal-close-${genericModalId}" class="w-full px-4 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-dark-800">OK</button>`;
        overlay.appendChild(modalContentDiv); document.body.appendChild(overlay);
        setTimeout(() => { overlay.classList.remove('opacity-0'); modalContentDiv.classList.remove('scale-95'); }, 10);
        const cb = document.getElementById(`lsu-generic-modal-close-${genericModalId}`);
        if (cb) { cb.focus(); cb.onclick = () => { overlay.classList.add('opacity-0'); modalContentDiv.classList.add('scale-95'); setTimeout(() => { overlay.remove(); this.activeGenericModalId = null; this._checkAndRestoreScroll(); }, 300); }; }
        if ((type === 'success' || type === 'info') && !message.toLowerCase().includes('error')) { setTimeout(() => { if (document.getElementById(genericModalId)) { cb?.click(); } }, 3000); }
    }

    _applySelectStyles(selectElement) {
        selectElement.style.paddingTop = '0.125rem'; selectElement.style.paddingBottom = '0.125rem';
        selectElement.style.paddingLeft = '0.5rem';  selectElement.style.paddingRight = '1.75rem';
        selectElement.style.backgroundImage = `url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e")`;
        selectElement.style.backgroundPosition = 'right 0.5rem center'; selectElement.style.backgroundRepeat = 'no-repeat';
        selectElement.style.backgroundSize = '1em 1em';
        if (document.documentElement.classList.contains('dark')) {
            selectElement.style.backgroundImage = `url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e")`;
        }
    }
    _applyTextareaFocusStyles(textareaElement) {
        textareaElement.style.setProperty('--tw-ring-offset-shadow', '0 0 #0000');
        textareaElement.style.setProperty('--tw-ring-shadow', '0 0 #0000');
        textareaElement.style.boxShadow = 'var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow, 0 0 #0000)';
    }
    async _createStreamPostOnBackend(description, visibility) {
        if (!this.streamData || !this.streamData.liveInputId || !this.streamData.playbackUid) {
            console.error("LSU: Missing stream data for creating post on backend.");
            this._lsuShowGenericModal('Post Creation Failed', 'Could not create the stream post due to missing stream details.', 'error');
            return false;
        }

        const formData = new FormData();
        formData.append('post_content', description);
        formData.append('visibility', visibility);
        formData.append('cf_live_input_id', this.streamData.liveInputId);
        formData.append('cf_playback_uid', this.streamData.playbackUid);
        
        // ============ CSRF CHANGE 1: Add token to Live Stream post ============
        const csrfToken = window.getCsrfToken(); // Use the global helper
        if (!csrfToken) {
            this._lsuShowGenericModal('Security Error', 'Could not verify your request. Please refresh the page and try again.', 'error');
            console.error("LSU: CSRF token is missing. Cannot create stream post.");
            return false;
        }
        formData.append('csrf_token', csrfToken); // Assumes backend expects 'csrf_token'
        // ======================= END OF CSRF CHANGE =======================

        try {
            this._updateModalStreamStatus('Creating stream post...', 'info');
            const response = await fetch('/post/create_with_stream', { 
                method: 'POST',
                body: formData,
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `Server error ${response.status} creating stream post.`);
            }

            this._updateModalStreamStatus('Stream post created. Going live!', 'info'); 
            console.log("LSU: Stream post created successfully on backend.", result.post);

            if (window.globalPostFeedManager && typeof window.globalPostFeedManager.prependNewPost === 'function' && result.post) {
                window.globalPostFeedManager.prependNewPost(result.post);
            }
            return true;
        } catch (error) {
            console.error("LSU: Error creating stream post on backend:", error);
            this._lsuShowGenericModal('Post Creation Failed', `Could not create the stream post: ${error.message}`, 'error');
            return false;
        }
    }
}

// =========== Consolidated DOMContentLoaded and Initializer ===========
document.addEventListener('DOMContentLoaded', () => {
    // ... (Your existing DOMContentLoaded content is fine) ...
    console.log("DOM fully loaded and parsed.");
    
    // ============ CSRF CHANGE 2: Create a global helper function ============
    /**
     * Retrieves the CSRF token from the <meta name="csrf-token"> tag in the document's head.
     * This function is made globally available for easy access from various parts of the application,
     * including dynamically patched methods.
     * @returns {string|null} The CSRF token content, or null if not found.
     */
    window.getCsrfToken = () => {
        const tokenElement = document.querySelector('meta[name="csrf-token"]');
        if (!tokenElement) {
            console.error('CSRF token meta tag not found. Please ensure your HTML includes: <meta name="csrf-token" content="...">');
            return null;
        }
        return tokenElement.getAttribute('content');
    };
    // ======================= END OF CSRF CHANGE =======================


    // --- Initialize MediaUploader ---
    const mediaTriggerButtonId = 'composer-photo-video-btn'; 
    const photoVideoButton = document.getElementById(mediaTriggerButtonId); 
    if (photoVideoButton) {
        new MediaUploader(mediaTriggerButtonId);
        console.log(`MediaUploader initialized for button ID: ${mediaTriggerButtonId}`);
    } else {
        console.warn(`DOMContentLoaded: Trigger button ID '${mediaTriggerButtonId}' for MediaUploader NOT FOUND.`);
    }

    // --- Initialize LiveStreamUploader ---
    const liveTriggerButtonId = 'composer-live-video-btn'; 
    const liveVideoButton = document.getElementById(liveTriggerButtonId); 
    if (liveVideoButton) {
        new LiveStreamUploader(liveTriggerButtonId);
        console.log(`LiveStreamUploader initialized for button ID: ${liveTriggerButtonId}`);
    } else {
        console.warn(`DOMContentLoaded: Trigger button ID '${liveTriggerButtonId}' for LiveStreamUploader NOT FOUND.`);
    }

    // --- Consolidated Style Injection ---
    const style = document.createElement('style');
    style.textContent = `
        .loading-spinner-white-xs { 
            display: inline-block; 
            width: 0.875em; height: 0.875em; 
            vertical-align: -0.125em; 
            border: 2px solid rgba(255, 255, 255, 0.3); 
            border-left-color: #ffffff; 
            border-radius: 50%; 
            animation: uploader-spin 0.7s linear infinite; 
        }
        @keyframes uploader-spin { to { transform: rotate(360deg); } }
        .mu-overlay.opacity-0, .lsu-overlay.opacity-0, 
        .mu-generic-modal-overlay.opacity-0, .lsu-generic-modal-overlay.opacity-0 { 
            opacity: 0; 
        }
        .mu-overlay .transform.scale-95, .lsu-overlay .transform.scale-95,
        .mu-generic-modal-overlay .transform.scale-95, .lsu-generic-modal-overlay .transform.scale-95 { 
            transform: scale(0.95); 
        }
        textarea#post-media-caption:focus, textarea#live-stream-description:focus {
            outline: none !important;
            box-shadow: none !important; 
            border-color: transparent !important; 
            --tw-ring-color: transparent !important; 
        }
        select#post-media-visibility, select#live-stream-visibility {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
    `;
    document.head.appendChild(style);
    console.log("Consolidated styles injected.");

    if (window.globalPostFeedManager) {
        if (!window.globalPostFeedManager.handleNewPostSubmissionFromUploader) {
            window.globalPostFeedManager.handleNewPostSubmissionFromUploader = async function({ caption, visibility, file }) {
                console.log("PostFeedManager: Handling submission from MediaUploader:", { caption, visibility, filePresent: !!file });
                const formData = new FormData();
                formData.append('post_content', caption);
                formData.append('visibility', visibility);
                if (file) { formData.append('media_file', file); }

                // ============ CSRF CHANGE 3: Add token to Media post ============
                const csrfToken = window.getCsrfToken(); // Use the global helper
                if (!csrfToken) {
                    const errorMsg = 'Could not verify your request. Please refresh the page and try again.';
                    if (typeof this._showInternalGenericModal === 'function') {
                        this._showInternalGenericModal('Security Error', errorMsg, 'error');
                    } else {
                        alert('Security Error: ' + errorMsg);
                    }
                    throw new Error('CSRF token is missing.');
                }
                formData.append('csrf_token', csrfToken); // Assumes backend expects 'csrf_token'
                // ======================= END OF CSRF CHANGE =======================

                const endpoint = file ? '/post/create_with_media' : '/post';
                try {
                    const response = await fetch(endpoint, { method: 'POST', body: formData });
                    const result = await response.json();
                    if (response.ok && result.success && result.post) {
                        if (typeof this.prependNewPost === 'function') {
                            this.prependNewPost(result.post); 
                        } else {
                             console.warn("PostFeedManager's prependNewPost method not found. Cannot add post to UI directly.");
                        }
                        if (typeof this._showInternalGenericModal === 'function') {
                            this._showInternalGenericModal('Success!', result.message || 'Your post has been created.', 'success');
                        } else {
                            alert('Success! ' + (result.message || 'Your post has been created.'));
                        }
                    } else {
                        throw new Error(result.message || result.error || `Server error (status ${response.status})`);
                    }
                } catch (error) {
                    console.error("PostFeedManager: Error submitting post:", error);
                     if (typeof this._showInternalGenericModal === 'function') {
                        this._showInternalGenericModal('Upload Failed', `An error occurred: ${error.message}`, 'error');
                    } else {
                        alert('Upload Failed: ' + error.message);
                    }
                    throw error; 
                }
            };
            console.log("DOMContentLoaded: Successfully patched PostFeedManager with handleNewPostSubmissionFromUploader.");
        } else {
            console.log("DOMContentLoaded: PostFeedManager already has handleNewPostSubmissionFromUploader.");
        }
    } else {
        console.warn("DOMContentLoaded: window.globalPostFeedManager not found. Post submission from MediaUploader will not work with full integration.");
    }
});