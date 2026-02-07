<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="68b861a77ef73ec6386b72eb2046fddddc5d1037dfb6af363407e50813423e13">
        <link href="/assets/favicon/favicon.ico" rel="shortcut icon" type="image/x-icon" />
    <title>Dashboard - Sai</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        facebook: {
                            DEFAULT: '#1877F2',
                            dark: '#0B64C9',
                            light: '#E7F3FF'
                        },
                        dark: { // Custom dark theme palette colors
                            800: '#1E1E1E',
                            700: '#2D2D2D',
                            600: '#3A3A3A',
                            500: '#4A4A4A'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
    <style>
        /* Combined and Harmonized Custom Styles */
        .gradient-bg { background: linear-gradient(135deg, #6b73ff 0%, #000dff 100%); }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .sidebar-item:hover .sidebar-icon { transform: scale(1.2); }
        /* .chart-container { height: 250px; } -- Removed as calculator is gone */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        .dark ::-webkit-scrollbar-track { background: #2D2D2D; }
        .dark ::-webkit-scrollbar-thumb { background: #555; }
        .dark ::-webkit-scrollbar-thumb:hover { background: #777; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        /* Styles from new Marketplace content */
        .product-card:hover .product-actions {
            opacity: 1;
            transform: translateY(0);
        }
        .product-actions {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        .skeleton-loader {
            animation: pulse 2s infinite ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark-800 font-sans transition-colors duration-200">
    <!-- Fixed Header from Profile Page -->
<header class="bg-white dark:bg-dark-700 shadow-sm fixed top-0 left-0 right-0 z-30">
    <div class="max-w-6xl mx-auto px-4 py-2 flex items-center justify-between">
        <!-- Logo and Search -->
        <!-- MODIFIED: Added flex-grow md:flex-grow-0 for mobile expansion, and md:space-x-2 to apply spacing only on md+ -->
        <div class="flex items-center flex-grow md:flex-grow-0 md:space-x-2">
            <!-- MODIFIED: Added hidden md:block to hide logo on mobile -->
            <a href="/" class="hidden md:block text-facebook text-3xl font-bold" aria-label="Sai Home">
                <img src="/assets/favicon/apple-touch-icon.png" alt="Site Logo" class="w-8 h-8 rounded-md">
            </a>
            <!-- MODIFIED: Removed 'hidden md:block', added 'w-full md:w-auto' to make search visible and responsive -->
            <div class="relative w-full md:w-auto">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Search Sai"
                        class="search-input bg-gray-100 dark:bg-dark-600 dark:text-white rounded-full py-2 px-4 pl-10 w-full md:w-64 focus:outline-none"
                        aria-label="Search Sai">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-500 dark:text-gray-400"></i>
                    <div id="searchDropdown" class="search-dropdown absolute hidden w-full mt-1 bg-white dark:bg-dark-700 rounded-lg shadow-lg z-50">
                        <div id="searchResults" class="search-results max-h-80 overflow-y-auto scrollbar-hide">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

        <!-- Main Navigation (Static) -->
        <!-- MODIFIED: Added 'hidden md:flex' to hide nav on mobile -->
        <nav class="hidden md:flex space-x-1 md:space-x-2">
                                            <a href="/" class="p-3 md:px-6 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg" aria-label="Home">
                    <i class="fas fa-home text-xl"></i>
                </a>
                                            <a href="/dashboard" class="p-3 md:px-6 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg" aria-label="Watch">
                    <i class="fas fa-tv text-xl"></i>
                </a>
                                            <a href="/marketplace" class="p-3 md:px-6 text-facebook dark:text-facebook-light bg-facebook-light dark:bg-dark-600 rounded-lg" aria-label="Marketplace">
                    <i class="fas fa-store text-xl"></i>
                </a>
                                            <a href="/groups" class="p-3 md:px-6 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg" aria-label="Groups">
                    <i class="fas fa-users text-xl"></i>
                </a>
                                            <a href="/gaming" class="p-3 md:px-6 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg" aria-label="Gaming">
                    <i class="fas fa-gamepad text-xl"></i>
                </a>
                    </nav>
        
        <!-- User Menu - Placeholders will be filled by JS -->
        <div class="flex items-center space-x-2">
            <div class="relative">
                <button id="notificationBtn" class="p-2 bg-gray-200 dark:bg-dark-600 rounded-full hover:bg-gray-300 dark:hover:bg-dark-500 relative" aria-label="Notifications">
                    <i class="fas fa-bell text-gray-700 dark:text-gray-300"></i>
                    <!-- Unread count badge will be inserted here by JS if needed -->
                    <span id="notificationUnreadBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-4 h-4 rounded-full flex items-center justify-center hidden"></span>
                </button>
                <div id="notificationDropdown" class="notification-dropdown hidden absolute right-0 mt-2 w-72 md:w-80 bg-white dark:bg-dark-700 rounded-lg shadow-xl z-50">
                    <div class="p-3 border-b dark:border-dark-600 flex justify-between items-center">
                        <h3 class="font-semibold dark:text-white">Notifications</h3>
                        <button id="markAllNotificationsReadBtn" class="text-facebook text-sm hover:underline hidden">Mark all as read</button>
                    </div>
                    <div id="notificationListContainer" class="max-h-80 overflow-y-auto">
                        <div id="notificationEmptyState" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400 hidden">
                            No notifications yet.
                        </div>
                        <div id="notificationLoadingState" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading notifications...
                        </div>
                        <!-- Notification items are dynamically inserted here -->
                    </div>
                    <!-- **** ADD THIS ELEMENT **** -->
                    <div id="notificationLoadMoreSpinner" class="hidden text-center p-2">
                        <i class="fas fa-spinner fa-spin text-gray-500 dark:text-gray-400"></i>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-1">Loading more...</span>
                    </div>
                    <!-- ************************** -->
                    <div id="notificationViewAllContainer" class="p-2 text-center border-t dark:border-dark-600 hidden">
                        <a href="/notifications" class="text-facebook text-sm hover:underline">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <div class="relative">
                <button id="chatNotificationsBtn" class="p-2 bg-gray-200 dark:bg-dark-600 rounded-full hover:bg-gray-300 dark:hover:bg-dark-500" aria-label="Chats">
                    <i class="fas fa-comment-dots text-gray-700 dark:text-gray-300"></i>
                    <span id="globalChatUnreadBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-4 h-4 rounded-full flex items-center justify-center hidden">0</span>
                </button>
                <div id="chatNotificationsDropdown" class="chat-notifications-dropdown hidden absolute right-0 mt-2 w-72 md:w-80 bg-white dark:bg-dark-700 rounded-lg shadow-xl z-50">
                    <div class="p-3 border-b dark:border-dark-600 flex justify-between items-center">
                        <h3 class="font-semibold dark:text-white">Chats</h3>
                    </div>
                    <div id="chatNotificationList" class="max-h-80 overflow-y-auto scrollbar-hide">
                         <!-- Populated by chatmanager.js -->
                    </div>
                    <div id="chatNotificationEmptyState" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400 hidden">
                        No active chats.
                    </div>
                </div>
            </div>
            
            <!-- MODIFIED: Added 'hidden md:block' to hide profile button container on mobile -->
            <div class="relative"> 
                <button id="userMenuBtn" class="flex items-center space-x-1 bg-gray-200 dark:bg-dark-600 rounded-full p-1 hover:bg-gray-300 dark:hover:bg-dark-500" aria-label="Account">
                    <!-- User avatar placeholder -->
                    <img id="userMenuAvatar" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIiB3aWR0aD0iMzIiIGhlaWdodD0iMzIiPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjY2NjIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MiUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSI1MCIgZmlsbD0iIzY2NiI+PzwvdGV4dD48L3N2Zz4=" alt="Profile" class="w-8 h-8 rounded-full">
                </button>
                <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-dark-700 rounded-md shadow-lg py-1 hidden z-50">
                    <a href="/" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Home</a>
                    <a href="/sai/" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Sai</a>
                    <a id="userDropdownProfileLink" href="/profile" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Profile</a>
                    <a href="/dashboard" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Dashboard</a>
                    <a href="/smartfi" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">SmartFi</a>
                    <a href="#" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Settings</a>
                    <button id="darkModeToggle" class="block w-full text-left px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">
                        <span class="light-mode-text">Dark Mode</span>
                        <span class="dark-mode-text hidden">Light Mode</span>
                    </button>
                    <a href="/logout" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- Chatbox Container -->
<div id="chatboxContainer" class="chatbox-container">
    <!-- Chatboxes will be added here dynamically -->
</div>

<!-- Dashboard Content -->
<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-white dark:bg-dark-700 sidebar-container">
            <!-- Modified Sidebar for Marketplace -->
            <div class="flex flex-col flex-grow p-4 overflow-y-auto scrollbar-hide mt-[60px]">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white px-2 mb-4">Marketplace</h2>
                <nav class="flex-1 space-y-1">
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg bg-gray-100 dark:bg-dark-600 text-gray-800 dark:text-white font-semibold">
                        <i class="fas fa-store mr-3 w-5 text-center"></i>
                        <span>Browse All</span>
                    </a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600">
                        <i class="fas fa-bell mr-3 w-5 text-center"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600">
                        <i class="fas fa-inbox mr-3 w-5 text-center"></i>
                        <span>Inbox</span>
                    </a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600">
                        <i class="fas fa-shopping-cart mr-3 w-5 text-center"></i>
                        <span>Cart</span>
                    </a>
                     <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600">
                        <i class="fas fa-user-circle mr-3 w-5 text-center"></i>
                        <span>Your Account</span>
                    </a>
                    <button class="w-full mt-4 bg-facebook text-white py-2 px-4 rounded-lg font-medium hover:bg-facebook-dark transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>Create New Listing
                    </button>
                </nav>
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-dark-600">
                    <h3 class="px-3 text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Categories</h3>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600 text-sm">Fashion</a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600 text-sm">Electronics</a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600 text-sm">Home & Garden</a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600 text-sm">Beauty</a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600 text-sm">Toys</a>
                    <a href="#" class="flex items-center px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-600 text-sm">Sports</a>
                </div>
            </div>
        </div>
    </div>


    <!-- Main Content -->
    <div class="flex-1 overflow-auto scrollbar-hide mt-[60px]">
        <main class="p-4 sm:p-6 lg:p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div class="flex items-center space-x-2">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Today's Picks</h1>
                </div>
                <div class="hidden sm:flex items-center space-x-4">
                    <button class="p-2 rounded-full bg-gray-200 dark:bg-dark-600 text-gray-600 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-dark-500">
                        <i class="fas fa-filter"></i>
                    </button>
                    <button class="p-2 rounded-full bg-gray-200 dark:bg-dark-600 text-gray-600 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-dark-500">
                        <i class="fas fa-sort-amount-down"></i>
                    </button>
                </div>
            </header>
    
            <!-- Feed -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <!-- Product Card 1 -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-md overflow-hidden product-card relative">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                             alt="Running Shoes" class="w-full h-48 object-cover">
                        <div class="product-actions absolute bottom-2 right-2 flex space-x-2">
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-heart text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-share-alt text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                        <div class="absolute top-2 left-2 bg-facebook text-white text-xs px-2 py-1 rounded-full">
                            -20%
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <img src="https://i.pravatar.cc/150?u=sporty" class="w-6 h-6 rounded-full mr-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">SportyShoes</span>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Premium Running Shoes</h3>
                        <div class="flex items-center mb-2">
                            <div class="flex text-yellow-400 text-sm">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(128)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white">$89.99</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 line-through ml-2">$112.99</span>
                            </div>
                            <button class="text-facebook hover:text-facebook-dark">
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
    
                <!-- Product Card 2 -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-md overflow-hidden product-card relative">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                             alt="Smart Watch" class="w-full h-48 object-cover">
                        <div class="product-actions absolute bottom-2 right-2 flex space-x-2">
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-heart text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-share-alt text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <img src="https://i.pravatar.cc/150?u=tech" class="w-6 h-6 rounded-full mr-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">TechGadgets</span>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Smart Watch Pro</h3>
                        <div class="flex items-center mb-2">
                            <div class="flex text-yellow-400 text-sm">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(256)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div><span class="font-bold text-gray-900 dark:text-white">$199.99</span></div>
                            <button class="text-facebook hover:text-facebook-dark">
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
    
                <!-- Product Card 3 -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-md overflow-hidden product-card relative">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1551232864-3f0890e580d9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                             alt="Ceramic Mug" class="w-full h-48 object-cover">
                        <div class="product-actions absolute bottom-2 right-2 flex space-x-2">
                             <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-heart text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-share-alt text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                        <div class="absolute top-2 left-2 bg-facebook text-white text-xs px-2 py-1 rounded-full">New</div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <img src="https://i.pravatar.cc/150?u=home" class="w-6 h-6 rounded-full mr-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">HomeEssentials</span>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Artisan Ceramic Mug</h3>
                        <div class="flex items-center mb-2">
                            <div class="flex text-yellow-400 text-sm">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(42)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div><span class="font-bold text-gray-900 dark:text-white">$24.99</span></div>
                            <button class="text-facebook hover:text-facebook-dark">
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
    
                <!-- ... Repeat for other product cards, adding dark mode classes ... -->
                <!-- Product Card 4 -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-md overflow-hidden product-card relative">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1594035910387-fea47794261f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                             alt="Leather Bag" class="w-full h-48 object-cover">
                        <div class="product-actions absolute bottom-2 right-2 flex space-x-2">
                             <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-heart text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-share-alt text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <img src="https://i.pravatar.cc/150?u=urban" class="w-6 h-6 rounded-full mr-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">UrbanStyle</span>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Genuine Leather Backpack</h3>
                        <div class="flex items-center mb-2">
                            <div class="flex text-yellow-400 text-sm">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(87)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div><span class="font-bold text-gray-900 dark:text-white">$149.99</span></div>
                            <button class="text-facebook hover:text-facebook-dark">
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>

                 <!-- Product Card 5 -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-md overflow-hidden product-card relative">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                             alt="Camera" class="w-full h-48 object-cover">
                        <div class="product-actions absolute bottom-2 right-2 flex space-x-2">
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-heart text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-share-alt text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                        <div class="absolute top-2 left-2 bg-facebook text-white text-xs px-2 py-1 rounded-full">
                            Best Seller
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <img src="https://i.pravatar.cc/150?u=photopro" class="w-6 h-6 rounded-full mr-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">PhotoPro</span>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Vintage Polaroid Camera</h3>
                        <div class="flex items-center mb-2">
                            <div class="flex text-yellow-400 text-sm">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(312)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white">$79.99</span>
                            </div>
                            <button class="text-facebook hover:text-facebook-dark">
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Product Card 6 -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-md overflow-hidden product-card relative">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1572635196237-14b3f281503f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                             alt="Sunglasses" class="w-full h-48 object-cover">
                        <div class="product-actions absolute bottom-2 right-2 flex space-x-2">
                             <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-heart text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button class="w-8 h-8 bg-white dark:bg-dark-600 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-dark-500">
                                <i class="fas fa-share-alt text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                             <img src="https://i.pravatar.cc/150?u=sunshades" class="w-6 h-6 rounded-full mr-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">SunShades</span>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Designer Sunglasses</h3>
                        <div class="flex items-center mb-2">
                            <div class="flex text-yellow-400 text-sm">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(56)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white">$129.99</span>
                            </div>
                            <button class="text-facebook hover:text-facebook-dark">
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
    
            <!-- Load More Button -->
            <div class="flex justify-center mt-10">
                <button class="px-6 py-3 bg-facebook text-white rounded-full hover:bg-facebook-dark transition-colors">
                    Load More Products
                </button>
            </div>
    
        </main>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-dark-700 shadow-lg py-3 px-6 flex justify-around items-center md:hidden border-t border-gray-200 dark:border-dark-600">
    <a href="/" class="flex flex-col items-center text-gray-500 dark:text-gray-400">
        <i class="fas fa-home text-xl"></i>
        <span class="text-xs mt-1">Home</span>
    </a>
    <a href="/marketplace" class="flex flex-col items-center text-facebook dark:text-facebook-light">
        <i class="fas fa-store text-xl"></i>
        <span class="text-xs mt-1">Market</span>
    </a>
     <button class="flex flex-col items-center text-gray-500 dark:text-gray-400">
        <i class="fas fa-bell text-xl"></i>
        <span class="text-xs mt-1">Alerts</span>
    </button>
    <a href="/profile" class="flex flex-col items-center text-gray-500 dark:text-gray-400">
        <i class="fas fa-user text-xl"></i>
        <span class="text-xs mt-1">Profile</span>
    </a>
</div>

<!--Notifications Modal-->
<div id="notificationContentModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle" aria-hidden="true">
    <!--
        Removed: overflow-y-auto from the main overlay.
        The main overlay should just center the modal dialog.
    -->
    <div class="relative p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-xl rounded-lg bg-white dark:bg-dark-800 transform transition-all sm:my-8 flex flex-col max-h-[90vh]">
        <!--
            Added: flex flex-col (to make children (header, body, footer) stack vertically and allow body to grow/shrink)
            Added: max-h-[90vh] (sets a maximum height for the entire modal dialog, e.g., 90% of viewport height)
                Adjust 90vh as needed (e.g., max-h-[600px], max-h-[80vh]).
        -->

        <!-- Modal Header -->
        <div class="flex justify-between items-center pb-3 border-b dark:border-dark-600 flex-shrink-0">
            <!--
                Added: flex-shrink-0 (so the header doesn't shrink if content is large)
            -->
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100" id="notificationModalTitle">
                Notification Details
            </h3>
            <button id="notificationModalCloseBtn"
                    type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-dark-600 dark:hover:text-white"
                    aria-label="Close modal">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="mt-4 overflow-y-auto flex-grow">
            <!--
                Added: overflow-y-auto (this makes THIS specific div scrollable if its content exceeds its allocated space)
                Added: flex-grow (this allows the body to take up available vertical space between header and footer)
            -->
            <div id="notificationModalBody"> <!-- This inner div is where your JS injects content -->
                <p class="text-gray-700 dark:text-gray-300">Loading notification content...</p>
                <!-- Dynamic content will be injected here -->
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 pt-4 border-t dark:border-dark-600 flex justify-end space-x-3 flex-shrink-0">
            <button id="notificationModalDeclineBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-dark-800 hidden">
                Decline
            </button>
            <button id="notificationModalAcceptBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-dark-800 hidden">
                Accept
            </button>
            <!-- MODIFIED "View Link" / "View Profile" Button -->
            <a href="#" id="notificationModalViewLink" target="_blank" rel="noopener noreferrer"
                class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none">
                <!-- Text content ("View Profile", "Close", "View Original Context") will be dynamically injected by notification-manager.js -->
            </a>
            <!-- END OF MODIFIED Button -->
            <!-- You can remove notificationModalSecondaryActionBtn if it's no longer used -->
            <!--
            <button id="notificationModalSecondaryActionBtn" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-dark-700 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-dark-800 hidden">
                Secondary Action
            </button>
            -->
        </div>


    </div>
</div>
<!-- Scripts -->
<script>
    window.APP_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
    window.APP_USER_FULL_NAME = <?php echo json_encode($_SESSION['user_full_name'] ?? 'You'); ?>;
    window.APP_USER_AVATAR = <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>;
    const currentUserData = {
        id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
        fullName: <?php echo json_encode($_SESSION['user_full_name'] ?? 'Guest'); ?>,
        username: <?php echo json_encode($_SESSION['user_username'] ?? 'guest'); ?>,
        profilePicture: <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>
    };
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
<script src="/assets/client/js/dashboard.js"></script>
<script src="/assets/client/js/headmanager.js"></script>
<script src="/assets/client/js/notifications.js"></script>
<script src="/assets/client/js/typeahead-chat.js"></script>

<!-- Marketplace Interactivity Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add to cart functionality
        const addButtons = document.querySelectorAll('.fa-plus-circle');
        addButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productCard = this.closest('.product-card');
                const productName = productCard.querySelector('h3').textContent;
                const productPrice = productCard.querySelector('.font-bold').textContent;
                
                // Remove any existing notification
                const existingNotification = document.querySelector('.cart-notification');
                if(existingNotification) existingNotification.remove();

                // Create a notification
                const notification = document.createElement('div');
                notification.className = 'cart-notification fixed bottom-20 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg transition-opacity duration-300';
                notification.textContent = `Added ${productName} (${productPrice}) to cart`;
                document.body.appendChild(notification);
                
                // Fade out and remove notification after 3 seconds
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 2700);
            });
        });

        // Like button functionality
        const likeButtons = document.querySelectorAll('.product-actions .fa-heart');
        likeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                // Toggles between filled (red) and outline (gray) states
                if (this.classList.contains('text-gray-600')) {
                    this.classList.remove('text-gray-600', 'dark:text-gray-300');
                    this.classList.add('text-red-500');
                } else {
                    this.classList.remove('text-red-500');
                    this.classList.add('text-gray-600', 'dark:text-gray-300');
                }
            });
        });
    });
</script>

</body>
</html>