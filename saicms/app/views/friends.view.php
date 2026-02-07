<?php
/**
 * View for the Friends page
 *
 * Expects the following variables from the controller:
 * - $pageTitle (string)
 * - $profileUser (array) - User whose profile/friends page is being viewed
 * - $loggedInUserDetails (array) - Currently logged-in user
 * - $isOwnProfile (bool)
 * - $friendRequests (array)
 * - $allFriends (array)
 * - $suggestedFriends (array)
 * // $defaultProfilePic is not directly used by render_avatar but noted
 */
$csrfToken = getCsrfToken();
// --- PHP Helper Functions ---
if (!function_exists('getInitials')) {
    function getInitials(?string $fullName, ?string $username = null, int $numInitials = 2): string {
        $nameToUse = trim($fullName ?? '');
        if (empty($nameToUse) && !empty($username)) {
            $nameToUse = trim($username);
        }
        if (empty($nameToUse)) return "?";
        $words = preg_split('/\s+/', $nameToUse);
        $initials = '';
        if (count($words) >= 2 && $numInitials == 2) {
            $initials .= mb_strtoupper(mb_substr($words[0], 0, 1));
            $initials .= mb_strtoupper(mb_substr(end($words), 0, 1));
        } elseif (!empty($words)) {
            $initials .= mb_strtoupper(mb_substr($words[0], 0, 1));
            if ($numInitials == 2 && count($words) == 1 && mb_strlen($words[0]) > 1) {
                $initials .= mb_strtoupper(mb_substr($words[0], 1, 1));
            }
        }
        return empty($initials) ? mb_strtoupper(mb_substr($nameToUse, 0, $numInitials == 2 ? 2 : 1)) : $initials;
    }
}

if (!function_exists('generateBgColorForInitials')) {
    function generateBgColorForInitials(string $initials): string {
        $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#06b6d4'];
        $hash = 0;
        if (!empty($initials)) {
            for ($i = 0; $i < strlen($initials); $i++) {
                $hash = ord($initials[$i]) + (($hash << 5) - $hash);
                $hash = $hash & $hash;
            }
        }
        return $colors[abs($hash) % count($colors)];
    }
}

// Avatar rendering helper
function render_avatar(array $user, string $sizeClasses, int $svgSize, string $imgClass = 'object-cover') {
    $initials = getInitials($user['full_name'] ?? null, $user['username'] ?? null);
    $hasProfilePic = !empty($user['profile_picture']);
    $imgSrc = $hasProfilePic ? htmlspecialchars($user['profile_picture']) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    // The outer div has the Tailwind size classes, the img inside is w-full h-full
    // Note: border-primary-600 does not have a light: variant by default, it will remain the same color in light/dark
    echo '<div class="avatar-placeholder-container flex-shrink-0 rounded-full border-2 border-primary-600 overflow-hidden ' . $sizeClasses . '"
               data-initials="' . htmlspecialchars($initials) . '"
               data-size="' . $svgSize . '"
               data-bg-color="' . htmlspecialchars(generateBgColorForInitials($initials)) . '">';
    echo   '<img src="' . $imgSrc . '"
                 alt="' . htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User') . '"
                 class="w-full h-full ' . $imgClass . ($hasProfilePic ? '' : ' hidden-if-no-src') . '">';
    echo '</div>';
}

