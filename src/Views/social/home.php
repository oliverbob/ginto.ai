<?php
// Safer session & CSRF initialization (avoid fatal errors for missing helpers)
$csrfToken = null;
if (function_exists('getCsrfToken')) {
    try { $csrfToken = getCsrfToken(); } catch (\Throwable $_) { $csrfToken = null; }
}
if (empty($csrfToken)) {
    $csrfToken = $csrf_token ?? ($_SESSION['csrf_token'] ?? null);
}

// Check if user is logged in (using your existing logic)
$isUserLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$username = $isUserLoggedIn ? $_SESSION['user'] : null;
// Display part of email or full username based on your preference
$userDisplay = $isUserLoggedIn && isset($_SESSION['user']) ? (str_contains($_SESSION['user'], '@') ? explode('@', $_SESSION['user'])[0] : $_SESSION['user']) : 'Guest';

$fullName = $_SESSION['user_full_name'] ?? ($_SESSION['user'] ?? 'User');
$initial = strtoupper(substr($fullName, 0, 1));

$profilePicture = $_SESSION['user_profile_picture'] ?? '';

$session_user_id = $_SESSION['user_id'] ?? null;
if (empty($profilePicture)) {
    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
        <circle cx="16" cy="16" r="16" fill="#4B5563"/>
        <text x="50%" y="50%" text-anchor="middle" dy=".35em"
              font-family="Arial, sans-serif" font-size="16"
              fill="#ffffff">' . htmlspecialchars($initial) . '</text>
    </svg>';
    $profilePicture = 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// $expiryTime is passed from HomeController::code() method ('YYYY-MM-DD HH:MM:SS' or null)
$expiryTimestampForJS = null;
$initialExpiryStatusMessage = "";
$initialExpiryStatusColorClass = "text-gray-400"; // Default/none
$initialStatusDotClass = "status-dot-none";

if ($isUserLoggedIn) {
    if (isset($expiryTime) && $expiryTime !== null) {
        $phpExpiryTimestamp = strtotime($expiryTime);
        if ($phpExpiryTimestamp !== false) { // Check if strtotime was successful
            if ($phpExpiryTimestamp > time()) {
                $expiryTimestampForJS = $phpExpiryTimestamp; // Pass to JS for live countdown
                $initialExpiryStatusMessage = "Loading timer..."; // JS will update this
                $initialExpiryStatusColorClass = "user-status-text-active";
                $initialStatusDotClass = "status-dot-active";
            } else {
                $initialExpiryStatusMessage = "Access Expired";
                $initialExpiryStatusColorClass = "user-status-text-expired";
                $initialStatusDotClass = "status-dot-expired";
            }
        } else {
            $initialExpiryStatusMessage = "Invalid Date Data"; // Error case
            $initialExpiryStatusColorClass = "user-status-text-expired";
            $initialStatusDotClass = "status-dot-expired";
        }
    } else {
        $initialExpiryStatusMessage = "No active plan";
        // $initialExpiryStatusColorClass remains gray
        // $initialStatusDotClass remains none
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>SmartFed AI - Sai Chat Pro</title>
    <link href="/assets/favicon.ico" rel="shortcut icon" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- PayPal SDK - LOADED ONCE FOR THE ENTIRE PAGE (INCLUDING MODAL) -->
    <script src="https://www.paypal.com/sdk/js?client-id=AQzi7hX2kmDW5ItWcIQVQxKTkfyw5I4nJYSBN2NjggbSy3r8R5YkhnmIwrDEznTf1CyNDvtD_koB65-s&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>
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
                        dark: {
                            800: '#1E1E1E',
                            700: '#2D2D2D',
                            600: '#3A3A3A',
                            500: '#4A4A4A'
                        },
                        pink: { // Added for Ask Sai button
                            600: '#DB2777',
                            700: '#BE185D'
                        },
                        red: { // Added for Stop Sai button
                            600: '#DC2626',
                            700: '#B91C1C'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
</head>
<body class="bg-gray-100 dark:bg-dark-800 font-sans transition-colors duration-200">
    <!-- Header -->
    <?php
    require_once 'htmlparts/header.php'; // Ensure message items in #messagesDropdown have class="message-notification-item" and data-user/data-img attributes
    ?>

    <!-- Chatbox Container -->
    <div id="chatboxContainer" class="chatbox-container">
        <!-- Chatboxes will be added here dynamically -->
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto pt-16 pb-28 lg:pb-20 grid grid-cols-1 lg:grid-cols-12 main-content">
        
        
        <?php require_once __DIR__ . '/htmlparts/left_sidebar.php'; ?>

        <!-- Main Feed -->
        <div class="col-span-1 lg:col-span-8 px-4">
            <!-- Stories -->
            <?php
                require_once __DIR__ . '/stories.php';
            ?>
            
            <!-- Create Post -->
            <div class="bg-white dark:bg-dark-700 rounded-lg shadow mb-4 p-4">
                <div class="flex items-center space-x-2 pb-3 border-b border-gray-200 dark:border-dark-600">
                    <img src="<?= $profilePicture ?>" alt="User" class="w-10 h-10 rounded-full">
                    <button id="openPostModalBtn" class="flex-grow bg-gray-100 dark:bg-dark-600 dark:text-white hover:bg-gray-200 dark:hover:bg-dark-500 rounded-full py-2 px-4 text-left text-gray-500 dark:text-gray-400">
                        What's on your mind?
                    </button>
                </div>
                <div class="flex justify-between pt-3">
                    <button id="composer-live-video-btn" class="flex items-center justify-center w-1/3 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg py-1" aria-label="Live Video">
                        <i class="fas fa-video text-red-500 mr-2"></i>
                        <span class="action-text hidden sm:inline">Live Video</span>
                    </button>
                    <button id="composer-photo-video-btn" class="flex items-center justify-center w-1/3 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg py-1" aria-label="Photo/Video">
                        <i class="fas fa-images text-green-500 mr-2"></i>
                        <span class="action-text hidden sm:inline">Photo/Video</span>
                    </button>
                    <button class="flex items-center justify-center w-1/3 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg py-1" aria-label="Feeling/Activity">
                        <i class="fas fa-smile text-yellow-500 mr-2"></i>
                        <span class="action-text hidden sm:inline">Feeling/Activity</span>
                    </button>
                </div>
            </div>
            
            <!-- Posts Container -->
            <div id="postsContainer">
                <!-- Posts will be loaded here, including the editor post as the first item -->
            </div>
            
            <!-- Loading indicator -->
            <div id="loadingIndicator" class="text-center py-4 hidden dark:text-gray-300">
                <div class="loading-spinner inline-block"></div>
                <span class="ml-2">Loading more posts...</span>
            </div>
        </div>
        <!-- Right Sidebar -->
        <aside class="hidden lg:block lg:col-span-2 pl-4 right-sidebar">
            <div class="sidebar-container sticky top-16 h-[calc(100vh-64px)] overflow-y-auto scrollbar-hide">
                <div class="space-y-4">
                    <!-- Sponsored -->
                    <div class="mt-2">
                        <h3 class="font-semibold text-gray-500 dark:text-gray-400 px-2">Sponsored</h3>
                        <div class="mt-2 space-y-2">
                            <a href="#" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                                <img src="/assets/client/img/ad<?=mt_rand(1, 9)?>.png" alt="Ad" class="w-10 h-10 rounded-md">
                                <div class="ml-2">
                                    <p class="font-medium dark:text-white">Filipino? Earn 10% profit sharing on ads!</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Join Smartfed.ai Premium</p>
                                </div>
                            </a>
                            <a href="#" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                                <img src="/assets/client/img/ad<?=mt_rand(1, 9)?>.png" alt="Ad" class="w-10 h-10 rounded-md">
                                <div class="ml-2">
                                    <p class="font-medium dark:text-white">Become a SmartFed leader</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Earn up to $1000.00 a month!</p>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Birthdays -->
                    <div class="pt-4 border-t border-gray-300 dark:border-dark-600">
                        <h3 class="font-semibold text-gray-500 dark:text-gray-400 px-2">Birthdays</h3>
                        <div class="mt-2 flex items-center p-2">
                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="fas fa-birthday-cake"></i>
                            </div>
                            <p class="ml-2 text-sm dark:text-white">
                                <span class="font-semibold">Sarah Williams</span> and <span class="font-semibold">2 others</span> have birthdays today.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Contacts -->
                    <div class="pt-4 border-t border-gray-300 dark:border-dark-600">
                        <div class="flex items-center justify-between px-2">
                            <h3 class="font-semibold text-gray-500 dark:text-gray-400">Contacts</h3>
                            <div class="flex space-x-2">
                                <button class="text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-full p-1" aria-label="Video chat">
                                    <i class="fas fa-video"></i>
                                </button>
                                <button class="text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-full p-1" aria-label="Search contacts">
                                    <i class="fas fa-search"></i>
                                </button>
                                <button class="text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-full p-1" aria-label="More options">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div id="contactsListContainer" class="mt-2 space-y-1">
                            <!-- Contacts will be loaded here by JavaScript -->
                            <div id="contactsLoadingState" class="p-2 text-center text-gray-500 dark:text-gray-400">
                                <div class="loading-spinner inline-block w-5 h-5"></div>
                                <span class="ml-2 text-sm">Loading contacts...</span>
                            </div>
                            <div id="contactsEmptyState" class="p-2 text-center text-gray-500 dark:text-gray-400 text-sm hidden">
                                No contacts found.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </main>

    <!-- Create Post Modal -->
    <div id="postModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-dark-700 rounded-lg w-full max-w-md">
            <div class="p-4 border-b border-gray-200 dark:border-dark-600 flex justify-between items-center">
                <h3 class="font-semibold text-lg dark:text-white">Create Post</h3>
                <button id="closePostModalBtn" class="text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-full p-2" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-4">
                <div class="flex items-center space-x-2">
                    <img src="<?= $profilePicture ?>" alt="User" class="w-10 h-10 rounded-full">
                    <div>
                        <p class="font-semibold dark:text-white"><?= htmlspecialchars($_SESSION['user_full_name'] ?? 'User') ?></p>
                        <div class="flex items-center bg-gray-100 dark:bg-dark-600 rounded-md px-2 py-1 text-xs">
                            <i class="fas fa-globe-americas text-gray-500 dark:text-gray-400"></i>
                            <select id="postModalVisibilitySelect" class="bg-transparent border-none focus:outline-none ml-1 dark:text-white dark:bg-dark-600" aria-label="Post audience">
                                <option value="public">Public</option>
                                <option value="friends">Friends</option>
                                <option value="private">Only me</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Share Preview Container -->
                <div id="sharePreviewContainer" class="mt-1">
                    <!-- Preview of the post being shared will be injected here by JS -->
                </div>
                
                <textarea id="postModalTextarea" placeholder="What's on your mind? Or ask Sai to write for you... Or discover Sai Chat by clicking your account profile." class="w-full mt-2 p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none focus:ring-2 focus:ring-facebook dark:bg-dark-600 dark:text-white resize-none" rows="5"></textarea>
                
                <div class="border border-gray-200 dark:border-dark-600 rounded-lg p-3 mt-2">
                    <div class="flex justify-between items-center">
                        <p class="font-medium dark:text-white">Add to your post</p>
                        <div class="flex space-x-2">
                            <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-green-500 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="Add photo/video">
                                <i class="fas fa-images"></i>
                            </button>
                            <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-red-500 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="Tag people">
                                <i class="fas fa-user-tag"></i>
                            </button>
                            <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-yellow-500 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="Add feeling/activity">
                                <i class="fas fa-smile"></i>
                            </button>
                            <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="More options">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="flex mt-4 space-x-3">
                    <button id="askSaiBtn" class="flex-1 bg-pink-600 hover:bg-pink-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center justify-center space-x-2 transition-colors duration-150">
                        <i class="fas fa-magic"></i>
                        <span>Ask Sai</span>
                    </button>
                    <button id="postModalPostBtn" class="flex-1 bg-facebook hover:bg-facebook-dark text-white font-semibold py-2 px-4 rounded-lg transition-colors duration-150">
                        Post
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================== -->
    <!-- START: Universal Premium Modal HTML Structure -->
    <!-- ================================================== -->
    <div id="universalPremiumModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center p-4 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="max-w-3xl w-full bg-white dark:bg-dark-700 rounded-xl shadow-lg overflow-hidden transform transition-all sm:my-8 animate-fade-in">
            <!-- Close Button -->
            <button id="closePremiumModal" class="absolute top-4 right-4 text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 z-[101]">
                <span class="sr-only">Close</span>
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="md:flex">
                <div class="md:w-2/5 bg-gradient-to-br from-purple-600 to-purple-500 p-8 text-white flex flex-col justify-center relative">
                    <div class="flex items-center mb-6">
                        <i class="fas fa-crown text-4xl mr-4"></i>
                        <div>
                            <h1 class="text-3xl font-bold">Go Premium!</h1>
                            <p class="text-purple-200">Unlock Full Code Editor Access</p>
                        </div>
                    </div>
                    <p class="mb-6 text-purple-100">Subscribe now to enjoy unlimited AI power, priority access, and exclusive features. Elevate your experience today!</p>
                    
                    <div class="bg-purple-700 bg-opacity-40 rounded-lg p-4">
                        <h3 class="font-semibold mb-3 text-lg">Premium Benefits:</h3>
                        <ul class="space-y-2 text-sm text-purple-50">
                            <li class="feature-item">Unlimited daily requests</li>
                            <li class="feature-item">Priority AI processing</li>
                            <li class="feature-item">Extended response lengths</li>
                            <li class="feature-item">Advanced customization options</li>
                            <li class="feature-item">Dedicated 24/7 VIP support</li>
                            <li class="feature-item">Early access to new features & models</li>
                        </ul>
                    </div>
                </div>
                
                <div class="md:w-3/5 p-8 flex flex-col justify-center">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 text-center">Subscribe to Premium</h2>
                    <p class="text-gray-600 dark:text-gray-300 text-center mb-6">
                        Gain immediate access to all premium features with our straightforward subscription plan.
                    </p>
                    
                    <!-- PayPal Subscription Button Container -->
                    <div id="paypal-button-container-P-43S89794RD1094113NAV52CA-modal" class="mx-auto w-full max-w-xs"></div>
                    
                    <div class="mt-8 text-xs text-gray-500 dark:text-gray-400 text-center">
                        <div class="flex items-center justify-center text-green-600 mb-2">
                            <i class="fas fa-shield-alt mr-2"></i>
                            <span>Secure & Encrypted Payment via PayPal</span>
                        </div>
                        <p>By subscribing, you agree to our <a href="#" class="text-purple-500 hover:underline">Terms of Service</a> and <a href="#" class="text-purple-500 hover:underline">Privacy Policy</a>.</p>
                        <p class="mt-1">You can manage or cancel your subscription anytime through your PayPal account.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ================================================== -->
    <!-- END: Universal Premium Modal HTML Structure -->
    <!-- ================================================== -->


    <!-- Mobile Bottom Navigation (raised and floating so composer remains visible) -->
    <nav class="lg:hidden fixed bottom-4 left-0 right-0 mx-4 bg-white/95 dark:bg-dark-700/95 shadow-lg border border-gray-200 dark:border-dark-600 rounded-xl z-50 backdrop-blur-sm">
        <div class="flex justify-around py-2">
            <a href="/" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Contacts">
                <i class="fas fa-home text-xl"></i>
            </a>
            <a href="/dashboard" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Dashboard">
                <i class="fas fa-dashboard text-xl"></i>
            </a>
            <a href="/code" class="p-3 text-facebook" aria-label="Sai Chat">
                <i class="fas fa-atom text-xl"></i>
            </a>
            <a href="/gaming" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Search">
                <i class="fas fa-gamepad text-xl"></i>
            </a>
            <a href="#" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Menu">
                <i class="fas fa-bars text-xl"></i>
            </a>
        </div>
    </nav>

    <!-- Story Creation Modal -->
    <div id="createStoryModal" class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white dark:bg-dark-800 rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-dark-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Create New Story</h3>
                <button id="closeCreateStoryModal" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <form id="createStoryForm" class="p-4 space-y-4 overflow-y-auto">
                <div>
                    <label for="storyContentType" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Story Type</label>
                    <select id="storyContentType" name="content_type" class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white">
                        <option value="text_only" selected>Text Only</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                        <option value="code_snippet">Code Snippet</option>
                        <!-- <option value="link_preview">Link Preview</option> -->
                    </select>
                </div>

                <!-- Image/Video Upload Section (shown conditionally) -->
                <div id="storyMediaUploadSection" class="hidden space-y-2">
                    <label for="storyMediaFile" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Media File (Image/Video)</label>
                    <input type="file" id="storyMediaFile" name="media_file" class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 dark:file:bg-blue-900 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-800">
                    <div id="storyMediaPreviewContainer" class="mt-2 border border-dashed border-gray-300 dark:border-dark-600 rounded-md p-2 min-h-[100px] flex items-center justify-center">
                        <img id="storyImagePreview" src="#" alt="Image Preview" class="max-h-48 max-w-full hidden rounded">
                        <video id="storyVideoPreview" controls class="max-h-48 max-w-full hidden rounded">
                            <source src="#" type="video/mp4"> Your browser does not support the video tag.
                        </video>
                        <span id="storyMediaPreviewPlaceholder" class="text-gray-400 dark:text-gray-500 text-sm">Media preview</span>
                    </div>
                </div>
                
                <!-- Text Overlay / Main Text Content -->
                <div id="storyTextContentWrapper">
                    <label for="storyTextOverlay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Text Content</label>
                    <textarea id="storyTextOverlay" name="text_overlay" rows="3" placeholder="What's on your mind for this story?" class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white"></textarea>
                </div>

                <!-- Code Snippet Section (shown conditionally) -->
                <div id="storyCodeSnippetSection" class="hidden space-y-2">
                    <div>
                        <label for="storyCodeLanguage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code Language</label>
                        <input type="text" id="storyCodeLanguage" name="code_language" placeholder="e.g., javascript, php, python" class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white">
                    </div>
                    <div>
                        <label for="storyCodeContent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                        <textarea id="storyCodeContent" name="code_content" rows="5" placeholder="Paste your code here..." class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white font-mono text-sm"></textarea>
                    </div>
                </div>

                <!-- Background Color (for text_only, code_snippet) -->
                <div id="storyBackgroundColorSection" class="space-y-1">
                    <label for="storyBackgroundColor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Background Color (Hex)</label>
                    <input type="text" id="storyBackgroundColor" name="background_color" placeholder="#3B82F6" class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white">
                </div>

                <div>
                    <label for="storyVisibility" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Visibility</label>
                    <select id="storyVisibility" name="visibility" class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white">
                        <option value="public" selected>Public</option>
                        <option value="friends_only">Friends Only</option>
                        <option value="private">Private (Only Me)</option>
                    </select>
                </div>
                
                <div>
                    <label for="storyDurationSeconds" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Display Duration (seconds)</label>
                    <input type="number" id="storyDurationSeconds" name="duration_seconds" value="15" min="3" max="60" class="w-full p-2 border border-gray-300 dark:border-dark-600 rounded-md focus:outline-none dark:bg-dark-700 dark:text-white">
                </div>
                <!-- You can add expiry duration here if needed, e.g., default 24h -->
                <input type="hidden" name="expires_duration_hours" value="24">


                <div id="storyCreationError" class="text-red-500 text-sm hidden"></div>
            </form>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-gray-200 dark:border-dark-700 flex justify-end space-x-2">
                <button id="cancelCreateStory" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-dark-600 hover:bg-gray-200 dark:hover:bg-dark-500 rounded-md">Cancel</button>
                <button id="submitCreateStory" type="submit" form="createStoryForm" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">Create Story</button>
            </div>
        </div>
    </div>

    <!-- Full Screen Story Viewer Modal -->
    <div id="storyViewerModal"
        class="fixed inset-0 bg-black bg-opacity-90 flex flex-col items-center justify-center p-0 z-[70] hidden"
        role="dialog" aria-modal="true" aria-labelledby="storyViewerUserName">

        <div class="relative w-full max-w-md md:max-w-lg aspect-[9/16] bg-dark-900 rounded-lg overflow-hidden shadow-2xl flex flex-col">
            
            <!-- Story Viewer Content Area (this will be filled by JS) -->
            <div id="storyViewerContent" class="absolute inset-0 w-full h-full">
                <!-- JavaScript will insert image, video, or text content here -->
            </div>

            <!-- Story Viewer Header (User Info & Close Button) - Overlay -->
            <div id="storyViewerHeader"
                class="absolute top-0 left-0 right-0 p-3 md:p-4 flex items-center justify-between z-20 
                    bg-gradient-to-b from-black/50 via-black/30 to-transparent">
                <div class="flex items-center space-x-2 md:space-x-3">
                    <img id="storyViewerUserAvatar" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="User avatar"
                        class="w-8 h-8 md:w-10 md:h-10 rounded-full object-cover border-2 border-white/80 shadow bg-gray-500">
                    <div>
                        <p id="storyViewerUserName" class="text-white text-sm md:text-base font-semibold leading-tight">Username</p>
                        <p id="storyViewerTimestamp" class="text-gray-300 text-xs leading-tight">Just now</p>
                    </div>
                </div>
                <button id="closeStoryViewer" type="button"
                    class="text-white hover:text-gray-300 text-2xl md:text-3xl p-1 rounded-full hover:bg-black/30 focus:outline-none focus:ring-2 focus:ring-white/50"
                    aria-label="Close story viewer">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation Buttons - Overlay -->
            <button id="storyViewerPrevItem" aria-label="Previous story item"
                class="absolute left-1 md:left-2 top-1/2 -translate-y-1/2 z-20 text-white text-3xl md:text-4xl p-2 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-white/50 rounded-full hover:bg-black/20 hidden">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button id="storyViewerNextItem" aria-label="Next story item"
                class="absolute right-1 md:right-2 top-1/2 -translate-y-1/2 z-20 text-white text-3xl md:text-4xl p-2 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-white/50 rounded-full hover:bg-black/20 hidden">
                <i class="fas fa-chevron-right"></i>
            </button>

            <!-- Clickable Zones for Next/Prev (covers entire left/right halves) - Overlay -->
            <div id="storyViewerPrevZone" class="absolute left-0 top-0 h-full w-1/3 z-10 cursor-pointer"></div>
            <div id="storyViewerNextZone" class="absolute right-0 top-0 h-full w-1/3 z-10 cursor-pointer"></div>
        </div>
    </div>

    <!-- Ringing Call Modal -->
    <div id="ringingCallModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 dark:bg-opacity-75 hidden transition-opacity duration-300 ease-in-out opacity-0">
        <div id="ringingCallModalContent" class="bg-white dark:bg-dark-700 p-6 sm:p-8 rounded-xl shadow-2xl text-center w-full max-w-xs sm:max-w-sm transform scale-95 transition-all duration-300 ease-in-out">
            <img id="ringingCallerAvatar" src="" alt="Caller Avatar" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover mx-auto mb-4 border-4 border-gray-200 dark:border-dark-600 shadow-md">
            <h3 id="ringingCallerName" class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-white mb-1"></h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Incoming video call...</p>
            <div class="flex justify-around space-x-3">
                <button id="declineCallButton" type="button" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-opacity-50 transition-colors duration-150 ease-in-out flex items-center justify-center space-x-2">
                    <i class="fas fa-phone-slash"></i>
                    <span>Decline</span>
                </button>
                <button id="acceptCallButton" type="button" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-opacity-50 transition-colors duration-150 ease-in-out flex items-center justify-center space-x-2">
                    <i class="fas fa-phone"></i>
                    <span>Accept</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Full Screen Video Call Modal -->
    <div id="fullScreenVideoModal" class="fixed inset-0 bg-black bg-opacity-90 flex flex-col items-center justify-center z-50 hidden">
        <div class="relative w-full h-full max-w-full max-h-full">
            <span id="fullScreenVideoStatusOverlay" 
                class="absolute top-4 left-4 z-10 text-white text-lg bg-black bg-opacity-70 py-2 px-4 rounded-md shadow-lg">
            </span>
            <video id="fullScreenRemoteVideo" autoplay playsinline class="w-full h-full object-contain"></video>
            <video id="fullScreenLocalVideo" autoplay playsinline muted class="absolute bottom-4 right-4 w-1/5 max-w-[200px] h-auto bg-gray-800 rounded border-2 border-white shadow-md"></video>

            <div id="fullScreenVideoControls" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 p-3 bg-black bg-opacity-60 rounded-xl flex justify-center items-center space-x-4 z-20">
                <button type="button" id="fullScreenToggleMicBtn" class="video-control-btn text-xl" aria-label="Mute microphone"><i class="fas fa-microphone"></i></button>
                <button type="button" id="fullScreenHangupBtn" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-full text-xl" aria-label="Hang up"><i class="fas fa-phone-slash"></i></button>
                <button type="button" id="fullScreenToggleCameraBtn" class="video-control-btn text-xl" aria-label="Disable camera"><i class="fas fa-video"></i></button>
                <button type="button" id="fullScreenMinimizeBtn" class="video-control-btn text-xl ml-auto" aria-label="Minimize video"><i class="fas fa-compress"></i></button>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div id="createGroupModal" 
        class="fixed inset-0 z-[60] hidden 
                flex items-center justify-center
                bg-black bg-opacity-50 
                transition-opacity duration-300 ease-in-out opacity-0">

        <div id="createGroupModalContent" 
            class="bg-white dark:bg-dark-800 p-5 sm:p-6 rounded-lg shadow-xl 
                    w-full max-w-md 
                    transform transition-all duration-300 ease-in-out scale-95">
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create New Group</h3>
                <button type="button" id="closeCreateGroupModalBtn" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                    <span class="sr-only">Close</span>
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Group Name Input -->
            <div>
                <label for="groupNameInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group Name <span class="text-red-500">*</span></label>
                <input type="text" id="groupNameInput" maxlength="100" class="mt-1 block w-full px-3 py-2 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Enter group name">
            </div>

            <!-- Group Icon URL Input (Optional) -->
            <div class="mt-4">
                <label for="groupIconUrlInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group Icon URL (Optional)</label>
                <input type="url" id="groupIconUrlInput" class="mt-1 block w-full px-3 py-2 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="https://example.com/icon.png">
            </div>

            <!-- Add Participants Section -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add Participants <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" id="groupParticipantSearchInput" class="mt-1 block w-full px-3 py-2 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Search users to add...">
                    <div id="groupParticipantSearchDropdown" class="absolute z-[70] mt-1 w-full bg-white dark:bg-dark-700 rounded-md shadow-lg hidden ring-1 ring-black ring-opacity-5 dark:ring-gray-700 max-h-48 sm:max-h-60 overflow-y-auto">
                        <ul id="groupParticipantSearchResults" class="py-1">
                            <!-- Search results populated here -->
                        </ul>
                    </div>
                </div>
                <div id="selectedGroupParticipants" class="mt-2 space-y-1 max-h-24 sm:max-h-32 overflow-y-auto p-1 border dark:border-dark-600 rounded-md empty:hidden">
                    <!-- Selected participants listed here -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="cancelCreateGroupBtn" class="px-3 sm:px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-dark-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="button" id="submitCreateGroupBtn" class="px-3 sm:px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Create Group
                </button>
            </div>
        </div>
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

    <!--Delete comments Confirmation Modal-->
    <div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full flex items-center justify-center hidden z-[100]" aria-labelledby="deleteConfirmModalTitle" role="dialog" aria-modal="true">
        <div class="relative p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-dark-800">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mt-2" id="deleteConfirmModalTitle">Delete Confirmation</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300" id="deleteConfirmModalMessage">
                        Are you sure you want to proceed? This action cannot be undone.
                    </p>
                </div>
                <!-- MODIFIED BUTTON CONTAINER BELOW -->
                <div class="items-center px-4 py-3 space-y-2 sm:space-y-0 sm:flex sm:justify-center sm:space-x-4">
                    <button id="deleteConfirmModalConfirmBtn" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-dark-800">
                        Delete
                    </button>
                    <button id="deleteConfirmModalCancelBtn" type="button" class="w-full sm:w-auto mt-2 sm:mt-0 inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-600 shadow-sm px-4 py-2 bg-white dark:bg-dark-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-dark-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-dark-800">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Editor Loader Script (AMD) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>


<script>
    
    window.APP_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
    window.APP_USER_FULL_NAME = <?php echo json_encode($_SESSION['user_full_name'] ?? 'You'); ?>;
    window.APP_USER_AVATAR = <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>;

    // Expose current user data on the window object so client scripts (headmanager.js etc.) can read it.
    window.currentUserData = {
        id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
        fullName: <?php echo json_encode($_SESSION['user_full_name'] ?? ($_SESSION['user'] ?? 'Guest')); ?>,
        username: <?php echo json_encode($_SESSION['user_username'] ?? ($_SESSION['user'] ?? 'guest')); ?>,
        profilePicture: <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>
    };
  
</script>
<script src="/assets/client/js/smartfed.js"></script>
<script src="/assets/client/js/stories.js"></script>
<script src="/assets/client/js/headmanager.js"></script>
<script src="/assets/client/js/notifications.js"></script>
<script src="/assets/client/js/typeahead-chat.js"></script>
<script src="/assets/client/js/contacts.js"></script>
<script src="/assets/client/js/feedmanager.js"></script>
<script src="/assets/client/js/mediamanager.js"></script>
</body>
</html>