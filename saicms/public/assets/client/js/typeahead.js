document.addEventListener('DOMContentLoaded', () => {
    // Check for the gameConfig object and its isAdmin property before running any code.
    if (typeof gameConfig === 'undefined' || !gameConfig.isAdmin) {
        return;
    }

    // --- UI ELEMENTS ---
    const searchInput = document.getElementById('admin-user-search');
    const resultsContainer = document.getElementById('typeahead-results');
    let debounceTimer;

    // --- GLOBAL AVATAR GENERATION FUNCTION ---
    /**
     * Creates a circular SVG avatar with a user's initials and makes it globally available.
     * This function can be called by other scripts, like typing.js.
     * @param {string} name The user's full name.
     * @param {number} [size=40] The width and height of the avatar.
     * @returns {string} A base64 encoded data URL for the SVG image.
     */
    window.createInitialAvatar = function(name, size = 40) {
        if (!name || name.trim() === '') name = 'User';
        const parts = name.trim().split(' ');
        let initials = (parts[0][0] || '');
        if (parts.length > 1 && parts[parts.length - 1]) {
            initials += parts[parts.length - 1][0];
        }
        initials = initials.toUpperCase();
        const svgString = `
            <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
                <circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="#4B5563"/>
                <text x="50%" y="50%" text-anchor="middle" dy=".35em"
                      font-family="Arial, sans-serif" font-size="${size * 0.4}"
                      fill="#ffffff">${initials}</text>
            </svg>`;
        return 'data:image/svg+xml;base64,' + btoa(svgString);
    }

    // --- HELPER FUNCTIONS ---
    const debounce = (func, delay) => {
        return function(...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func.apply(this, args), delay);
        };
    };

    // --- CORE LOGIC ---
    const fetchUsers = async (query) => {
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.classList.add('hidden');
            return;
        }
        try {
            const response = await fetch(`/admin/search-users?query=${encodeURIComponent(query)}`);
            if (!response.ok) throw new Error('Network response was not ok.');
            const users = await response.json();
            displayResults(users);
        } catch (error) {
            console.error("Failed to fetch users:", error);
            resultsContainer.innerHTML = '<div class="p-3 text-red-500">Error loading users.</div>';
            resultsContainer.classList.remove('hidden');
        }
    };

    const displayResults = (users) => {
        if (users.length === 0) {
            resultsContainer.innerHTML = '<div class="p-3 text-gray-400">No users found.</div>';
        } else {
            resultsContainer.innerHTML = users.map(user => {
                // Use the globally defined function for consistency
                const avatarSrc = user.profile_picture || window.createInitialAvatar(user.full_name);
                return `
                    <div class="typeahead-item" data-user='${JSON.stringify(user)}'>
                        <img src="${avatarSrc}" alt="${user.full_name}">
                        <div class="typeahead-info">
                            <strong>${user.full_name}</strong>
                            <small>${user.email}</small>
                        </div>
                    </div>
                `;
            }).join('');
        }
        resultsContainer.classList.remove('hidden');
    };

    // --- EVENT LISTENERS ---
    searchInput.addEventListener('focus', () => {
        if (window.pauseGame) window.pauseGame();
    });

    searchInput.addEventListener('blur', () => {
        // A small delay allows a click on a result item to be processed before resuming.
        setTimeout(() => {
            if (window.resumeGame) window.resumeGame();
        }, 150);
    });

    searchInput.addEventListener('input', debounce((e) => {
        fetchUsers(e.target.value);
    }, 300));

    resultsContainer.addEventListener('click', (e) => {
        const item = e.target.closest('.typeahead-item');
        if (item) {
            const userData = JSON.parse(item.dataset.user);
            if (window.openAdminModal) {
                window.openAdminModal(userData); 
            }
            resultsContainer.classList.add('hidden');
            searchInput.value = '';
        }
    });

    document.addEventListener('click', (e) => {
        if (!resultsContainer.contains(e.target) && !searchInput.contains(e.target)) {
            resultsContainer.classList.add('hidden');
        }
    });
});