$current_user_id = $loggedInUserDetails['id'] ?? 0;
$profile_page_user_id = $profileUser['id'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Friends') ?> - SmartFed AI</title>
    <link href="/assets/favicon.ico" rel="shortcut icon" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Scrollbar */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        /* Transitions */
        .transition-bg { transition: background-color 0.3s ease; }
        .transition-border { transition: border-color 0.3s ease; }
        .transition-text { transition: color 0.3s ease; }
        
        /* Light mode styles */
        .light .light\:bg-white { background-color: #ffffff !important; }
        .light .light\:bg-gray-50 { background-color: #f9fafb !important; }
        .light .light\:bg-gray-100 { background-color: #f3f4f6 !important; }
        .light .light\:bg-gray-200 { background-color: #e5e7eb !important; }
        .light .light\:text-gray-800 { color: #1f2937 !important; }
        .light .light\:text-gray-600 { color: #4b5563 !important; }
        .light .light\:text-gray-400 { color: #6b7280 !important; }
        .light .light\:border-gray-200 { border-color: #e5e7eb !important; }
        .light .light\:hover\:bg-gray-100:hover { background-color: #f3f4f6 !important; }
        .light .light\:hover\:bg-gray-200:hover { background-color: #e5e7eb !important; }
        .light .light\:hover\:bg-gray-300:hover { background-color: #d1d5db !important; }
        .light .light\:hover\:text-gray-900:hover { color: #111827 !important; }
        .light .light\:placeholder-gray-500::placeholder { color: #6b7280 !important; }
        
        /* Avatar fallback */
        .hidden-if-no-src {
            display: none !important;
        }

        /* If needed in your main CSS file */
        .friend-options-dropdown {
            /* Tailwind classes are doing most of the work:
            absolute right-0 mt-2 w-40 bg-dark-600 rounded-md shadow-lg py-1 z-20 border border-dark-500
            You might add:
            */
            min-width: 150px; /* Example */
        }
    </style>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            600: '#6366f1',
                            700: '#4f46e5',
                        },
                        dark: {
                            900: '#0a0a0a',
                            800: '#1a1a1a',
                            700: '#262626',
                            600: '#404040',
                            500: '#525252',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-dark-900 text-gray-100 light:bg-gray-50 light:text-gray-800 transition-colors duration-200">
    <!-- Header -->
    <?php require_once 'htmlparts/header.php'; ?>

    <!-- Main Container -->
    <div class="container mx-auto px-4 py-6 flex flex-col md:flex-row pt-20">
        <!-- Sidebar -->
        <aside class="w-full md:w-1/4 lg:w-1/5 mb-6 md:mb-0 md:pr-4">
            <?php if (isset($loggedInUserDetails)): ?>
            <!-- User Profile Card -->
            <div class="bg-dark-800 light:bg-white rounded-lg shadow p-4 mb-4 border border-dark-700 light:border-gray-200 transition-bg transition-border">
                <div class="flex items-center mb-4">
                    <div class="mr-3 h-10 w-10">
                        <?php render_avatar($profileUser, 'w-full h-full', 40); ?>
                    </div>
                    <a href="/profile/<?= $profile_page_user_id ?>" class="font-semibold text-gray-100 light:text-gray-800 hover:underline transition-text">
                        <?= htmlspecialchars($profileUser['full_name'] ?? $profileUser['username'] ?? 'User') ?>
                    </a>
                </div>
                
                <!-- Navigation Menu -->
                <nav class="space-y-1">
                    <a href="/friends/<?= $profile_page_user_id ?>" class="flex items-center w-full py-2 px-2 rounded hover:bg-dark-700 light:hover:bg-gray-100 text-gray-100 light:text-gray-800 transition-colors">
                        <i class="fas fa-user-friends text-primary-600 mr-3"></i>
                        <span>Friends</span>
                    </a>
                    <a href="/friends/requests" class="flex items-center w-full py-2 px-2 rounded hover:bg-dark-700 light:hover:bg-gray-100 text-gray-100 light:text-gray-800 transition-colors">
                        <i class="fas fa-user-plus text-primary-600 mr-3"></i>
                        <span>Friend Requests</span>
                    </a>
                    <a href="/friends/suggestions" class="flex items-center w-full py-2 px-2 rounded hover:bg-dark-700 light:hover:bg-gray-100 text-gray-100 light:text-gray-800 transition-colors">
                        <i class="fas fa-user-clock text-primary-600 mr-3"></i>
                        <span>Suggestions</span>
                    </a>
                    <a href="/birthdays" class="flex items-center w-full py-2 px-2 rounded hover:bg-dark-700 light:hover:bg-gray-100 text-gray-100 light:text-gray-800 transition-colors">
                        <i class="fas fa-birthday-cake text-primary-600 mr-3"></i>
                        <span>Birthdays</span>
                    </a>
                    <a href="/friends/lists" class="flex items-center w-full py-2 px-2 rounded hover:bg-dark-700 light:hover:bg-gray-100 text-gray-100 light:text-gray-800 transition-colors">
                        <i class="fas fa-list text-primary-600 mr-3"></i>
                        <span>Custom Lists</span>
                    </a>
                </nav>
            </div>
            
            <!-- Shortcuts -->
            <div class="bg-dark-800 light:bg-white rounded-lg shadow p-4 border border-dark-700 light:border-gray-200 transition-bg transition-border">
                <h3 class="font-semibold mb-3 text-gray-100 light:text-gray-800 transition-text">Your Shortcuts</h3>
                <div class="flex items-center py-2 px-2 rounded hover:bg-dark-700 light:hover:bg-gray-100 cursor-pointer transition-colors">
                    <i class="fas fa-users text-primary-600 mr-3"></i>
                    <span class="text-gray-100 light:text-gray-800 transition-text">College Friends</span>
                </div>
            </div>
            <?php endif; ?>
        </aside>
        
        <!-- Main Content -->
        <main class="w-full md:w-3/4 lg:w-4/5 md:pl-4 space-y-6">
            <!-- Friend Requests Section -->
            <?php if (isset($isOwnProfile) && $isOwnProfile): ?>
            <div class="bg-dark-800 light:bg-white rounded-lg shadow p-4 mb-6 animate-fade-in border border-dark-700 light:border-gray-200 transition-bg transition-border">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold transition-text">Friend Requests</h2>
                    <?php if (!empty($friendRequests)): ?>
                        <a href="/friends/requests/all" class="text-primary-600 hover:underline transition-text">See All</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($friendRequests)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($friendRequests as $request): ?>
                        <div class="bg-dark-700 border border-dark-600 rounded-lg p-4 flex flex-col items-center transition-transform hover:scale-[1.02]">
                            <a href="/profile/<?= (int)$request['user_id'] ?>" class="mb-3">
                                <?php render_avatar($request, 'h-24 w-24', 96); ?>
                            </a>
                            <a href="/profile/<?= (int)$request['user_id'] ?>" class="font-semibold transition-text hover:underline"><?= htmlspecialchars($request['full_name'] ?? $request['username']) ?></a>
                            <p class="text-gray-400 text-sm mb-3 transition-text">
                                <?= isset($request['mutual_friends_count']) && $request['mutual_friends_count'] > 0 ? (int)$request['mutual_friends_count'] . ' mutual friend' . ($request['mutual_friends_count'] > 1 ? 's' : '') : (htmlspecialchars(mb_strimwidth($request['bio'] ?? '', 0, 25, '...')) ?: ' ') ?>
                            </p>
                            <div class="flex space-x-2 w-full">
                                <form action="/friends/accept/<?= (int)$request['request_id'] ?>" method="POST" class="flex-1">
                                    <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white py-1 px-4 rounded-lg w-full text-sm font-medium transition-colors">Confirm</button>
                                </form>
                                <form action="/friends/decline/<?= (int)$request['request_id'] ?>" method="POST" class="flex-1">
                                    <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="bg-dark-600 hover:bg-dark-500 text-gray-200 py-1 px-4 rounded-lg w-full text-sm font-medium transition-colors">Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400 text-center py-4">No pending friend requests.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- People You May Know Section -->
            <?php if (!empty($suggestedFriends)): ?>
            <section class="bg-dark-800 light:bg-white rounded-lg shadow p-6 mb-6 animate-fade-in border border-dark-700 light:border-gray-200 transition-bg transition-border" style="animation-delay: 0.2s;">
                <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <h2 class="text-xl font-semibold text-gray-100 light:text-gray-800 transition-text">People You May Know</h2>
                    <div class="flex items-center gap-3">
                        <input type="text" id="searchSuggestionsInput" placeholder="Search suggestions..." class="bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 text-gray-200 light:text-gray-800 placeholder-gray-400 light:placeholder-gray-500 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-600 transition-bg transition-border transition-text w-48">
                        <a href="#" onclick="alert('feature coming soon...')" id="seeAllSuggestionsLink" class="text-primary-600 hover:underline transition-text text-sm <?php if (empty($suggestedFriends)) echo 'hidden'; ?>">See All</a>
                    </div>
                </div>
                
                <div id="suggestedFriendsGridContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php if (!empty($suggestedFriends)): ?>
                        <?php foreach ($suggestedFriends as $suggestion): 
                            $initials = getInitials($suggestion['full_name'] ?? null, $suggestion['username'] ?? null);
                            $bgColor = generateBgColorForInitials($initials);
                        ?>
                        <div class="suggestion-card-item bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 rounded-lg p-4 flex flex-col items-center transition-transform hover:scale-[1.02] transition-bg transition-border"
                            data-suggestion-name="<?= htmlspecialchars(strtolower($suggestion['full_name'] ?? $suggestion['username'])) ?>"
                            data-suggestion-id="<?= (int)$suggestion['id'] ?>">
                            <a href="/profile/<?= (int)$suggestion['id'] ?>" class="mb-3 avatar-placeholder-container h-24 w-24 rounded-full border-2 border-primary-600 overflow-hidden"
                                 data-initials="<?= htmlspecialchars($initials) ?>"
                                 data-size="96" 
                                 data-bg-color="<?= htmlspecialchars($bgColor) ?>">
                                <img src="<?= htmlspecialchars(!empty($suggestion['profile_picture']) ? $suggestion['profile_picture'] : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') ?>"
                                     alt="<?= htmlspecialchars($suggestion['full_name'] ?? $suggestion['username']) ?>"
                                     class="w-full h-full object-cover <?= empty($suggestion['profile_picture']) ? 'hidden-if-no-src' : '' ?>">
                            </a>
                            <a href="/profile/<?= (int)$suggestion['id'] ?>" class="font-semibold text-gray-100 light:text-gray-800 hover:underline transition-text text-center mb-1">
                                <?= htmlspecialchars($suggestion['full_name'] ?? $suggestion['username']) ?>
                            </a>
                            <p class="text-gray-400 light:text-gray-600 text-sm mb-4 text-center transition-text h-10 leading-tight overflow-hidden">
                                <?= isset($suggestion['mutual_friends_count']) && $suggestion['mutual_friends_count'] > 0 ? (int)$suggestion['mutual_friends_count'] . ' mutual friend' . ($suggestion['mutual_friends_count'] > 1 ? 's' : '') : (htmlspecialchars(mb_strimwidth($suggestion['bio'] ?? 'New to SmartFed', 0, 50, '...')) ?: 'New to SmartFed') ?>
                            </p>
                            <div class="flex gap-2 w-full mt-auto pt-2 border-t border-dark-600 light:border-gray-300">
                                <form action="/friends/suggestion/add/<?= (int)$suggestion['id'] ?>" method="POST" class="flex-1">
                                    <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="w-full h-9 px-3 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors flex items-center justify-center">Add</button>
                                </form>
                                <form action="/friends/suggestion/remove/<?= (int)$suggestion['id'] ?>" method="POST" class="flex-1">
                                    <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="w-full h-9 px-3 text-sm font-medium text-gray-200 light:text-gray-800 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-lg transition-colors flex items-center justify-center">Remove</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="noSuggestionsFoundMessage" class="text-center py-8 text-gray-400 light:text-gray-600 text-lg <?php if (!empty($suggestedFriends)) echo 'hidden'; ?>">
                    <i class="fas fa-user-plus text-3xl mb-3 search-icon"></i>
                    <p id="noSuggestionsText">
                        <?php if (empty($suggestedFriends)): ?>
                            No new friend suggestions right now.
                        <?php else: ?>
                            No suggestions match your search. 
                        <?php endif; ?>
                    </p>
                </div>

                <div id="suggestionsLoadingIndicator" class="text-center py-8 text-gray-400 light:text-gray-600 hidden">
                    <i class="fas fa-spinner fa-spin text-3xl"></i>
                    <p class="mt-2 text-sm">Searching for more suggestions...</p>
                </div>
            </section>
            <?php endif; ?>


            <!-- DYNAMIC Mutual Friends Section (Populated by JS)  -->
            <?php if (isset($profileUser) && isset($profileUser['id']) && isset($current_user_id) && $profileUser['id'] != $current_user_id): ?>
            <section class="bg-dark-800 light:bg-white rounded-lg shadow p-6 mb-6 animate-fade-in border border-dark-700 light:border-gray-200 transition-bg transition-border"
                     style="animation-delay: 0.3s;"
                     data-mutual-friends-for="<?= (int)$profileUser['id'] ?>"
                     data-mutual-friends-current-page="1"
                     data-mutual-friends-limit="6" >

                <div class="flex flex-wrap justify-between items-center mb-4 gap-3">
                    <h2 class="text-xl font-semibold text-gray-100 light:text-gray-800 transition-text">
                        Mutual Friends with <?= htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']) ?>
                        <span class="mutual-friends-count-display text-sm font-normal text-gray-400 light:text-gray-600"></span>
                    </h2>
                    <input type="text"
                           class="mutual-friends-search-input bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 text-gray-200 light:text-gray-800 placeholder-gray-400 light:placeholder-gray-500 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-600 transition-colors w-full sm:w-48"
                           placeholder="Search mutual friends...">
                </div>

                <ul class="mutual-friends-list grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <!-- JS will populate this -->
                </ul>

                <div class="mutual-friends-loading text-center py-4 text-gray-400 light:text-gray-600 hidden">
                    <p class="text-sm">Loading...</p>
                </div>
                <div class="mutual-friends-error text-center py-4 text-red-500 hidden">
                    <p class="text-sm">Could not load mutual friends.</p>
                </div>
                <div class="mutual-friends-empty text-center py-8 text-gray-400 light:text-gray-600 hidden">
                    <p class="text-md">No mutual friends match your search or none found with <?= htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']) ?>.</p>
                </div>

                <div class="mt-6 text-center mutual-friends-load-more-container hidden">
                    <button class="mutual-friends-load-more-btn ...">Load More</button>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- All Friends Section -->
            <section id="allFriendsSection" data-profile-id="<?= (int)($profileUser['id'] ?? 0) ?>" class="bg-dark-800 light:bg-white rounded-lg shadow p-6 border border-dark-700 light:border-gray-200 transition-bg transition-border animate-fade-in" style="animation-delay: 0.4s;">
                <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <h2 class="text-xl font-semibold text-gray-100 light:text-gray-800 transition-text">
                        <?php 
                        if (isset($isOwnProfile) && $isOwnProfile): ?>
                            Your Friends (<?= count($allFriends ?? []) ?>)
                        <?php elseif (isset($profileUser)): ?>
                            <?= htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']) ?>'s Friends (<?= count($allFriends ?? []) ?>)
                        <?php else: ?>
                            Friends (<?= count($allFriends ?? []) ?>)
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (!empty($allFriends)): ?>
                    <div class="flex items-center gap-3">
                        <select id="allFriendsSortSelect" class="bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 text-gray-200 light:text-gray-800 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-600 transition-bg transition-border transition-text">
                            <option value="default">Sort by: Newest</option>
                            <option value="name_asc">Sort by: Name (A-Z)</option>
                            <option value="name_desc">Sort by: Name (Z-A)</option>
                        </select>
                        <input type="text" id="allFriendsSearchInput" placeholder="Search friends..." class="bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 text-gray-200 light:text-gray-800 placeholder-gray-400 light:placeholder-gray-500 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-600 transition-bg transition-border transition-text w-48">
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($allFriends)): ?>
                    <div id="allFriendsGridContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($allFriends as $friend): ?>
                        <div class="friend-card-item bg-dark-700 light:bg-gray-100 border border-dark-600 light:border-gray-200 rounded-lg p-4 flex flex-col items-center transition-transform hover:scale-[1.02] transition-bg transition-border"
                             data-friend-name="<?= htmlspecialchars(strtolower($friend['full_name'] ?? $friend['username'])) ?>"
                             data-friended-at="<?= htmlspecialchars($friend['accepted_at'] ?? '0') ?>"
                             data-friend-id="<?= (int)$friend['id'] ?>">
                            <a href="/profile/<?= (int)$friend['id'] ?>" class="mb-3">
                                <?php 
                                $avatarClasses = 'h-32 w-32';
                                $avatarSvgSize = 128;
                                render_avatar($friend, $avatarClasses, $avatarSvgSize); 
                                ?>
                            </a>
                            <a href="/profile/<?= (int)$friend['id'] ?>" class="font-semibold text-gray-100 light:text-gray-800 hover:underline transition-text text-center mb-2">
                                <?= htmlspecialchars($friend['full_name'] ?? $friend['username']) ?>
                            </a>
                            <p class="text-gray-400 light:text-gray-600 text-sm mb-4 text-center transition-text">
                                <?php
                                if (isset($isOwnProfile) && $isOwnProfile) {
                                    echo htmlspecialchars(mb_strimwidth($friend['bio'] ?? 'Friend', 0, 25, '...')) ?: 'Friend';
                                } else {
                                    echo 'Friend';
                                }
                                ?>
                            </p>
                            
                            <div class="flex gap-2 w-full relative mt-auto pt-2 border-t border-dark-600 light:border-gray-300">
                                <?php 
                                $relationshipWithViewer = $friend['relationship_with_viewer'] ?? 'not_friends';
                                $currentFriendIdOnCard = (int)$friend['id'];
                                $loggedInUserId = (int)($_SESSION['user_id'] ?? 0);

                                $primaryBtnClasses = "flex-1 h-9 px-3 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors flex items-center justify-center";
                                $optionsBtnClasses = "h-9 w-9 flex items-center justify-center text-sm font-medium text-gray-200 light:text-gray-800 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-lg transition-colors friend-options-btn";
                                $disabledBtnClasses = "flex-1 h-9 px-3 text-sm font-medium text-gray-400 light:text-gray-500 bg-dark-500 light:bg-gray-300 rounded-lg cursor-not-allowed flex items-center justify-center";
                                $acceptBtnClasses = "flex-1 h-9 px-3 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors flex items-center justify-center";
                                $secondaryBtnClasses = "flex-1 h-9 px-3 text-sm font-medium text-gray-200 light:text-gray-800 bg-dark-600 light:bg-gray-200 hover:bg-dark-500 light:hover:bg-gray-300 rounded-lg transition-colors flex items-center justify-center";

                                if ($currentFriendIdOnCard === $loggedInUserId) {
                                    if (! (isset($isOwnProfile) && $isOwnProfile) ) {
                                        echo '<span class="text-xs text-gray-500 flex-1 text-center py-2">(This is you)</span>';
                                    }
                                } elseif (isset($isOwnProfile) && $isOwnProfile) { 
                                ?>
                                    <a href="/messages/with/<?= $currentFriendIdOnCard ?>" class="<?= $primaryBtnClasses ?>">Message</a>
                                    <button class="<?= $optionsBtnClasses ?>" data-friend-id="<?= $currentFriendIdOnCard ?>"><i class="fas fa-ellipsis-h"></i></button>
                                <?php 
                                } else { 
                                    switch ($relationshipWithViewer) {
                                        case 'friends':
                                ?>
                                            <a href="/messages/with/<?= $currentFriendIdOnCard ?>" class="<?= $primaryBtnClasses ?>">Message</a>
                                            <button class="<?= $optionsBtnClasses ?>" data-friend-id="<?= $currentFriendIdOnCard ?>"><i class="fas fa-ellipsis-h"></i></button>
                                <?php
                                            break;
                                        case 'request_sent':
                                ?>
                                            <button disabled class="<?= $disabledBtnClasses ?>">Request Sent</button>
                                <?php
                                            break;
                                        case 'request_received':
                                            if (isset($friend['pending_request_id_from_them'])) {
                                ?>
                                            <form action="/friends/request/<?= (int)$friend['pending_request_id_from_them'] ?>/accept" method="POST" class="flex-1">
                                                <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="<?= $acceptBtnClasses ?>">Accept</button>
                                            </form>
                                            <form action="/friends/request/<?= (int)$friend['pending_request_id_from_them'] ?>/decline" method="POST" class="flex-1">
                                                <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="<?= $secondaryBtnClasses ?>">Decline</button>
                                            </form>
                                <?php       } else { echo '<span class="text-xs text-gray-400 flex-1 text-center py-2">Request Pending</span>'; }
                                            break;
                                        case 'not_friends': 
                                        default:
                                ?>
                                            <form action="/friends/add/<?= $currentFriendIdOnCard ?>" method="POST" class="flex-1">
                                                <!-- *** FIX: ADDED CSRF TOKEN *** -->
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="<?= $primaryBtnClasses ?>">Add Friend</button>
                                            </form>
                                <?php
                                            break;
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="allFriendsNoResults" class="text-center py-12 text-gray-400 light:text-gray-600 text-lg hidden">
                        <i class="fas fa-search text-4xl mb-4"></i>
                        <p>No friends match your search.</p>
                    </div>
                    
                    <?php 
                    $pagination_profile_id = $profileUser['id'] ?? 0; 
                    if (isset($allFriends) && count($allFriends) >= 8 && $pagination_profile_id > 0):
                    ?>
                    <div class="mt-6 flex justify-center">
                        <a href="/friends/<?= $pagination_profile_id ?>?page=2" class="bg-dark-700 light:bg-gray-100 hover:bg-dark-600 light:hover:bg-gray-200 text-gray-200 light:text-gray-800 py-2 px-6 rounded-lg font-medium transition-colors border border-dark-600 light:border-gray-200">
                            See More Friends
                        </a>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                     <div class="text-center py-12">
                        <i class="fas fa-user-friends text-4xl text-gray-400 light:text-gray-600 mb-4"></i>
                        <p class="text-gray-400 light:text-gray-600 text-lg">
                            <?php /* ... Your "no friends yet" message logic ... */ ?>
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- ... (Rest of your file: Chatbox, Modals, JavaScript) remains unchanged ... -->
    <!-- Chatbox Container -->
    <div id="chatboxContainer" class="chatbox-container"></div>
    <!-- Mobile Navigation -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-dark-800 light:bg-white shadow-lg border-t border-dark-700 light:border-gray-200 z-50 transition-bg transition-border">
        <div class="flex justify-around py-2">
            <a href="/profile/<?= $current_user_id ?>" class="p-3 text-gray-400 light:text-gray-600 hover:text-primary-600 transition-colors"><i class="fas fa-user-circle text-xl"></i></a>
            <a href="/sai/" class="p-3 text-primary-600"><i class="fas fa-atom text-xl"></i></a>
            <a href="/contacts" class="p-3 text-gray-400 light:text-gray-600 hover:text-primary-600 transition-colors"><i class="fas fa-address-book text-xl"></i></a>
            <a href="#search" class="p-3 text-gray-400 light:text-gray-600 hover:text-primary-600 transition-colors"><i class="fas fa-search text-xl"></i></a>
            <button onclick="toggleMobileMenu()" class="p-3 text-gray-400 light:text-gray-600 hover:text-primary-600 transition-colors"><i class="fas fa-bars text-xl"></i></button>
        </div>
    </nav>
    <!-- Notifications Modal -->
    <div id="notificationContentModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle" aria-hidden="true">
        <div class="relative p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-xl rounded-lg bg-white dark:bg-dark-800 transform transition-all sm:my-8 flex flex-col max-h-[90vh]">
            <div class="flex justify-between items-center pb-3 border-b dark:border-dark-600 flex-shrink-0">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100" id="notificationModalTitle">Notification Details</h3>
                <button id="notificationModalCloseBtn" type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-dark-600 dark:hover:text-white" aria-label="Close modal"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg></button>
            </div>
            <div class="mt-4 overflow-y-auto flex-grow">
                <div id="notificationModalBody"><p class="text-gray-700 dark:text-gray-300">Loading notification content...</p></div>
            </div>
            <div class="mt-6 pt-4 border-t dark:border-dark-600 flex justify-end space-x-3 flex-shrink-0">
                <button id="notificationModalDeclineBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-dark-800 hidden">Decline</button>
                <button id="notificationModalAcceptBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-dark-800 hidden">Accept</button>
                <a href="#" id="notificationModalViewLink" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none"></a>
            </div>
        </div>
    </div>
    <!-- Delete comments Confirmation Modal -->
    <div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full flex items-center justify-center hidden z-[100]" aria-labelledby="deleteConfirmModalTitle" role="dialog" aria-modal="true">
        <div class="relative p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-dark-800">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900"><i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i></div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mt-2" id="deleteConfirmModalTitle">Delete Confirmation</h3>
                <div class="mt-2 px-7 py-3"><p class="text-sm text-gray-600 dark:text-gray-300" id="deleteConfirmModalMessage">Are you sure you want to proceed? This action cannot be undone.</p></div>
                <div class="items-center px-4 py-3 space-y-2 sm:space-y-0 sm:flex sm:justify-center sm:space-x-4">
                    <button id="deleteConfirmModalConfirmBtn" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-dark-800">Delete</button>
                    <button id="deleteConfirmModalCancelBtn" type="button" class="w-full sm:w-auto mt-2 sm:mt-0 inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-600 shadow-sm px-4 py-2 bg-white dark:bg-dark-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-dark-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-dark-800">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    window.APP_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
    window.APP_USER_FULL_NAME = <?php echo json_encode($_SESSION['user_full_name'] ?? 'You'); ?>;
    window.APP_USER_AVATAR = <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>;
    const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
    const currentUserData = {
        id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
        fullName: <?php echo json_encode($_SESSION['user_full_name'] ?? 'Guest'); ?>,
        username: <?php echo json_encode($_SESSION['user_username'] ?? 'guest'); ?>,
        profilePicture: <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>
    };
    </script>
    <script src="/assets/client/js/F.js"></script>
    <script src="/assets/client/js/mf.js"></script>
    <script src="/assets/client/js/headmanager.js"></script>
    <script src="/assets/client/js/notifications.js"></script>
    <script src="/assets/client/js/typeahead-chat.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationBtn && notificationDropdown) {
            notificationBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                const isHidden = notificationDropdown.classList.contains('hidden');
                if (isHidden) {
                    notificationDropdown.classList.remove('hidden');
                } else {
                    notificationDropdown.classList.add('hidden');
                }
            });
        }
        document.addEventListener('click', (event) => {
            if (notificationDropdown && !notificationDropdown.classList.contains('hidden')) {
                if (!notificationDropdown.contains(event.target) && !notificationBtn.contains(event.target)) {
                    notificationDropdown.classList.add('hidden');
                }
            }
        });
    });
    </script>
</body>
</html>