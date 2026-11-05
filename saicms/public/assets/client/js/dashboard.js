    (function(window, document, undefined) {
        'use strict';

        class ProfileApp {
            constructor() {
                this.MAX_CHATBOXES = 3;
                this.activeChats = []; // Stores { id: chatId, user: userName, element: chatboxDOM }
                this.typingTimers = {};
                this.currentContextMenu = null;
                this.mockUsersSearch = [
                    { name: 'John Doe', img: 'https://randomuser.me/api/portraits/men/32.jpg' },
                    { name: 'Jane Smith', img: 'https://randomuser.me/api/portraits/women/44.jpg' },
                    { name: 'Mike Johnson', img: 'https://randomuser.me/api/portraits/men/75.jpg' },
                    { name: 'Sarah Williams', img: 'https://randomuser.me/api/portraits/women/33.jpg' },
                    { name: 'David Brown', img: 'https://randomuser.me/api/portraits/men/22.jpg' },
                    { name: 'Dashboard Project', type: 'Project', icon: 'fa-project-diagram' },
                    { name: 'Mobile App UI', type: 'Component', icon: 'fa-puzzle-piece' },
                    { name: 'Settings Page', type: 'Page', icon: 'fa-cog' }
                ];

                this._cacheDOMElements();
                this._bindEvents();
                this._initDropdowns();
                this._initSearch();
                this._initDarkMode(); // Will respect initial class on <html>
                this._initChatSystem();
                 // Mobile menu button connection
                if (this.mobileMenuBtn && this.userMenuBtn) {
                    this.mobileMenuBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.userMenuBtn.click(); // Simulate click on the main user menu button
                    });
                }
            }

            _cacheDOMElements() {
                this.notificationBtn = document.getElementById('notificationBtn');
                this.notificationDropdownEl = document.getElementById('notificationDropdown');
                this.messagesBtn = document.getElementById('messagesBtn');
                this.messagesDropdownEl = document.getElementById('messagesDropdown');
                this.userMenuBtn = document.getElementById('userMenuBtn');
                this.userDropdownEl = document.getElementById('userDropdown');
                this.allDropdowns = [this.notificationDropdownEl, this.messagesDropdownEl, this.userDropdownEl].filter(el => el); // Filter out nulls if elements don't exist
                
                this.searchInputEl = document.getElementById('searchInput');
                this.searchDropdownEl = document.getElementById('searchDropdown');
                if(this.searchDropdownEl) { // Check if searchDropdownEl exists
                    this.searchResultsEl = this.searchDropdownEl.querySelector('.search-results');
                } else {
                    this.searchResultsEl = null;
                }
                
                this.darkModeToggleBtn = document.getElementById('darkModeToggle');
                if(this.darkModeToggleBtn) { // Check if darkModeToggleBtn exists
                    this.lightModeTextEl = this.darkModeToggleBtn.querySelector('.light-mode-text');
                    this.darkModeTextEl = this.darkModeToggleBtn.querySelector('.dark-mode-text');
                } else {
                    this.lightModeTextEl = null;
                    this.darkModeTextEl = null;
                }

                this.chatboxContainerEl = document.getElementById('chatboxContainer');
                this.messageItemLinks = document.querySelectorAll('.message-item');
                this.mobileMenuBtn = document.getElementById('mobileMenuBtn');
            }

            _bindEvents() {
                document.addEventListener('click', (e) => {
                    if (this.notificationDropdownEl && this.notificationBtn && !this.notificationBtn.contains(e.target) && !this.notificationDropdownEl.contains(e.target)) {
                        this.notificationDropdownEl.classList.add('hidden');
                    }
                    if (this.messagesDropdownEl && this.messagesBtn && !this.messagesBtn.contains(e.target) && !this.messagesDropdownEl.contains(e.target)) {
                        this.messagesDropdownEl.classList.add('hidden');
                    }
                    if (this.userDropdownEl && this.userMenuBtn && !this.userMenuBtn.contains(e.target) && !this.userDropdownEl.contains(e.target)) {
                        this.userDropdownEl.classList.add('hidden');
                    }
                    if (this.searchInputEl && this.searchDropdownEl && !this.searchInputEl.contains(e.target) && !this.searchDropdownEl.contains(e.target)) {
                        this.searchDropdownEl.classList.add('hidden');
                    }
                    if (this.currentContextMenu && !this.currentContextMenu.contains(e.target)) {
                        this._closeChatMessageContextMenu();
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.allDropdowns.forEach(dd => dd.classList.add('hidden'));
                        if (this.searchDropdownEl) this.searchDropdownEl.classList.add('hidden');
                        if (this.currentContextMenu) this._closeChatMessageContextMenu();
                    }
                });
            }

            _initDropdowns() {
                const setupDropdownToggle = (btn, dropdown, ...otherDropdowns) => {
                    if (btn && dropdown) {
                        btn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            dropdown.classList.toggle('hidden');
                            otherDropdowns.forEach(od => { if(od) od.classList.add('hidden'); });
                        });
                    }
                };
                setupDropdownToggle(this.notificationBtn, this.notificationDropdownEl, this.messagesDropdownEl, this.userDropdownEl);
                setupDropdownToggle(this.messagesBtn, this.messagesDropdownEl, this.notificationDropdownEl, this.userDropdownEl);
                setupDropdownToggle(this.userMenuBtn, this.userDropdownEl, this.notificationDropdownEl, this.messagesDropdownEl);
            }

            _initSearch() {
                if (this.searchInputEl && this.searchDropdownEl && this.searchResultsEl) {
                    this.searchInputEl.addEventListener('input', () => this._handleSearchInput());
                    this.searchInputEl.addEventListener('focus', () => {
                         this.searchDropdownEl.classList.remove('hidden'); // Show on focus always
                         this._handleSearchInput(); // Populate with recents or results
                    });
                }
            }
            
            _handleSearchInput() {
                const query = this.searchInputEl.value.toLowerCase();
                this.searchDropdownEl.classList.remove('hidden');

                if (query.length === 0) { // Show recent searches if query is empty
                    const recentSearches = this.mockUsersSearch.slice(0, 3); // Example: show first 3 as "recent"
                    this._displaySearchResults(recentSearches, "", true); // true for recent
                    return;
                }

                if (query.length < 1 && query.length !==0) { // Min 1 char for actual search, but 0 for recents
                    this.searchResultsEl.innerHTML = '<div class="p-3 text-gray-500 dark:text-gray-400 text-sm">Type to search...</div>';
                    return;
                }

                const filteredItems = this.mockUsersSearch.filter(item => 
                    item.name.toLowerCase().includes(query) || (item.type && item.type.toLowerCase().includes(query))
                );
                this._displaySearchResults(filteredItems, query, false); // false for actual search results
            }
            
            _displaySearchResults(items, query, isRecent = false) {
                this.searchResultsEl.innerHTML = '';
                const recentSearchesTitle = this.searchDropdownEl.querySelector('.p-2.text-gray-500');
                if (recentSearchesTitle) {
                    recentSearchesTitle.textContent = isRecent ? "Recent searches" : "Search results";
                }


                if (items.length === 0 && !isRecent) { // Only show "No results" for actual searches
                    this.searchResultsEl.innerHTML = '<div class="p-3 text-gray-500 dark:text-gray-400">No results found</div>';
                    return;
                }
                if (items.length === 0 && isRecent) { // Don't show "No results" if there are no recents, just empty
                     this.searchResultsEl.innerHTML = '<div class="p-3 text-gray-500 dark:text-gray-400 text-sm">No recent searches.</div>';
                    return;
                }

                items.forEach(item => {
                    const itemElement = document.createElement('a');
                    itemElement.href = '/user/' + encodeURIComponent(item.name.toLowerCase().replace(/\s+/g, '-')); // Replace with actual link if available
                    itemElement.className = 'flex items-center p-3 hover:bg-gray-100 dark:hover:bg-dark-600';
                    
                    const name = item.name;
                    let highlightedName = this._sanitizeHTML(name);
                    if (!isRecent && query) { // Only highlight for actual search query
                        const matchIndex = name.toLowerCase().indexOf(query);
                        if (matchIndex !== -1) {
                            const beforeMatch = name.substring(0, matchIndex);
                            const matchText = name.substring(matchIndex, matchIndex + query.length);
                            const afterMatch = name.substring(matchIndex + query.length);
                            highlightedName = `${this._sanitizeHTML(beforeMatch)}<span class="highlight">${this._sanitizeHTML(matchText)}</span>${this._sanitizeHTML(afterMatch)}`;
                        }
                    }
                    
                    const iconHtml = item.img ? `<img src="${item.img}" alt="${this._sanitizeHTML(item.name)}" class="w-8 h-8 rounded-full">` : 
                                     item.icon ? `<i class="fas ${item.icon} text-gray-500 dark:text-gray-400 w-8 h-8 flex items-center justify-center text-xl"></i>` :
                                     '<div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-dark-500"></div>'; // Placeholder
                    
                    itemElement.innerHTML = `
                        ${iconHtml}
                        <div class="ml-3">
                            <div class="dark:text-white">${highlightedName}</div>
                            ${item.type ? `<div class="text-xs text-gray-500 dark:text-gray-400">${this._sanitizeHTML(item.type)}</div>` : ''}
                        </div>
                    `;
                    this.searchResultsEl.appendChild(itemElement);
                });
            }
            
            _sanitizeHTML(str) {
                const temp = document.createElement('div');
                temp.textContent = str;
                return temp.innerHTML;
            }

            _initDarkMode() {
                // Apply based on existing class or localStorage or prefers-color-scheme
                this._applyDarkModePreference(); 
                if (this.darkModeToggleBtn) {
                    this.darkModeToggleBtn.addEventListener('click', () => this._toggleDarkMode());
                }
            }
            
            _applyDarkModePreference() {
                const isDarkInitially = document.documentElement.classList.contains('dark');
                let useDark = isDarkInitially;

                if (localStorage.getItem('darkMode') === 'true') {
                    useDark = true;
                } else if (localStorage.getItem('darkMode') === 'false') {
                    useDark = false;
                } else { // No localStorage, rely on initial class or prefers-color-scheme
                   useDark = isDarkInitially || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
                }

                if (useDark) {
                    document.documentElement.classList.add('dark');
                    if (this.lightModeTextEl) this.lightModeTextEl.classList.add('hidden');
                    if (this.darkModeTextEl) this.darkModeTextEl.classList.remove('hidden');
                } else {
                    document.documentElement.classList.remove('dark');
                    if (this.lightModeTextEl) this.lightModeTextEl.classList.remove('hidden');
                    if (this.darkModeTextEl) this.darkModeTextEl.classList.add('hidden');
                }
            }
            
            _toggleDarkMode() {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('darkMode', isDark ? 'true' : 'false');
                this._applyDarkModePreference();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            window.GlobalProfileApp = new ProfileApp(); // Make it globally accessible if needed for debugging or other scripts
        });

    })(window, document);

        document.addEventListener('DOMContentLoaded', function() {
            // Usage Chart
            const usageCtx = document.getElementById('usageChart').getContext('2d');
            const usageChart = new Chart(usageCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Components Used',
                        data: [320, 450, 380, 510, 620, 750, 890],
                        backgroundColor: document.documentElement.classList.contains('dark') ? 'rgba(59, 130, 246, 0.2)' : 'rgba(99, 102, 241, 0.1)', // Adjusted for dark mode
                        borderColor: document.documentElement.classList.contains('dark') ? 'rgba(59, 130, 246, 1)' : 'rgba(99, 102, 241, 1)', // Adjusted for dark mode
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: document.documentElement.classList.contains('dark') ? 'rgba(59, 130, 246, 1)' : 'rgba(99, 102, 241, 1)',
                        pointBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { 
                                drawBorder: false,
                                color: document.documentElement.classList.contains('dark') ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
                            },
                            ticks: { color: document.documentElement.classList.contains('dark') ? '#A0A3A8' : '#6B7280' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: document.documentElement.classList.contains('dark') ? '#A0A3A8' : '#6B7280' }
                        }
                    }
                }
            });

            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Pending', 'On Hold'],
                    datasets: [{
                        data: [35, 25, 20, 20],
                        backgroundColor: [
                            'rgba(16, 185, 129, 1)', // green
                            'rgba(59, 130, 246, 1)',  // blue
                            'rgba(245, 158, 11, 1)', // yellow
                            'rgba(239, 68, 68, 1)'   // red
                        ],
                        borderWidth: 2, // Added for better separation
                        borderColor: document.documentElement.classList.contains('dark') ? '#2D2D2D' : '#fff' // Background color for border effect
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20,
                                color: document.documentElement.classList.contains('dark') ? '#E4E6EB' : '#050505'
                            }
                        }
                    }
                }
            });
            
            // Update chart colors on dark mode toggle by ProfileApp
            const darkModeObserver = new MutationObserver((mutationsList) => {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const isDark = document.documentElement.classList.contains('dark');
                        // Update Usage Chart
                        usageChart.data.datasets[0].backgroundColor = isDark ? 'rgba(59, 130, 246, 0.2)' : 'rgba(99, 102, 241, 0.1)';
                        usageChart.data.datasets[0].borderColor = isDark ? 'rgba(59, 130, 246, 1)' : 'rgba(99, 102, 241, 1)';
                        usageChart.data.datasets[0].pointBackgroundColor = isDark ? 'rgba(59, 130, 246, 1)' : 'rgba(99, 102, 241, 1)';
                        usageChart.options.scales.y.grid.color = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
                        usageChart.options.scales.y.ticks.color = isDark ? '#A0A3A8' : '#6B7280';
                        usageChart.options.scales.x.ticks.color = isDark ? '#A0A3A8' : '#6B7280';
                        usageChart.update();

                        // Update Status Chart
                        statusChart.data.datasets[0].borderColor = isDark ? '#2D2D2D' : '#fff';
                        statusChart.options.plugins.legend.labels.color = isDark ? '#E4E6EB' : '#050505';
                        statusChart.update();
                    }
                }
            });
            darkModeObserver.observe(document.documentElement, { attributes: true });


            // Card hover effect (already styled with CSS, this JS might be redundant but kept if complex logic was intended)
            const cards = document.querySelectorAll('.card-hover');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => { /* card.style.transform = 'translateY(-5px)'; CSS handles this */ });
                card.addEventListener('mouseleave', () => { /* card.style.transform = 'translateY(0)'; CSS handles this */ });
            });

            // Sidebar icon animation (already styled with CSS, this JS might be redundant)
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            sidebarItems.forEach(item => {
                const icon = item.querySelector('.sidebar-icon');
                if (icon) {
                    item.addEventListener('mouseenter', () => { /* icon.style.transform = 'scale(1.2)'; CSS handles this */ });
                    item.addEventListener('mouseleave', () => { /* icon.style.transform = 'scale(1)'; CSS handles this */ });
                }
            });
        });


