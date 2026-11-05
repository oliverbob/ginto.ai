// public/js/stories.js

class SaiStories {
    constructor(containerSelector, currentUser, config = {}) {
        // ... (existing constructor properties) ...
        this.overallStoriesContainer = document.querySelector(containerSelector);
        this.currentUser = currentUser;

        this.storiesContainer = document.getElementById('stories-items-container');

        this.modal = document.getElementById('createStoryModal');
        this.closeModalButton = document.getElementById('closeCreateStoryModal');
        this.cancelModalButton = document.getElementById('cancelCreateStory');
        this.createStoryForm = document.getElementById('createStoryForm');
        this.submitStoryButton = document.getElementById('submitCreateStory');
        this.storyContentTypeSelect = document.getElementById('storyContentType');
        this.mediaUploadSection = document.getElementById('storyMediaUploadSection');
        this.codeSnippetSection = document.getElementById('storyCodeSnippetSection');
        this.backgroundColorSection = document.getElementById('storyBackgroundColorSection');
        this.storyTextContentWrapper = document.getElementById('storyTextContentWrapper');
        this.storyMediaFile = document.getElementById('storyMediaFile');
        this.imagePreview = document.getElementById('storyImagePreview');
        this.videoPreview = document.getElementById('storyVideoPreview');
        this.mediaPreviewPlaceholder = document.getElementById('storyMediaPreviewPlaceholder');
        this.storyCreationError = document.getElementById('storyCreationError');

        this.scrollLeftButton = document.getElementById('scrollStoriesLeft');
        this.scrollRightButton = document.getElementById('scrollStoriesRight');
        this.isDragging = false;
        this.startX = 0;
        this.scrollLeftStart = 0;
        this.scrollAmount = 300;

        this.viewerModal = document.getElementById('storyViewerModal');
        this.viewerContent = document.getElementById('storyViewerContent');
        this.viewerUserAvatar = document.getElementById('storyViewerUserAvatar');
        this.viewerUserName = document.getElementById('storyViewerUserName');
        this.viewerTimestamp = document.getElementById('storyViewerTimestamp');
        this.closeViewerButton = document.getElementById('closeStoryViewer');
        this.viewerPrevItemButton = document.getElementById('storyViewerPrevItem');
        this.viewerNextItemButton = document.getElementById('storyViewerNextItem');
        this.currentStoryData = null;

        this._monacoCDNPath = config.monacoCDNPath || "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs"; // Or your preferred version
        this._monacoReadyPromise = null; // Still useful to prevent multiple load attempts

        if (!this.overallStoriesContainer || !this.storiesContainer || !this.modal || !this.createStoryForm || !this.viewerModal) {
            console.error('Essential story components not found. Story functionality may be impaired.');
            const createBtnStatic = document.getElementById('create-story-button-static');
            if (createBtnStatic) createBtnStatic.style.display = 'none';
            return;
        }
        this.init();
    }

    init() {
        this.setupCreateStoryButton();
        this.setupModalEventListeners();
        this.setupScrollFunctionality();
        this.setupStoryViewerEventListeners();
        this.fetchAndRenderStories();
    }

    setupCreateStoryButton() {
        const createStoryButtonStatic = document.getElementById('create-story-button-static');
        if (!createStoryButtonStatic) {
            console.warn('"Create Story" button static placeholder not found.');
            return;
        }
        if (this.currentUser && this.currentUser.id) {
            const avatarImg = createStoryButtonStatic.querySelector('img.user-avatar-for-create');
            if (avatarImg) {
                const userAvatarSrc = this.currentUser.profilePicture
                    ? this._sanitizeHTML(this.currentUser.profilePicture)
                    : this._generateClientFallbackSVG(this.currentUser.fullName || this.currentUser.username, 40);
                avatarImg.src = userAvatarSrc;
                avatarImg.alt = this._sanitizeHTML(this.currentUser.fullName || this.currentUser.username || 'Your avatar');
            }
        }
        createStoryButtonStatic.addEventListener('click', () => this.openCreateStoryModal());
        createStoryButtonStatic.addEventListener('keypress', (e) => { if (e.key === 'Enter' || e.key === ' ') this.openCreateStoryModal(); });
    }

