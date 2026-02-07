<?php

// Assume $router is an instance of \Core\Router, defined in your main index.php or bootstrap file.

// 1. Register the CSRF middleware.
// This tells your router what 'csrf' means. It points to the handle() method in your middleware class.
$router->filter('csrf', ['App\Middleware\CsrfMiddleware', 'handle']);


// 2. Create a protected group for all POST routes.
// The 'before' => 'csrf' instruction tells the router to run the CSRF middleware
// BEFORE executing any route defined inside this group.
$router->group(['before' => 'csrf'], function($router) {

    // --- MOVE ALL YOUR POST ROUTES HERE ---
    $router->post('/login', 'AuthController@login');
    $router->post('/register', 'AuthController@register');
    $router->post('/code', 'CodeController@code');
    $router->post('/post', 'HomeController@post');
    // Duplicated endpoints under /social to avoid root path conflicts
    $router->post('/social/post', 'SocialController@post');
    $router->post('/feed', 'HomeController@feed');
    $router->post('/social/feed', 'SocialController@feed');
    $router->post('/post', 'ProfileController@post');
    $router->post('/codesave', 'CodeController@codesave');
    $router->post('/search', 'SearchController@search');
    $router->post('/chat/conversation/direct', 'ChatController@direct');
    $router->post('/chat/message', 'ChatController@sendMessageApi');
    $router->post('/chat/conversation/read', 'ChatController@markConversationAsReadApi');
    $router->post('/chat/group', 'ChatController@createGroupConversationApi');
    $router->post('/chat/conversation/group/create', 'ChatController@createGroupConversationApi');
    $router->post('/search/chat-participants', 'SearchController@search');
    $router->post('/contacts', 'ContactsController@contacts');
    $router->post('/contacts/statuses', 'ContactsController@statuses');
    $router->post('/user/activity', 'ContactsController@activity');
    $router->post('/post/like', 'ActivitiesController@toggleLike');
    $router->post('/post/comment', 'ActivitiesController@addComment');
    $router->post('/post/{id:\d+}/comments', 'ActivitiesController@getComments');
    $router->post('/post/{id:\d+}/delete', 'ActivitiesController@deletePost');
    $router->post('/post/{id:\d+}/update', 'ActivitiesController@editPostById');
    $router->post('/post/share', 'ActivitiesController@createSharePost');
    $router->post('/post/comments/{id:\d+}/delete', 'ActivitiesController@deleteCommentById');
    $router->post('/post/comments/{id:\d+}/edit', 'ActivitiesController@editCommentById');
    $router->post('/post/notifications/mark-all-read', 'ActivitiesController@markAllNotificationsAsRead');
    $router->post('/post/notifications/{id:\d+}/mark-read', 'ActivitiesController@markNotificationAsRead');
    $router->post('/upload', 'UploadController@upload');
    $router->post('/post/create_with_media', 'UploadController@createPostWithMedia');
    $router->post('/post/create_with_stream', 'StreamController@createPostWithStream');
    $router->post('/post/stream', 'StreamController@stream');
    $router->post('/post/stories', 'StoriesController@storiesView');
    $router->post('/post/stories/create', 'StoriesController@createStoryWithMedia');
    $router->post('/post/stories/delete/{storyId:\d+}', 'StoriesController@deleteStory');
    $router->post('profile/cover', 'UploadController@cover');
    $router->post('profile/picture', 'UploadController@profilePicture');
    $router->post('/profile/{id:\d+}', 'ProfileController@profile');
    $router->post('/profile/{id:\d+}/feed', 'ProfileController@feed');
    $router->post('/friends/unfriend/{id:\d+}', 'FriendsController@unfriend');
    $router->post('/friends/suggestion/add/{id:\d+}', 'FriendsController@sendRequest');
    $router->post('/friends/suggestion/remove/{id:\d+}', 'FriendsController@removeSuggestion');
    $router->post('/friends/accept/{id:\d+}', 'FriendsController@confirm');
    $router->post('/friends/decline/{id:\d+}', 'FriendsController@decline');
    $router->post('/friends/notifications/{id:\d+}/accept', 'FriendsController@confirmNotification');
    $router->post('/friends/notifications/{id:\d+}/decline', 'FriendsController@declineRequestNotification');
    $router->post('/friends/unfriending/{id:\d+}', 'FriendsController@unfriendApi');
    $router->post('/friends/requesting/{id:\d+}', 'FriendsController@sendRequestApi');
    $router->post('/friends/declining/{id:\d+}', 'FriendsController@declineRequestApi');
    $router->post('/profile/intro', 'ProfileController@updateIntro');
    $router->post('/location/search', 'LocationController@search');
    $router->post('/gaming/typing/save-progress', 'GameController@saveProgress');
    $router->post('/admin/update-user-status', 'AdminController@updateUserStatus');
    // Marketplace
    $router->post('/marketplace', 'MarketPlaceController@marketFeed');

    // SmartFi Vendo
    $router->post('/saifi', 'SmartFiController@status');

    // --- NEW ROUTE FOR USER WIFI PURCHASE ---
    // A logged-in user with a valid CSRF token will call this to buy time.

    // --- NEW ROUTE FOR SMARTFI TIER PURCHASE PROCESSING ---
    $router->post('/buytier/process', 'SmartFiController@processTierPurchase');
});