document.addEventListener('DOMContentLoaded', function() {
    // --- NEW: Grow Network Modal Logic ---
    const growBtn = document.getElementById('growBtn');
    const openBtn = document.getElementById('openReferralModalBtn');
    const closeBtn = document.getElementById('closeReferralModalBtn');
    const modal = document.getElementById('referralModal');
    const modalContent = document.getElementById('referralModalContent');
    const copyBtn = document.getElementById('copyModalLinkBtn');
    const linkInput = document.getElementById('referralModalLinkInput');

    // Function to open the modal
    const openModal = () => {
        if (!modal || !modalContent) return;
        modal.classList.remove('hidden');
        // Triggering transitions for a smooth fade-in and scale-up effect
        setTimeout(() => {https://smartfed.ai/profile/4
            modal.style.opacity = '1';
            modalContent.style.opacity = '1';
            modalContent.style.transform = 'scale(1)';
        }, 10); // A tiny delay to allow CSS to catch up
    };

    // Function to close the modal
    const closeModal = () => {
        if (!modal || !modalContent) return;
        modalContent.style.opacity = '0';
        modalContent.style.transform = 'scale(0.95)';
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300); // Wait for the transition to finish
    };

    if (growBtn) {
        growBtn.addEventListener('click', openModal);
    }

    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    // Close modal if user clicks outside the content area
    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    // Close modal on 'Escape' key press
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    // Logic for the copy button inside the modal
    if (copyBtn && linkInput) {
        copyBtn.addEventListener('click', () => {
            linkInput.select();
            document.execCommand('copy');
            
            const originalText = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            copyBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            copyBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            
            setTimeout(() => {
                copyBtn.textContent = originalText;
                copyBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                copyBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 2000);
        });
    }


        // --- 3. Initialize the Chat System ---
        // This was previously in your chat-manager file, but it's clearer here.
        // It ensures that everything is initialized after the DOM is ready.
        const chatContainerElId = 'chatboxContainer';
        if (document.getElementById(chatContainerElId)) {
            // Instantiate the ChatUIManager
            const chatManager = new ChatUIManager(chatContainerElId);
            window.globalChatManager = chatManager; // Make it globally accessible

            // Set user details for the chat manager
            if (window.currentUserData && window.currentUserData.id) {
                chatManager.setCurrentUserDetails(
                    window.currentUserData.id,
                    window.currentUserData.fullName,
                    window.currentUserData.avatar
                );
            } else {
                chatManager.setCurrentUserDetails(null, 'Guest', null);
            }

            // Initialize the UserTypeahead for search, passing it the chatManager instance
            new UserTypeahead('searchInput', 'searchDropdown', 'searchResults', chatManager, null);

            // Initialize the ChatNotificationManager
            window.globalChatNotificationManager = new ChatNotificationManager(
                'chatNotificationList',
                'chatNotificationEmptyState',
                'globalChatUnreadBadge',
                chatManager
            );

            // Load initial chat notifications if the user is logged in
            if (chatManager.currentUserId) {
                window.globalChatNotificationManager.loadInitialNotifications();
            } else {
                window.globalChatNotificationManager._toggleEmptyState();
            }
        } else {
            console.warn(`Chat System: Container #${chatContainerElId} not found. Chat UI not initialized.`);
        }


        // --- NEW: Referral Link Copy ---
        const linkBtn = document.getElementById('copyLinkBtn');
        if (linkBtn) {
            linkBtn.addEventListener('click', () => {
                const linkInput = document.getElementById('referralLinkInput');
                linkInput.select();
                document.execCommand('copy');
                
                // Visual feedback
                const originalText = linkBtntextContent;
                linkBtn.textContent = 'Copied!';
                linkBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                linkBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                
                setTimeout(() => {
                    linkBtn.textContent = originalText;
                    linkBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    linkBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            });
        }


    // --- The rest of your dashboard JS (Charts, etc.) follows here ---
    // const usageCtx = ...
});