    setupModalEventListeners() {
        if (!this.modal || !this.closeModalButton || !this.cancelModalButton || !this.createStoryForm || !this.storyContentTypeSelect || !this.storyMediaFile) {
            console.error("One or more creation modal elements are missing for event listener setup.");
            return;
        }
        this.closeModalButton.addEventListener('click', () => this.closeCreateStoryModal());
        this.cancelModalButton.addEventListener('click', () => this.closeCreateStoryModal());
        this.modal.addEventListener('click', (event) => {
            if (event.target === this.modal) this.closeCreateStoryModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.modal && !this.modal.classList.contains('hidden')) {
                this.closeCreateStoryModal();
            }
        });
        this.storyContentTypeSelect.addEventListener('change', (e) => this.handleContentTypeChange(e.target.value));
        this.storyMediaFile.addEventListener('change', (e) => this.previewMediaFile(e.target.files[0]));
        this.createStoryForm.addEventListener('submit', (e) => this.handleStoryFormSubmit(e));
        this.handleContentTypeChange(this.storyContentTypeSelect.value);
    }

    openCreateStoryModal() {
        if (!this.currentUser || !this.currentUser.id) {
            alert("Please log in to create a story."); return;
        }
        if (!this.modal) return;
        this.resetCreateStoryForm();
        this.modal.classList.remove('hidden');
        if (this.storyContentTypeSelect) this.storyContentTypeSelect.focus();
    }

    closeCreateStoryModal() {
        if (!this.modal) return;
        this.modal.classList.add('hidden');
        this.resetCreateStoryForm();
    }

    resetCreateStoryForm() {
        if (!this.createStoryForm) return;
        this.createStoryForm.reset();
        if(this.imagePreview) { this.imagePreview.classList.add('hidden'); this.imagePreview.src = '#'; }
        if(this.videoPreview) { this.videoPreview.classList.add('hidden'); this.videoPreview.src = '#'; }
        if(this.mediaPreviewPlaceholder) this.mediaPreviewPlaceholder.classList.remove('hidden');
        if(this.storyCreationError) { this.storyCreationError.classList.add('hidden'); this.storyCreationError.textContent = ''; }
        if(this.submitStoryButton) { this.submitStoryButton.disabled = false; this.submitStoryButton.innerHTML = 'Create Story'; }
        if(this.storyContentTypeSelect) this.handleContentTypeChange(this.storyContentTypeSelect.value);
    }

    handleContentTypeChange(selectedType) {
        if(this.mediaUploadSection) this.mediaUploadSection.classList.toggle('hidden', selectedType !== 'image' && selectedType !== 'video');
        if(this.codeSnippetSection) this.codeSnippetSection.classList.toggle('hidden', selectedType !== 'code_snippet');
        if(this.storyTextContentWrapper) this.storyTextContentWrapper.classList.toggle('hidden', selectedType === 'image' || selectedType === 'video');
        if(this.backgroundColorSection) this.backgroundColorSection.classList.toggle('hidden', selectedType !== 'text_only' && selectedType !== 'code_snippet');
        if (selectedType !== 'image' && selectedType !== 'video') {
            if(this.storyMediaFile) this.storyMediaFile.value = '';
            if(this.imagePreview) { this.imagePreview.classList.add('hidden'); this.imagePreview.src = '#'; }
            if(this.videoPreview) { this.videoPreview.classList.add('hidden'); this.videoPreview.src = '#'; }
            if(this.mediaPreviewPlaceholder) this.mediaPreviewPlaceholder.classList.remove('hidden');
        }
    }

    previewMediaFile(file) {
        if(this.mediaPreviewPlaceholder) this.mediaPreviewPlaceholder.classList.add('hidden');
        if(this.imagePreview) this.imagePreview.classList.add('hidden');
        if(this.videoPreview) this.videoPreview.classList.add('hidden');
        if (!file) {
            if(this.mediaPreviewPlaceholder) this.mediaPreviewPlaceholder.classList.remove('hidden');
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            if (file.type.startsWith('image/')) {
                if(this.imagePreview) { this.imagePreview.src = e.target.result; this.imagePreview.classList.remove('hidden'); }
            } else if (file.type.startsWith('video/')) {
                if(this.videoPreview) { this.videoPreview.src = e.target.result; this.videoPreview.classList.remove('hidden'); }
            } else {
                if(this.mediaPreviewPlaceholder) this.mediaPreviewPlaceholder.classList.remove('hidden');
            }
        };
        reader.onerror = () => {
            console.error("File reading error for preview.");
            if(this.mediaPreviewPlaceholder) this.mediaPreviewPlaceholder.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    async handleStoryFormSubmit(event) {
        event.preventDefault();
        if(!this.submitStoryButton || !this.storyCreationError) return;
        this.submitStoryButton.disabled = true;
        this.submitStoryButton.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>Creating...`;
        this.storyCreationError.classList.add('hidden'); this.storyCreationError.textContent = '';
        const formData = new FormData(this.createStoryForm);

        // ============ CSRF CHANGE 1: Add CSRF token to story creation ============
        const csrfToken = window.getCsrfToken();
        if (!csrfToken) {
            this.storyCreationError.textContent = 'Security token missing. Please refresh the page and try again.';
            this.storyCreationError.classList.remove('hidden');
            this.submitStoryButton.disabled = false;
            this.submitStoryButton.innerHTML = 'Create Story';
            return;
        }
        formData.append('csrf_token', csrfToken);
        // ======================= END OF CSRF CHANGE =======================

        try {
            const response = await fetch('/post/stories/create', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
            const result = await response.json();
            if (result.success && result.story) {
                this.closeCreateStoryModal();
                this.prependStoryElement(result.story);
            } else {
                this.storyCreationError.textContent = result.message || 'Failed to create story.';
                this.storyCreationError.classList.remove('hidden');
                this.submitStoryButton.disabled = false; this.submitStoryButton.innerHTML = 'Create Story';
            }
        } catch (error) {
            console.error('Error submitting story:', error);
            this.storyCreationError.textContent = 'A network error occurred. Please try again.';
            this.storyCreationError.classList.remove('hidden');
            this.submitStoryButton.disabled = false; this.submitStoryButton.innerHTML = 'Create Story';
        }
    }

    prependStoryElement(storyData) {
        const storyElement = this._createDynamicStoryElement(storyData);
        if (storyElement) {
            this.removePlaceholderStories();
            const createButton = document.getElementById('create-story-button-static');
            if (createButton && createButton.nextSibling) {
                this.storiesContainer.insertBefore(storyElement, createButton.nextSibling);
            } else if (createButton) {
                this.storiesContainer.appendChild(storyElement);
            } else {
                this.storiesContainer.prepend(storyElement);
            }
            this.checkScrollButtonsVisibility();
        }
    }

    async fetchAndRenderStories() {
        try {
            // This is a GET request, so no CSRF token is needed.
            const response = await fetch('/post/stories/active');
            if (!response.ok) { console.error(`Failed to fetch stories. Status: ${response.status}`); this.checkScrollButtonsVisibility(); return; }
            const data = await response.json();
            if (data.success && data.stories) {
                if (data.stories.length > 0) {
                    this.removePlaceholderStories();
                    this.renderDynamicStories(data.stories);
                } else {
                    console.log('No active stories. Placeholders remain (or clear dynamic if needed).');
                    this.renderDynamicStories([]); // Clear any old dynamic stories
                }
            } else {
                console.error('API reported no success or stories missing:', data.message || 'Unknown API error');
            }
        } catch (error) {
            console.error('Network error fetching stories:', error);
        } finally {
            this.checkScrollButtonsVisibility();
        }
    }

    removePlaceholderStories() {
        if (!this.storiesContainer) return;
        const placeholders = this.storiesContainer.querySelectorAll('.placeholder-story-item');
        placeholders.forEach(placeholder => placeholder.remove());
    }

    renderDynamicStories(stories) {
        if (!this.storiesContainer) return;
        const existingDynamicStories = this.storiesContainer.querySelectorAll('.dynamic-story-item');
        existingDynamicStories.forEach(el => el.remove());
        stories.forEach(story => {
            const storyElement = this._createDynamicStoryElement(story);
            if (storyElement) this.storiesContainer.appendChild(storyElement);
        });
    }

    setupScrollFunctionality() {
        if (!this.storiesContainer || !this.scrollLeftButton || !this.scrollRightButton) {
            console.warn('Scroll elements not found, scroll functionality disabled.');
            if(this.scrollLeftButton) this.scrollLeftButton.style.display = 'none';
            if(this.scrollRightButton) this.scrollRightButton.style.display = 'none';
            return;
        }
        this.scrollLeftButton.addEventListener('click', () => this.storiesContainer.scrollBy({ left: -this.scrollAmount, behavior: 'smooth' }));
        this.scrollRightButton.addEventListener('click', () => this.storiesContainer.scrollBy({ left: this.scrollAmount, behavior: 'smooth' }));
        this.storiesContainer.addEventListener('scroll', () => this.checkScrollButtonsVisibility());
        window.addEventListener('resize', () => this.checkScrollButtonsVisibility());
        const startDrag = (e) => {
            this.isDragging = true; this.storiesContainer.classList.add('dragging');
            this.startX = (e.pageX || e.touches[0].pageX) - this.storiesContainer.offsetLeft;
            this.scrollLeftStart = this.storiesContainer.scrollLeft;
        };
        const doDrag = (e) => {
            if (!this.isDragging) return; e.preventDefault();
            const x = (e.pageX || e.touches[0].pageX) - this.storiesContainer.offsetLeft;
            const walk = (x - this.startX) * 1.5;
            this.storiesContainer.scrollLeft = this.scrollLeftStart - walk;
        };
        const endDrag = () => {
            if (!this.isDragging) return; this.isDragging = false;
            this.storiesContainer.classList.remove('dragging');
            this.checkScrollButtonsVisibility();
        };
        this.storiesContainer.addEventListener('mousedown', startDrag);
        this.storiesContainer.addEventListener('touchstart', startDrag, { passive: true });
        this.storiesContainer.addEventListener('mousemove', doDrag);
        this.storiesContainer.addEventListener('touchmove', doDrag, { passive: false });
        this.storiesContainer.addEventListener('mouseup', endDrag);
        this.storiesContainer.addEventListener('mouseleave', endDrag);
        this.storiesContainer.addEventListener('touchend', endDrag);
        this.storiesContainer.addEventListener('touchcancel', endDrag);
        this.checkScrollButtonsVisibility();
    }

    checkScrollButtonsVisibility() {
        if (!this.storiesContainer || !this.scrollLeftButton || !this.scrollRightButton) return;
        const { scrollLeft, scrollWidth, clientWidth } = this.storiesContainer;
        const isOverflowing = scrollWidth > clientWidth + 1; // +1 for subpixel precision issues
        this.scrollLeftButton.classList.toggle('hidden', !isOverflowing || scrollLeft <= 0);
        this.scrollRightButton.classList.toggle('hidden', !isOverflowing || scrollLeft >= (scrollWidth - clientWidth - 1));
    }

    setupStoryViewerEventListeners() {
        if (!this.viewerModal || !this.closeViewerButton) {
             console.warn("Story viewer modal or close button not found. Viewer interactions disabled.");
             return;
        }
        this.closeViewerButton.addEventListener('click', () => this.closeStoryViewer());
        this.viewerModal.addEventListener('click', (event) => {
            if (event.target === this.viewerModal) this.closeStoryViewer();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.viewerModal && !this.viewerModal.classList.contains('hidden')) {
                this.closeStoryViewer();
            }
        });
    }

    openStoryViewer(storyData) {
        if (!this.viewerModal || !storyData) return;
        this.currentStoryData = storyData;
        this.renderStoryInViewer(storyData);
        this.viewerModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    renderStoryInViewer(storyData) {
        if (!this.viewerContent || !this.viewerUserAvatar || !this.viewerUserName) return;

        this.viewerUserAvatar.src = storyData.user_avatar || this._generateClientFallbackSVG(storyData.full_name || storyData.username, 40);
        this.viewerUserAvatar.alt = this._sanitizeHTML(storyData.full_name || storyData.username || 'User');
        this.viewerUserName.textContent = this._sanitizeHTML(storyData.full_name || storyData.username || 'User');
        if (this.viewerTimestamp && storyData.created_at) {
             this.viewerTimestamp.textContent = new Date(storyData.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        } else if (this.viewerTimestamp) {
            this.viewerTimestamp.textContent = '';
        }

        this.viewerContent.innerHTML = '';
        let itemHTML = '';

        if (storyData.content_type === 'code_snippet') {
            const codeBg = this._sanitizeHTML(storyData.background_color || '#1F2937');
            const codeLang = this._sanitizeHTML(storyData.code_language || 'plaintext');
            const rawCodeContent = storyData.code_content || '// No code provided';
            const rawCodeTitle = storyData.text_overlay || '';
            const monacoContainerId = `storyViewerMonaco-${storyData.id}`;

            itemHTML = `
                <div class="w-full h-full flex flex-col p-4 pt-14 md:pt-16" style="background-color: ${codeBg};">
                    ${rawCodeTitle ? `<h4 class="text-xl font-semibold text-gray-200 mb-2 break-words shrink-0">${this._sanitizeHTML(rawCodeTitle)}</h4>` : ''}
                    ${codeLang !== 'plaintext' ? `<p class="text-sm text-gray-400 mb-1 shrink-0">Language: ${this._sanitizeHTML(codeLang)}</p>` : ''}
                    <div id="${monacoContainerId}" class="flex-grow min-h-0 w-full border border-gray-600 rounded"></div>
                </div>`;
            this.viewerContent.innerHTML = itemHTML;
            this.initializeReadOnlyMonaco(monacoContainerId, rawCodeContent, codeLang);

        } else { 
            switch (storyData.content_type) {
                case 'image':
                    itemHTML = `<img src="${this._sanitizeHTML(storyData.final_media_url)}" alt="Story by ${this._sanitizeHTML(storyData.full_name || storyData.username)}" class="w-full h-full object-contain bg-black">`;
                    if (storyData.text_overlay) {
                        itemHTML += `<div class="absolute bottom-10 left-4 right-4 p-2 bg-black bg-opacity-40 rounded text-white text-center text-lg font-semibold" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">${this._sanitizeHTML(storyData.text_overlay)}</div>`;
                    }
                    break;
                case 'video':
                    itemHTML = `<video src="${this._sanitizeHTML(storyData.final_media_url)}" class="w-full h-full object-contain bg-black" autoplay controls playsinline loop>Your browser does not support the video tag.</video>`;
                    if (storyData.text_overlay) {
                        itemHTML += `<div class="absolute bottom-10 left-4 right-4 p-2 bg-black bg-opacity-40 rounded text-white text-center text-lg font-semibold" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">${this._sanitizeHTML(storyData.text_overlay)}</div>`;
                    }
                    break;
                case 'text_only':
                    const bgColor = this._sanitizeHTML(storyData.background_color || '#007bff');
                    const font = this._sanitizeHTML(storyData.font_family || 'Arial, sans-serif');
                    itemHTML = `<div class="w-full h-full flex items-center justify-center p-8 text-center" style="background-color: ${bgColor};"><p class="text-white text-2xl md:text-3xl font-bold leading-tight" style="font-family: ${font}; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">${this._sanitizeHTML(storyData.text_overlay || ' ')}</p></div>`;
                    break;
                default:
                    itemHTML = `<div class="w-full h-full flex items-center justify-center bg-gray-700"><p class="text-white">Unsupported story type.</p></div>`;
            }
            this.viewerContent.innerHTML = itemHTML;
        }

        const videoElement = this.viewerContent.querySelector('video');
        if (videoElement) {
            videoElement.play().catch(error => console.warn("Video autoplay prevented:", error));
        }
    }
    
    ensureMonacoIsReady() {
        if (this._monacoReadyPromise) {
            return this._monacoReadyPromise;
        }

        this._monacoReadyPromise = new Promise((resolve, reject) => {
            if (typeof window.monaco !== 'undefined' && typeof window.monaco.editor !== 'undefined') {
                console.log('Monaco already loaded.');
                resolve();
                return;
            }
            const loadScript = (src) => {
                return new Promise((scriptResolve, scriptReject) => {
                    if (document.querySelector(`script[src="${src}"]`)) {
                        console.log(`${src} script tag already exists.`);
                        scriptResolve(); 
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = src;
                    script.onload = () => {
                        console.log(`${src} loaded successfully.`);
                        scriptResolve();
                    };
                    script.onerror = (err) => {
                        console.error(`Failed to load script: ${src}`, err);
                        scriptReject(new Error(`Failed to load script: ${src}`));
                    };
                    document.head.appendChild(script);
                });
            };
            const loaderSrc = `${this._monacoCDNPath}/loader.js`;
            const setupAndLoadMonaco = () => {
                if (typeof window.require === 'undefined' || typeof window.require.config !== 'function') {
                     const msg = "RequireJS (Monaco loader) is not available. Cannot configure Monaco.";
                     console.error(msg);
                     reject(new Error(msg));
                     return;
                }
                console.log(`Configuring Monaco paths to CDN: '${this._monacoCDNPath}'`);
                window.require.config({ paths: { 'vs': this._monacoCDNPath } });
                console.log('Loading Monaco editor main module (vs/editor/editor.main) from CDN...');
                window.require(['vs/editor/editor.main'], () => {
                    console.log('Monaco editor main module loaded from CDN.');
                    if (typeof window.monaco !== 'undefined' && typeof window.monaco.editor !== 'undefined') {
                        resolve();
                    } else {
                        reject(new Error('Monaco loaded from CDN but monaco.editor is not available.'));
                    }
                }, (err) => {
                    console.error('Failed to load Monaco editor main module from CDN:', err);
                    reject(err);
                });
            };

            if (typeof window.require !== 'undefined' && typeof window.require.config === 'function') {
                console.log('Monaco loader (require.js) already present.');
                setupAndLoadMonaco();
            } else {
                console.log('Loading Monaco loader (loader.js) from CDN...');
                loadScript(loaderSrc)
                    .then(setupAndLoadMonaco)
                    .catch(err => {
                        console.error('Error in Monaco CDN loading sequence (loader.js part):', err);
                        this._monacoReadyPromise = null; 
                        reject(err);
                    });
            }
        });
        return this._monacoReadyPromise;
    }

    async initializeReadOnlyMonaco(containerId, codeContent, language) {
        const containerElement = document.getElementById(containerId);
        if (!containerElement) {
            console.error(`Monaco container #${containerId} not found.`);
            return;
        }
        try {
            console.log(`Attempting to initialize Monaco for ${containerId} (CDN)...`);
            await this.ensureMonacoIsReady();
            console.log(`Monaco should be ready for ${containerId} via CDN. window.monaco:`, window.monaco);
            const editorOptions = {
                value: codeContent,
                language: language.toLowerCase() || 'plaintext',
                theme: 'vs-dark', 
                readOnly: true,
                automaticLayout: true, 
                minimap: { enabled: true },
                lineNumbers: 'on', 
                scrollBeyondLastLine: false,
                fontSize: 13, 
                wordWrap: 'on', 
                glyphMargin: false, 
                folding: false, 
                lineDecorationsWidth: 0, 
                lineNumbersMinChars: 3, 
                renderLineHighlight: 'none', 
                scrollbar: {
                    verticalScrollbarSize: 8,
                    horizontalScrollbarSize: 8,
                }
            };
            while (containerElement.firstChild) {
                containerElement.removeChild(containerElement.firstChild);
            }
            if (containerElement.editorInstance) {
                console.log(`Disposing previous editor instance for ${containerId}.`);
                containerElement.editorInstance.dispose();
            }
            console.log(`Creating Monaco editor instance for ${containerId} from CDN with minimap and line numbers.`);
            const editor = window.monaco.editor.create(containerElement, editorOptions);
            containerElement.editorInstance = editor; 
            console.log(`Monaco editor for ${containerId} (CDN) created successfully.`);

        } catch (error) {
            console.error(`Error initializing Monaco editor for ${containerId} (CDN):`, error);
            containerElement.innerHTML = `<div class="p-4 text-gray-300 bg-gray-800 rounded h-full overflow-auto"><p class="font-semibold text-red-400">Error: Code editor could not be loaded.</p><pre class="mt-2 text-xs whitespace-pre-wrap">${this._sanitizeHTML(codeContent)}</pre></div>`;
        }
    }

    closeStoryViewer() {
        if (!this.viewerModal) return;

        if (this.currentStoryData && this.currentStoryData.content_type === 'code_snippet') {
            const monacoContainerId = `storyViewerMonaco-${this.currentStoryData.id}`;
            const containerElement = document.getElementById(monacoContainerId);
            if (containerElement && containerElement.editorInstance) {
                 containerElement.editorInstance.dispose();
                 delete containerElement.editorInstance; // Clean up reference
                 console.log(`Monaco editor for ${monacoContainerId} disposed via instance.`);
            } else if (containerElement) {
                containerElement.innerHTML = ''; // Fallback if instance not found
            }
        }

        this.viewerModal.classList.add('hidden');
        if(this.viewerContent) this.viewerContent.innerHTML = '';
        this.currentStoryData = null;
        document.body.style.overflow = '';
    }

    _sanitizeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        const temp = document.createElement('div'); temp.textContent = str; return temp.innerHTML;
    }

    _generateClientFallbackSVG(name = 'User', size = 32) {
        const trimmedName = name ? name.trim() : 'User'; let initials = '?';
        if (trimmedName && trimmedName !== 'User') {
            const nameParts = trimmedName.split(/\s+/).filter(part => part.length > 0);
            if (nameParts.length >= 2) {
                const firstInitial = nameParts[0].charAt(0).toUpperCase();
                const lastInitial = nameParts[nameParts.length - 1].charAt(0).toUpperCase();
                initials = firstInitial + lastInitial;
            } else if (nameParts.length === 1 && nameParts[0].length > 0) {
                initials = nameParts[0].charAt(0).toUpperCase();
            }
        }
        if (!initials.match(/^[A-Z0-9]{1,2}$/i) && initials !== '?') {
             initials = trimmedName.length > 0 ? trimmedName.charAt(0).toUpperCase() : '?';
             if (!initials.match(/^[A-Z0-9]$/i)) initials = '?';
        }
        let hueSeed = 0; for (let i = 0; i < trimmedName.length; i++) { hueSeed = ((hueSeed << 5) - hueSeed) + trimmedName.charCodeAt(i); hueSeed |= 0; }
        const hue = Math.abs(hueSeed % 360); const saturation = 70; const lightness = 45;
        const bgColor = `hsl(${hue}, ${saturation}%, ${lightness}%)`;
        const textColor = `hsl(${hue}, ${saturation - 40 > 0 ? saturation - 40 : 20}%, ${lightness > 50 ? 15 : 95}%)`;
        const fontSize = (initials.length > 1 ? size * 0.35 : size * 0.5);
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}"><rect width="100%" height="100%" fill="${bgColor}"/><text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" fill="${textColor}" font-size="${fontSize}px" font-family="Arial, Helvetica, sans-serif" font-weight="bold">${this._sanitizeHTML(initials)}</text></svg>`;
        return `data:image/svg+xml;base64,${btoa(svg)}`;
    }

    _createDynamicStoryElement(story) {
        const storyDiv = document.createElement('div');
        storyDiv.className = 'flex-shrink-0 relative w-28 h-44 md:w-32 md:h-48 rounded-xl overflow-hidden story-item dynamic-story-item cursor-pointer bg-gray-200 dark:bg-dark-600 group';
        storyDiv.dataset.storyId = story.id;
        storyDiv.setAttribute('role', 'button'); storyDiv.setAttribute('tabindex', '0');
        storyDiv.setAttribute('aria-label', `View story by ${this._sanitizeHTML(story.full_name || story.username || 'User')}`);
        const storyUserAvatar = story.user_avatar ? this._sanitizeHTML(story.user_avatar) : this._generateClientFallbackSVG(story.full_name || story.username, 32);
        const storyUserName = this._sanitizeHTML(story.full_name || story.username || 'User');
        let storyBackgroundHTML = '';
        const defaultStoryBg = 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';

        switch (story.content_type) {
            case 'image': case 'video':
                const mediaUrl = story.final_media_url || defaultStoryBg;
                storyBackgroundHTML = `<img src="${this._sanitizeHTML(mediaUrl)}" alt="Story by ${storyUserName}" class="w-full h-full object-cover">`;
                if (story.content_type === 'video' && story.final_media_url) {
                    storyBackgroundHTML += `<div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-20"><i class="fas fa-play text-white text-3xl opacity-75"></i></div>`;
                }
                break;
            case 'text_only':
                const bgColor = this._sanitizeHTML(story.background_color || '#3B82F6');
                const font = this._sanitizeHTML(story.font_family || 'Arial, sans-serif');
                const textContent = this._sanitizeHTML(story.text_overlay || '');
                storyBackgroundHTML = `<div class="w-full h-full flex items-center justify-center p-3 text-center overflow-hidden" style="background-color: ${bgColor};"><p class="text-white text-md font-semibold leading-tight" style="font-family: ${font}; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden;">${textContent || 'Tap to view'}</p></div>`;
                break;
            case 'code_snippet':
                 const codeBg = this._sanitizeHTML(story.background_color || '#1F2937');
                 const codeLang = this._sanitizeHTML(story.code_language || 'Code');
                 const codeContent = this._sanitizeHTML(story.code_content || '');
                 const codeTitle = this._sanitizeHTML(story.text_overlay || '');
                 storyBackgroundHTML = `<div class="w-full h-full flex flex-col justify-between p-2 overflow-hidden" style="background-color: ${codeBg};">${codeTitle ? `<div class="mb-1"><p class="text-xs text-gray-200 truncate">${codeTitle}</p></div>` : `<div><p class="text-xs text-gray-300 mb-1 truncate">${codeLang}</p></div>`}<pre class="text-xs text-gray-100 whitespace-pre-wrap overflow-hidden text-ellipsis flex-grow max-h-[60%] leading-snug"><code>${this._sanitizeHTML(codeContent || '// Tap to view code')}</code></pre>${story.theme_category ? `<div class="mt-auto"><p class="text-xxs text-gray-400 mt-1 truncate">${this._sanitizeHTML(story.theme_category)}</p></div>` : ''}</div>`;
                break;
            default: storyBackgroundHTML = `<div class="w-full h-full flex items-center justify-center"><span class="text-gray-500 text-xs">Preview N/A</span></div>`;
        }
        storyDiv.innerHTML = `<div class="absolute inset-0">${storyBackgroundHTML}</div><div class="absolute top-0 left-0 right-0 h-1/2 bg-gradient-to-b from-black/50 to-transparent opacity-70"></div><div class="absolute top-2 left-2 w-7 h-7 md:w-8 md:h-8 rounded-full border-2 border-blue-500 bg-white dark:bg-dark-700 p-0.5"><img src="${storyUserAvatar}" alt="${storyUserName}" class="w-full h-full rounded-full object-cover"></div><div class="absolute bottom-0 left-0 right-0 p-2 text-white bg-gradient-to-t from-black/60 to-transparent"><p class="font-semibold text-xs md:text-sm truncate leading-tight">${storyUserName}</p>${story.theme_category && story.content_type !== 'code_snippet' ? `<p class="text-xxs md:text-xs opacity-80 truncate leading-tight">${this._sanitizeHTML(story.theme_category)}</p>` : ''}</div>`;

        if (this.currentUser && story.user_id && parseInt(story.user_id, 10) === parseInt(this.currentUser.id, 10)) {
            const deleteButton = document.createElement('button');
            deleteButton.className = `absolute top-1.5 right-1.5 z-20 p-1 rounded-full w-6 h-6 flex items-center justify-center text-sm leading-none bg-transparent text-white opacity-75 hover:bg-red-600 hover:text-white hover:opacity-100 focus:bg-red-600 focus:text-white focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-red-500 story-delete-button transition-all duration-200 ease-in-out`;
            deleteButton.innerHTML = '<i class="fas fa-times"></i>';
            deleteButton.setAttribute('aria-label', 'Delete this story');
            deleteButton.setAttribute('title', 'Delete story');

            deleteButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleDeleteStory(story.id, storyDiv);
            });
            storyDiv.appendChild(deleteButton);
        }

        storyDiv.addEventListener('click', () => this.openStoryViewer(story));
        storyDiv.addEventListener('keypress', (e) => { if (e.key === 'Enter' || e.key === ' ') this.openStoryViewer(story); });
        return storyDiv;
    }

    async handleDeleteStory(storyId, storyElement) {
        if (!confirm('Are you sure you want to delete this story? This action cannot be undone.')) {
            return;
        }

        const deleteButton = storyElement.querySelector('.story-delete-button');
        let originalButtonContent = '';
        if (deleteButton) {
            originalButtonContent = deleteButton.innerHTML;
            deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteButton.disabled = true;
        }

        // ============ CSRF CHANGE 2: Add CSRF token to story deletion ============
        const csrfToken = window.getCsrfToken();
        if (!csrfToken) {
            alert('Security token missing. Please refresh the page and try again.');
            if (deleteButton) {
                deleteButton.innerHTML = originalButtonContent;
                deleteButton.disabled = false;
            }
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        // ======================= END OF CSRF CHANGE =======================

        try {
            const response = await fetch(`/post/stories/delete/${storyId}`, {
                method: 'POST',
                body: formData, // Send the token in the body
                headers: {
                    'Accept': 'application/json'
                }
            });
            const result = await response.json();

            if (response.ok && result.success) {
                storyElement.remove();
                this.checkScrollButtonsVisibility();
                
                if (this.currentStoryData && this.currentStoryData.id === storyId) {
                    this.closeStoryViewer();
                }
                console.log(result.message || 'Story deleted successfully.');
            } else {
                alert(`Failed to delete story: ${result.message || 'Unknown server error'}`);
                if (deleteButton) {
                    deleteButton.innerHTML = originalButtonContent;
                    deleteButton.disabled = false;
                }
            }
        } catch (error) {
            console.error('Error deleting story:', error);
            alert('A network error occurred while trying to delete the story. Please try again.');
            if (deleteButton) {
                deleteButton.innerHTML = originalButtonContent;
                deleteButton.disabled = false;
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // ============ CSRF CHANGE 3: Define global CSRF helper on DOM ready ============
    if (!window.getCsrfToken) {
        window.getCsrfToken = () => {
            const tokenElement = document.querySelector('meta[name="csrf-token"]');
            if (!tokenElement) {
                console.error('CSRF token meta tag not found. Please ensure your HTML includes: <meta name="csrf-token" content="...">');
                return null;
            }
            return tokenElement.getAttribute('content');
        };
        console.log("stories.js: CSRF token helper function initialized.");
    }
    // ======================= END OF CSRF CHANGE =======================

    if (typeof currentUserData !== 'undefined' && currentUserData.id) {
        new SaiStories('.stories-section-wrapper', currentUserData);
    } else {
        console.warn('Current user data not found for stories. Create story functionality will be limited/disabled.');
        new SaiStories('.stories-section-wrapper', { id: null, fullName: 'Guest', username: 'guest', profilePicture: null });
        const createBtnStatic = document.getElementById('create-story-button-static');
        if (createBtnStatic && (!currentUserData || !currentUserData.id)) createBtnStatic.style.display = 'none';
    }
});