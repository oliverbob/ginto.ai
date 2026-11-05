<?php
// Assuming session_start() and getCsrfToken() are handled before this file is included.
$csrfToken = getCsrfToken();

// Logic to get the logged-in user's avatar.
$loggedInUserAvatar = $_SESSION['user_profile_picture'] ?? '';
if (empty($loggedInUserAvatar)) {
    $fullName = $_SESSION['user_full_name'] ?? 'User';
    $initial = strtoupper(substr($fullName, 0, 1));
    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">
        <circle cx="20" cy="20" r="20" fill="#4B5563"/>
        <text x="50%" y="50%" text-anchor="middle" dy=".35em"
              font-family="Arial, sans-serif" font-size="20"
              fill="#ffffff">' . htmlspecialchars($initial) . '</text>
    </svg>';
    $loggedInUserAvatar = 'data:image/svg+xml;base64,' . base64_encode($svg);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    <link href="/assets/favicon/favicon.ico" rel="shortcut icon" type="image/x-icon" />
    <title>Profile Page - <?php echo htmlspecialchars($profileUser['full_name'] ?? 'User'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        facebook: { DEFAULT: '#1877F2', dark: '#0B64C9', light: '#E7F3FF' },
                        dark: { 800: '#1E1E1E', 700: '#2D2D2D', 600: '#3A3A3A', 500: '#4A4A4A' },
                        pink: { 600: '#DB2777', 700: '#BE185D' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        .dark ::-webkit-scrollbar-track { background: #2D2D2D; }
        .dark ::-webkit-scrollbar-thumb { background: #555; }
        .dark ::-webkit-scrollbar-thumb:hover { background: #777; }

        /* Story gradient */
        .story-gradient { background: linear-gradient(to bottom, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }

        /* Custom animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Profile */
        .profile-cover { height: 350px; background-size: cover; background-position: center; background-color: #cccccc; transition: background-image 0.3s ease-in-out; }
        .profile-picture-container { position: relative; display: inline-block; }
        .profile-picture { width: 168px; height: 168px; border-radius: 50%; border: 4px solid white; margin-top: -84px; position: relative; z-index: 10; background-color: #e0e0e0; }
        .profile-picture-edit-icon { position: absolute; bottom: 10px; right: 10px; background-color: rgba(0, 0, 0, 0.6); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: opacity 0.2s ease-in-out; z-index: 11; }
        .profile-picture-container:hover .profile-picture-edit-icon { opacity: 1; }
        .profile-nav { border-bottom: 1px solid #dddfe2; }
        .profile-nav-item { padding: 16px; font-weight: 600; color: #65676B; }
        .profile-nav-item.active { color: #1877F2; border-bottom: 3px solid #1877F2; }
        .profile-nav-item:hover { background-color: #f0f2f5; }
        .intro-card { border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .friend-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .friend-item { position: relative; padding-top: 100%; }
        .friend-item img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }

        /* Mobile layout adjustments */
        @media (max-width: 1023px) { .profile-cover { height: 250px; } .profile-picture { width: 120px; height: 120px; margin-top: -60px; } .profile-picture-edit-icon { width: 28px; height: 28px; bottom: 8px; right: 8px; } .profile-nav-item { padding: 12px 8px; font-size: 0.9rem; } }
        @media (max-width: 640px) { .profile-cover { height: 200px; } .profile-picture { width: 100px; height: 100px; margin-top: -50px; } .profile-picture-edit-icon { width: 24px; height: 24px; font-size: 0.8rem; bottom: 6px; right: 6px;} .profile-nav { overflow-x: auto; white-space: nowrap; } .friend-grid { grid-template-columns: repeat(2, 1fr); } }

        /* Highlight & Loading */
        .highlight { background-color: #E3F2FD; font-weight: 600; }
        .dark .highlight { background-color: #2D3748; color: #E2E8F0; }
        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(0,0,0,.1); border-radius: 50%; border-top-color: #888; animation: spin 1s ease-in-out infinite; }
        .dark .loading-spinner { border: 3px solid rgba(255,255,255,.3); border-top-color: #fff; }
        
        /* Dark mode specific adjustments */
        .dark .profile-nav { border-bottom-color: #3A3A3A; }
        .dark .profile-nav-item { color: #B0B3B8; }
        .dark .profile-nav-item.active { color: #4599FF; border-bottom-color: #4599FF; }
        .dark .profile-nav-item:hover { background-color: #3A3A3A; }
        .dark .intro-card { background-color: #242526; box-shadow: 0 1px 2px rgba(0,0,0,0.3); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark-800 font-sans transition-colors duration-200">
    <?php require_once 'htmlparts/header.php'; ?>

    <main class="max-w-6xl mx-auto pt-16">
        <div id="profileCoverImage" class="profile-cover relative" style="background-image: url('<?php echo htmlspecialchars($profileUser['cover_photo'] ?? 'https://picsum.photos/1600/350?grayscale&blur=2'); ?>');">
            <input type="file" id="coverPhotoInput" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" style="display: none;">
            <?php if ($isOwnProfile) {?>
            <button id="editCoverPhotoButton" class="absolute bottom-4 right-4 bg-white dark:bg-dark-600 text-gray-800 dark:text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:bg-gray-100 dark:hover:bg-dark-500 transition-colors">
                <i class="fas fa-camera mr-2"></i><span id="editCoverButtonText">Edit cover photo</span>
            </button>
            <?php } ?>
        </div>

        <div class="bg-white dark:bg-dark-700 px-4">
            <div class="flex flex-col md:flex-row md:items-end">
                <div class="profile-picture-container">
                    <img id="profilePictureImg" src="<?php echo htmlspecialchars($profileUser['profile_picture'] ?? ''); ?>" alt="Profile" class="profile-picture">
                    <input type="file" id="profilePictureInput" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" style="display: none;">
                    <?php if ($isOwnProfile) {?>
                    <div id="editProfilePictureButton" class="profile-picture-edit-icon" title="Edit Profile Picture">
                        <i class="fas fa-camera"></i>
                    </div>
                    <?php } ?>
                </div>
                <div class="flex-1 md:ml-6 mt-4 md:mt-0">
                    <h1 id="profileUserName" class="text-3xl font-bold dark:text-white"><?php echo htmlspecialchars($profileUser['full_name'] ?? 'N/A'); ?></h1>
                    
                    <p class="text-gray-600 dark:text-gray-400">
                        <?= htmlspecialchars($totalFriendCount) ?> friend<?= ($totalFriendCount !== 1) ? 's' : '' ?>
                    </p>
                     <?php if ($isOwnProfile) {?>
                    <div class="flex space-x-2 mt-4">
                        <button class="bg-facebook hover:bg-facebook-dark text-white px-4 py-2 rounded-md font-semibold"><i class="fas fa-plus-circle mr-2"></i>Add to Story</button>
                        <button id="mainEditProfileBtn" class="bg-gray-200 dark:bg-dark-600 dark:text-white px-4 py-2 rounded-md font-semibold"><i class="fas fa-pen mr-2"></i>Edit Profile</button>
                    </div>
                    <?php } else { ?>
                    <div class="flex space-x-2 mt-4">
                        <?php
                        // ===================================================================
                        // START: NEW CONDITIONAL BUTTON LOGIC
                        // ===================================================================
                        switch ($relationshipStatus) {
                            
                            case 'self':
                                // This is your own profile, show "Edit Profile" etc.
                                ?>
                                <button class="bg-facebook hover:bg-facebook-dark text-white px-4 py-2 rounded-md font-semibold"><i class="fas fa-plus-circle mr-2"></i>Add to Story</button>
                                <button class="bg-gray-200 dark:bg-dark-600 dark:text-white px-4 py-2 rounded-md font-semibold"><i class="fas fa-pen mr-2"></i>Edit Profile</button>
                                <?php
                                break;

                            case 'friends':
                                ?>
                                <button disabled class="bg-gray-200 dark:bg-dark-600 text-green-600 dark:text-green-400 px-4 py-2 rounded-md font-semibold flex items-center">
                                    <i class="fas fa-check mr-2"></i> Friends
                                </button>

                                <!-- 
                                    =======================================================
                                    START: MODIFIED "UNFRIEND" BUTTON
                                    =======================================================
                                    - Changed from a <form> to a <button>
                                    - Added id="unfriendBtn"
                                    - Added data-user-id attribute with the profile user's ID
                                    =======================================================
                                -->
                                <button 
                                    id="unfriendBtn"
                                    data-user-id="<?= htmlspecialchars($profileUser['id']) ?>"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md font-semibold"
                                >
                                    Unfriend
                                </button>
                                <?php
                                break;

                            case 'request_sent':
                                // You have sent a request, show "Request Sent".
                                ?>
                                <button disabled class="bg-gray-200 dark:bg-dark-600 text-black dark:text-white px-4 py-2 rounded-md font-semibold flex items-center">
                                    <i class="fas fa-user-clock mr-2"></i> Request Sent
                                </button>
                                <?php
                                break;
                            
                            case 'request_received':
                                // They sent you a request, show "Confirm" and "Decline" buttons.
                                // Note: These forms use your existing POST routes.
                                ?>
                                <div id="friendRequestActions" class="flex space-x-2">
                                    <form action="/friends/accept/<?= htmlspecialchars($friendshipRequestId) ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                        <button type="submit" class="bg-facebook hover:bg-facebook-dark text-white px-4 py-2 rounded-md font-semibold">
                                            Confirm
                                        </button>
                                    </form>
                                    <button
                                        type="button" 
                                        id="declineRequestBtn"
                                        data-request-id="<?= htmlspecialchars($friendshipRequestId) ?>"
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md font-semibold"
                                    >
                                        Decline
                                    </button>
                                </div>
                                <?php
                                break;

                            case 'not_friends':
                            case 'guest': // Treat guests and non-friends the same for this button
                            default:
                                // Not friends, show the AJAX "Add Friend" button.
                                ?>
                                <button 
                                    id="addFriendBtn" 
                                    data-user-id="<?= htmlspecialchars($profileUser['id'] ?? '') ?>"
                                    class="bg-facebook hover:bg-facebook-dark text-white px-4 py-2 rounded-md font-semibold flex items-center justify-center transition-colors duration-200"
                                >
                                    <i class="fas fa-user-plus mr-2"></i>
                                    <span>Add Friend</span>
                                </button>
                                <?php
                                break;
                        }
                        // ===================================================================
                        // END: NEW CONDITIONAL BUTTON LOGIC
                        // ===================================================================
                        ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <div class="profile-nav mt-4 flex space-x-1 overflow-x-auto scrollbar-hide">
                <a href="#" class="profile-nav-item active">Posts</a>
                <a href="#profileIntroCard" class="profile-nav-item">About</a>
                <a href="#userFriends" class="profile-nav-item">Friends</a>
                <a href="#userPhotos" class="profile-nav-item">Photos</a>
                <a href="#userVideos" class="profile-nav-item">Videos</a>
                <a href="#userCheckIns" class="profile-nav-item">Check-ins</a>
                <a href="#" class="profile-nav-item">More <i class="fas fa-chevron-down ml-1"></i></a>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row mt-4 space-y-4 lg:space-y-0 lg:space-x-4">
            <div class="w-full lg:w-1/3 space-y-4">
                <!-- MODIFIED INTRO CARD (WITH HEADLINE & BIO) -->
                <div id="profileIntroCard" class="intro-card bg-white dark:bg-dark-700 p-4 rounded-lg shadow-md scroll-mt-20">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold dark:text-white">Intro</h2>
                        <?php if ($isOwnProfile): ?>
                        <button id="editIntroButton" aria-label="Edit Intro" class="text-gray-500 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 p-2 rounded-full -mr-2">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- View State -->
                    <div id="introViewState" class="space-y-3">
                        <!-- Headline Display -->
                        <p id="viewHeadline" class="text-center font-semibold text-gray-700 dark:text-gray-300 <?= empty($profileUser['headline']) ? 'hidden' : '' ?>">
                            <?= htmlspecialchars($profileUser['headline'] ?? ''); ?>
                        </p>

                        <!-- Bio Display -->
                        <div id="viewBioContainer">
                            <?php if (!empty($profileUser['bio'])): ?>
                                <p id="viewBio" class="text-center text-gray-600 dark:text-gray-400"><?= nl2br(htmlspecialchars($profileUser['bio'])); ?></p>
                            <?php elseif ($isOwnProfile): ?>
                                <button id="addBioButton" class="w-full bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 text-black dark:text-white font-semibold py-2 px-4 rounded-md">Add Bio</button>
                            <?php endif; ?>
                        </div>

                        <!-- Other Details -->
                        <ul class="pt-2">
                            <?php if (!empty($profileUser['work_place'])): ?>
                                <li id="viewWorkPlaceItem" class="flex items-center mt-2"><i class="fas fa-briefcase text-gray-500 dark:text-gray-400 w-6"></i><span id="viewWorkPlace" class="ml-2 dark:text-white">Works at <?= htmlspecialchars($profileUser['work_place']); ?></span></li>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['education'])): ?>
                                <li id="viewEducationItem" class="flex items-center mt-2"><i class="fas fa-graduation-cap text-gray-500 dark:text-gray-400 w-6"></i><span id="viewEducation" class="ml-2 dark:text-white">Studied at <?= htmlspecialchars($profileUser['education']); ?></span></li>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['current_city'])): ?>
                                <li id="viewCurrentCityItem" class="flex items-center mt-2"><i class="fas fa-home text-gray-500 dark:text-gray-400 w-6"></i><span id="viewCurrentCity" class="ml-2 dark:text-white">Lives in <?= htmlspecialchars($profileUser['current_city']); ?></span></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Edit State (Initially Hidden) -->
                    <div id="introEditState" class="hidden space-y-4">
                        <div>
                            <label for="inputHeadline" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Headline</label>
                            <input type="text" id="inputHeadline" value="<?= htmlspecialchars($profileUser['headline'] ?? ''); ?>" placeholder="Add a professional headline" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-dark-600 dark:text-white px-3 py-2">
                        </div>

                        <div>
                            <label for="inputBio" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bio</label>
                            <textarea id="inputBio" placeholder="Describe yourself..." rows="4" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-dark-600 dark:text-white px-3 py-2"><?= htmlspecialchars($profileUser['bio'] ?? ''); ?></textarea>
                        </div>
                        <hr class="dark:border-dark-500"/>
                        <div>
                            <label for="inputWorkPlace" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Workplace</label>
                            <input type="text" id="inputWorkPlace" value="<?= htmlspecialchars($profileUser['work_place'] ?? ''); ?>" placeholder="Where do you work?" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-dark-600 dark:text-white px-3 py-2">
                        </div>
                        <div>
                            <label for="inputEducation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Education</label>
                            <input type="text" id="inputEducation" value="<?= htmlspecialchars($profileUser['education'] ?? ''); ?>" placeholder="Where did you study?" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-dark-600 dark:text-white px-3 py-2">
                        </div>
                        <div>
                            <label for="inputCurrentCity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current City</label>
                            <input type="text" id="inputCurrentCity" value="<?= htmlspecialchars($profileUser['current_city'] ?? ''); ?>" placeholder="Where do you live?" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-dark-600 dark:text-white px-3 py-2">
                        </div>
                        <div class="flex justify-end space-x-2 pt-2">
                            <button id="cancelIntroButton" class="bg-gray-200 dark:bg-dark-600 hover:bg-gray-300 dark:hover:bg-dark-500 text-black dark:text-white font-semibold py-2 px-4 rounded-md">Cancel</button>
                            <button id="saveIntroButton" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md flex items-center justify-center w-24">
                                <span class="button-text">Save</span>
                                <i class="fas fa-spinner fa-spin hidden ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Photos Card -->
                <div id="userPhotos" class="intro-card bg-white dark:bg-dark-700 p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold dark:text-white">Photos</h2>
                        <a href="/friends/<?= htmlspecialchars($profileUser['id'] ?? '') ?>" class="text-facebook hover:underline">See All Photos</a>
                    </div>
                    <!--
                        MODIFICATION:
                        - Removed the PHP loop.
                        - Added an id="photoGridContainer" to the grid div.
                        - Added a "Loading..." message that will be replaced by JS.
                    -->
                    <div id="photoGridContainer" class="grid grid-cols-3 gap-1">
                        <p class="col-span-3 text-sm text-gray-500 dark:text-gray-400">Loading photos...</p>
                    </div>
                </div>

                <!-- Videos Card (Add this to your profile.php view) -->
                <div id="userVideos" class="intro-card bg-white dark:bg-dark-700 p-4 mt-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold dark:text-white">Videos</h2>
                        <a href="#" class="text-blue-500 hover:underline">See All Videos</a>
                    </div>
                    <!-- The grid will be populated by JavaScript -->
                    <div id="videoGridContainer" class="grid grid-cols-3 gap-2">
                        <p class="col-span-3 text-sm text-gray-500 dark:text-gray-400">Loading videos...</p>
                    </div>
                </div>
                <!-- Friends Card -->
                <div id="userFriends" class="intro-card bg-white dark:bg-dark-700 p-4 pb-10">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold dark:text-white">Friends</h2>
                        <a href="/friends/<?= htmlspecialchars($profileUser['id'] ?? '') ?>" class="text-facebook hover:underline">See All Friends</a>
                    </div>
                    
                    <!-- Placeholder for the friend count -->
                    <p id="friendCountText" class="text-gray-600 dark:text-gray-400 mb-4"> </p> 
                    
                    <!-- Container for the friend grid -->
                    <div id="friendGridContainer" class="friend-grid">
                        <!-- Friends will be loaded here by JavaScript -->
                        <!-- Loading Skeletons -->
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <div class="friend-item bg-gray-200 dark:bg-dark-600 rounded-md animate-pulse"></div>
                        <?php endfor; ?>
                    </div>
                </div>
                <!-- Check-ins Card (Add this to profile.php) -->
                <div id="userCheckIns" class="intro-card bg-white dark:bg-dark-700 p-4 mt-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold dark:text-white">Check-ins</h2>
                        <a href="#" class="text-blue-500 hover:underline">See All Check-ins</a>
                    </div>
                    <!-- A list container is better than a grid for check-ins -->
                    <div id="checkinListContainer" class="space-y-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Loading check-ins...</p>
                    </div>
                </div>


            </div>
            <div class="w-full lg:w-2/3 space-y-4">
                
                <!-- POST CREATION BLOCK -->
                <div class="bg-white dark:bg-dark-700 rounded-lg shadow p-4">
                    <div class="flex items-center space-x-2">
                        <img id="createPostAvatar" src="<?= htmlspecialchars($loggedInUserAvatar) ?>" alt="User" class="w-10 h-10 rounded-full">
                        <div>
                            <p class="font-semibold dark:text-white"><?= htmlspecialchars($_SESSION['user_full_name'] ?? 'User') ?></p>
                            <div class="flex items-center bg-gray-100 dark:bg-dark-600 rounded-md px-1 py-1 text-xs">
                                <i class="fas fa-globe-americas text-gray-500 dark:text-gray-400"></i>
                                <select id="profile-post-visibility-select" class="bg-transparent border-none focus:outline-none ml-1 dark:text-white dark:bg-dark-600" aria-label="Post audience">
                                    <option value="public">Public</option>
                                    <option value="friends">Friends</option>
                                    <option value="private">Only me</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php
                        $placeholderText = $isOwnProfile
                            ? "What's on your mind? Or ask Sai to write for you..."
                            : "Write something to " . htmlspecialchars($profileUser['full_name'] ?? 'this user') . "...";
                    ?>
                    <textarea id="profile-post-textarea" placeholder="<?= $placeholderText ?>" class="w-full mt-4 p-2 border border-gray-300 dark:border-dark-500 rounded-md focus:outline-none focus:ring-2 focus:ring-facebook dark:bg-dark-600 dark:text-white resize-none" rows="5"></textarea>
                    
                    <!-- START: ADDED THIS BLOCK FOR LOCATION DISPLAY -->
                    <div id="postLocationDisplay" class="flex items-center justify-between bg-gray-100 dark:bg-dark-600 p-2 rounded-md mt-2 hidden">
                        <div class="flex items-center min-w-0">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2 flex-shrink-0"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-300 mr-1">Checked in at:</span>
                            <strong id="locationNameText" class="text-sm font-semibold dark:text-white truncate"></strong>
                        </div>
                        <button id="removeLocationBtn" class="text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-500 text-sm ml-2 p-1" aria-label="Remove location">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <!-- END: ADDED THIS BLOCK -->
                    
                    <div class="border border-gray-200 dark:border-dark-600 rounded-lg p-3 mt-2">
                        <div class="flex justify-between items-center">
                            <p class="font-medium dark:text-white">Add to your post</p>
                            <div class="flex space-x-2">
                                <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-green-500 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="Add photo/video"><i class="fas fa-images"></i></button>
                                <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-red-500 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="Tag people"><i class="fas fa-user-tag"></i></button>
                                <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-yellow-500 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="Add feeling/activity"><i class="fas fa-smile"></i></button>
                                <!-- THIS IS THE NEW BUTTON -->
                                <button id="addLocationBtn" class="w-9 h-9 rounded-full hover:bg-gray-200 dark:hover:bg-dark-600 flex items-center justify-center text-red-500" aria-label="Check in">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>
                                <button class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-600 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-dark-500" aria-label="More options"><i class="fas fa-ellipsis-h"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="flex mt-4 space-x-3">
                        <button id="profile-post-ask-sai-btn" class="flex-1 bg-pink-600 hover:bg-pink-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center justify-center space-x-2 transition-colors duration-150">
                            <i class="fas fa-magic"></i>
                            <span>Ask Sai</span>
                        </button>
                        <button id="profile-post-submit-btn" class="flex-1 bg-facebook hover:bg-facebook-dark text-white font-semibold py-2 px-4 rounded-lg transition-colors duration-150">
                            Post
                        </button>
                    </div>
                </div>
                <div id="postsContainer"></div>
                <div id="loadingIndicator" class="text-center py-4 hidden"></div>
            </div>
        </div>
    </main>

    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-dark-700 shadow-lg border-t border-gray-200 dark:border-dark-600 z-50">
       <!-- Mobile nav links -->
    </nav>

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
                    <img src="<?= $loggedInUserAvatar ?>" alt="User" class="w-10 h-10 rounded-full">
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

        <!-- Mobile Bottom Navigation -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-dark-700 shadow-lg border-t border-gray-200 dark:border-dark-600 z-50">
        <div class="flex justify-around py-2">
            <a href="/" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Contacts">
                <i class="fas fa-home text-xl"></i>
            </a>
            <a href="/dashboard" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Dashboard">
                <i class="fas fa-dashboard text-xl"></i>
            </a>
            <a href="/sai/" class="p-3 text-facebook" aria-label="Sai Chat">
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

    

<script>

window.APP_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
window.APP_USER_FULL_NAME = <?php echo json_encode($_SESSION['user_full_name'] ?? 'You'); ?>;
window.APP_USER_AVATAR = <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>;

const IS_OWN_PROFILE = <?php echo json_encode($isOwnProfile ?? false); ?>;
const PROFILE_USER_DATA = <?php echo json_encode($profileUser ?? null); ?>;
const profileUserId = <?php echo json_encode($profileUser['id'] ?? null); ?>;
const IS_PROFILE_PAGE = true; 
const LOGGED_IN_USER_DATA = <?php echo json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_full_name' => $_SESSION['user_full_name'] ?? 'Guest',
    'profile_picture' => $_SESSION['user_profile_picture'] ?? null
]); ?>;


// =========================================================================
// END: THE CRUCIAL FIX
// =========================================================================
</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
    <script src="/assets/client/js/profile.js"></script>
    <script src="/assets/client/js/smartfed.js"></script>
    <!-- <script src="/assets/client/js/stories.js"></script> -->
    <script src="/assets/client/js/headmanager.js"></script>
    <script src="/assets/client/js/notifications.js"></script>
    <script src="/assets/client/js/typeahead-chat.js"></script>
    <script src="/assets/client/js/contacts.js"></script>
    <script src="/assets/client/js/feedmanager.js"></script>
    <script src="/assets/client/js/mediamanager.js"></script>

<script>
    // document.addEventListener('DOMContentLoaded', () => {
    //     const notificationBtn = document.getElementById('notificationBtn');
    //     const notificationDropdown = document.getElementById('notificationDropdown');
    //     if (notificationBtn && notificationDropdown) {
    //         notificationBtn.addEventListener('click', (event) => {
    //             event.stopPropagation();
    //             const isHidden = notificationDropdown.classList.contains('hidden');
    //             if (isHidden) {
    //                 notificationDropdown.classList.remove('hidden');
    //             } else {
    //                 notificationDropdown.classList.add('hidden');
    //             }
    //         });
    //     }
    //     document.addEventListener('click', (event) => {
    //         if (notificationDropdown && !notificationDropdown.classList.contains('hidden')) {
    //             if (!notificationDropdown.contains(event.target) && !notificationBtn.contains(event.target)) {
    //                 notificationDropdown.classList.add('hidden');
    //             }
    //         }
    //     });
    // });
</script>
</body>
</html>