// 3. Keep all GET routes outside the protected group.
// These routes do not need CSRF protection because they don't change data.
$router->get('/test', 'URLMDController@test');
$router->get('/', 'AuthController@home');
$router->get('/login', 'AuthController@showLoginForm');
$router->get('/logout', 'AuthController@logout');
$router->get('/dashboard', 'AuthController@dashboard');
$router->get('/iconcaptcha', 'AuthController@iconcaptcha');
$router->get('/register', 'AuthController@register');
$router->get('/classroom', 'AuthController@classroom');
$router->get('/code', 'CodeController@code');

// Simple PHP-based API endpoints (replacement for saichat/nodejs API)
$router->get('/api', 'ApiController@index');
$router->post('/api', 'ApiController@post');
$router->get('/api/messages', 'ApiController@getMessages');

$router->get('/feed', 'HomeController@feed');
$router->get('/social/feed', 'SocialController@feed');
$router->get('/codesave', 'CodeController@codesave');
$router->get('/search', 'SearchController@search');
$router->get('/chat/conversation/direct', 'ChatController@direct');
$router->get('/chat/messages', 'ChatController@getConversationMessagesApi');
$router->get('/chat/conversations', 'ChatController@getUserConversationsApi');
$router->get('/chat/conversation/group/create', 'ChatController@createGroupConversationApi');
$router->get('/search/chat-participants', 'SearchController@search');
$router->get('/contacts', 'ContactsController@contacts');
$router->get('/post/like', 'ActivitiesController@toggleLike');
$router->get('/post/comment', 'ActivitiesController@addComment');
$router->get('/post/{id:\d+}/comments', 'ActivitiesController@getComments');
$router->get('/post/{id:\d+}/likes', 'ActivitiesController@getLikes');
$router->get('/post/{id:\d+}/debug', 'ActivitiesController@debugComments');
$router->get('/post/{id:\d+}', 'HomeController@getPostById');
$router->get('/social/post/{id:\d+}', 'SocialController@getPostById');
$router->get('/post/header-data', 'ActivitiesController@getHeaderData');
$router->get('/post/notifications/{id:\d+}/mark-read', 'ActivitiesController@markNotificationAsRead');
$router->get('/post/user/{id:\d+}/avatar-info', 'ActivitiesController@getUserAvatarInfo');
$router->get('/upload', 'UploadController@upload');
$router->get('/post/stream', 'StreamController@stream');
$router->get('/post/stories', 'StoriesController@storiesView');
$router->get('/post/stories/active', 'StoriesController@getActiveStories');
$router->get('/post/stories/delete/{storyId:\d+}', 'StoriesController@deleteStory');
$router->get('profile/cover', 'UploadController@cover');
$router->get('profile/picture', 'UploadController@profilePicture');
$router->get('/profile/{id:\d+}', 'ProfileController@profile');
$router->get('/profile/{id:\d+}/feed', 'ProfileController@feed');
$router->get('/friends', 'FriendsController@friends');
$router->get('/friends/{id:\d+}', 'FriendsController@friends');
// Migrations endpoint (development/admin only)
$router->get('/migrations', 'MigrationsController@install');
$router->get('/friends/suggestions/search', 'FriendsController@searchSuggestionsAPI');
$router->get('/friends/user-friends', 'FriendsController@searchUserFriendsAPI');
$router->get('/friends/mutual/{id:\d+}', 'FriendsController@mutualFriendsAPI');
$router->get('/ads/featured', 'HomeController@showAdsEndpoint');
$router->get('/social/ads/featured', 'SocialController@showAdsEndpoint');
$router->get('/profile/{id:\d+}/friends', 'FriendsController@getProfileFriendsApi');
$router->get('/profile/{id:\d+}/photos', 'ProfileController@getProfilePhotosApi');
$router->get('/profile/{id:\d+}/videos', 'ProfileController@getProfileVideosApi');
$router->get('/profile/{id:\d+}/checkins', 'ProfileController@getProfileCheckinsApi');

// Routes for Admin User Management for gaming (for now)
$router->get('/admin/search-users', 'AdminController@searchUsers');
$router->get('/gaming', 'GameController@game');
$router->get('/wh', 'WebhookController@webhook');
$router->get('/checksai', 'WebhookController@saiCodeCheck');
$router->get('/smartfi', 'SmartFiController@smartFi');
$router->get('/smartfi360', 'SmartFiController@smartFi360');

// Marketplace
$router->get('/marketplace', 'MarketPlaceController@marketFeed');

// Groups
$router->get('/groups', 'GroupController@groups');

// SmartFi Vendo
// These routes MUST be outside the 'csrf' group.
$router->post('/saifi/status', 'SmartFiController@statusUpdate');     // Vendo machine reports its status.
$router->post('/saifi/check-access', 'SmartFiController@checkAccess'); // Vendo machine checks if a user has paid.

// 4. Admin-Facing Route to see machine statuses (Session protected)
// This is a GET route, so it's naturally outside the CSRF group.
$router->get('/saifi/status', 'SmartFiController@getStatus');

// --- NEW ROUTE FOR THE WIFI PURCHASE PAGE ---
$router->get('/saifi/purchase', 'SmartFiController@showWifiPurchasePage');

// --- NEW ROUTE FOR VENDO WIFI PAGE ---
// This route will display the new page for buying WiFi time.
$router->get('/vendo', 'SmartFiController@showWifiPurchasePage');

// tier program
$router->get('/tier', 'SmartFiController@tier');
$router->get('/buytier', 'SmartFiController@buytier');
//$router->get('/buytier', 'SmartFiController@showBuyTierPage'); // Or a new controller