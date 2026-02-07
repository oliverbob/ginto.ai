<?php
namespace App\Controllers;

use Core\Controller;
use Medoo\Medoo;

// Define a default avatar path if not already globally available
if (!defined('DEFAULT_AVATAR_PATH')) {
    define('DEFAULT_AVATAR_PATH', '/images/default-avatar.png'); // Adjust this path
}

class FriendsController extends Controller {
    
    public function __construct()
    {
        parent::__construct();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function showLoginForm() {
        if (isset($_SESSION['user_id'])) {
            header('Location: /'); exit;
        }
        $this->view('auth/login');
    }

    public function login()
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /'); exit;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email and password are required.';
            $_SESSION['old_email'] = $email;
            header('Location: /login'); exit;
        }
        $user = $this->db->get("users", "*", ["email" => $email]);
        $passwordHash = $user['password_hash'] ?? $user['password'] ?? '';
        if ($user && password_verify($password, $passwordHash)) {
            if ($user['status'] === 'banned') {
                $_SESSION['error'] = 'Your account has been banned.'; header('Location: /login'); exit;
            }
            if ($user['status'] === 'inactive') {
                $_SESSION['error'] = 'Your account is inactive. Please contact support.'; header('Location: /login'); exit;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_full_name'] = $user['fullname'] ?? $user['full_name'] ?? 'User';
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_profile_picture'] = $user['avatar'] ?? $user['profile_picture'] ?? null;
            $this->db->update("users", ["last_login" => Medoo::raw('NOW()')], ["id" => $user['id']]);
            $redirectUrl = $_SESSION['redirect_url'] ?? '/';
            unset($_SESSION['redirect_url']);
            header('Location: ' . $redirectUrl); exit;
        }
        $_SESSION['error'] = 'Invalid email or password.';
        $_SESSION['old_email'] = $email;
        header('Location: /login'); exit;
    }

    /**
     * Displays a friends page.
     * - If $urlProfileId is null (route /friends), shows logged-in user's friends, requests, and suggestions.
     * - If $urlProfileId is provided (route /friends/{id}), shows that user's public friend list,
     *   and suggestions for the logged-in user, with appropriate action buttons for each listed friend.
     *
     * @param int|null $urlProfileId The ID from the URL (user whose friends to show), or null.
     */
    public function friends(?int $urlProfileId = null)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        if (!$loggedInUserId) {
            $_SESSION['error'] = 'You must be logged in to view this page.';
            $redirectTarget = $urlProfileId ? "/friends/{$urlProfileId}" : "/friends";
            $_SESSION['redirect_url'] = $redirectTarget;
            header('Location: /login');
            exit;
        }

        $profileWhoseFriendsToViewId = null;
        $isViewingOwnFullPage = false; 

        if ($urlProfileId === null) { // Matched /friends
            $profileWhoseFriendsToViewId = $loggedInUserId;
            $isViewingOwnFullPage = true;
        } else { // Matched /friends/{id}
            $urlProfileId = (int)$urlProfileId;
            if ($urlProfileId <= 0) {
                $_SESSION['error'] = "Invalid user ID specified.";
                header('Location: /friends'); exit;
            }
            $profileWhoseFriendsToViewId = $urlProfileId;
            $isViewingOwnFullPage = ($profileWhoseFriendsToViewId == $loggedInUserId);
        }

        // 1. Fetch details for the user whose friends list/profile context this page is about.
        $mainProfileUser = $this->db->get("users",
            ["id", "username", "fullname", "avatar", "bio", "status"],
            ["id" => $profileWhoseFriendsToViewId]
        );

        // map DB columns to expected keys used by views/controllers
        if ($mainProfileUser) {
            if (isset($mainProfileUser['fullname'])) $mainProfileUser['full_name'] = $mainProfileUser['fullname'];
            if (isset($mainProfileUser['avatar'])) $mainProfileUser['profile_picture'] = $mainProfileUser['avatar'];
        }

        if (!$mainProfileUser || ($mainProfileUser['status'] !== 'active' && !$isViewingOwnFullPage) ) {
            $_SESSION['error'] = "User profile (ID: {$profileWhoseFriendsToViewId}) not found or is inactive.";
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
        }
        if ($isViewingOwnFullPage && $mainProfileUser['status'] !== 'active') {
            $_SESSION['error'] = "Your account is currently inactive. Please contact support.";
            header('Location: /logout'); exit;
        }
        
        // 2. Fetch Logged-In User's Details (always needed for header, sidebar, context)
        $loggedInUserDetails = $this->db->get("users",
            ["id", "username", "fullname", "avatar"],
            ["id" => $loggedInUserId]
        );
        if ($loggedInUserDetails) {
            if (isset($loggedInUserDetails['fullname'])) $loggedInUserDetails['full_name'] = $loggedInUserDetails['fullname'];
            if (isset($loggedInUserDetails['avatar'])) $loggedInUserDetails['profile_picture'] = $loggedInUserDetails['avatar'];
        }
        
        if (!$loggedInUserDetails) { 
             session_destroy(); $_SESSION['error'] = 'Session invalid. Please log in again.'; header('Location: /login'); exit;
        }

        $pageTitle = $isViewingOwnFullPage ? "Your Friends" : (htmlspecialchars($mainProfileUser['full_name'] ?: $mainProfileUser['username']) . "'s Friends");

        // 3. Fetch Friend Requests (ONLY if viewing own full friends page)
        $friendRequests = [];
        if ($isViewingOwnFullPage) {
            $friendRequests = $this->fetchFriendRequests($loggedInUserId); // Uses helper
        }

        // 4. Fetch All Friends (of the $profileWhoseFriendsToViewId)
        // AND determine their relationship with the $loggedInUserId (the viewer)
        $allFriendsRaw = $this->fetchUserAcceptedFriendsInternal($profileWhoseFriendsToViewId); // Gets user objects of friends
        
        $allFriends = []; // This will be the final array with added relationship info
        if (is_array($allFriendsRaw)) {
            foreach ($allFriendsRaw as $potentialFriend) {
                // Default relationship for the viewer (logged-in user) to this potentialFriend
                $potentialFriend['relationship_with_viewer'] = 'not_friends';
                $potentialFriend['pending_request_id_from_them'] = null; // For request_received status

                if ($potentialFriend['id'] == $loggedInUserId) {
                    // This $potentialFriend is the logged-in user themselves.
                    // This scenario occurs if loggedInUser is viewing someone else's profile,
                    // and loggedInUser is one of their friends.
                    $potentialFriend['relationship_with_viewer'] = 'self';
                } elseif ($loggedInUserId) { // Only check relationship if there is a logged-in user
                    // Check relationship between $loggedInUserId and $potentialFriend['id']
                    $relationship = $this->db->get("friends", 
                        ["id AS request_id", "status", "user_id"], // select the 'id' of the friends table row as 'request_id'
                        [
                            "OR" => [
                                "AND #cond1" => ["user_id" => $loggedInUserId, "friend_id" => $potentialFriend['id']],
                                "AND #cond2" => ["user_id" => $potentialFriend['id'], "friend_id" => $loggedInUserId]
                            ]
                        ]
                    );

                    if ($relationship) {
                        if ($relationship['status'] === 'accepted') {
                            $potentialFriend['relationship_with_viewer'] = 'friends';
                        } elseif ($relationship['status'] === 'pending') {
                            if ($relationship['user_id'] == $loggedInUserId) { // Logged-in user sent the request
                                $potentialFriend['relationship_with_viewer'] = 'request_sent';
                            } else { // This $potentialFriend sent a request to logged-in user
                                $potentialFriend['relationship_with_viewer'] = 'request_received';
                                $potentialFriend['pending_request_id_from_them'] = $relationship['request_id'];
                            }
                        }
                        // If status is 'declined', it remains 'not_friends' for button display logic
                    }
                    // If no record in 'friends' table, it remains 'not_friends' (the default)
                }
                // Add `accepted_at` from the friend record to the user object if it was fetched by fetchUserAcceptedFriendsInternal
                // This assumes fetchUserAcceptedFriendsInternal (the step-by-step one) adds 'accepted_at'
                if (!isset($potentialFriend['accepted_at']) && isset($friendIdToAcceptedAtMap[$potentialFriend['id']])) {
                    // This part refers to the previous 'step-by-step' fetchUserAcceptedFriendsInternal.
                    // If you reverted to a simpler fetchUserAcceptedFriendsInternal, ensure it provides 'accepted_at'.
                    // The version of fetchUserAcceptedFriendsInternal you chose (step-by-step) should already handle this.
                }

                $allFriends[] = $potentialFriend;
            }
        }

        // 5. Fetch People You May Know (Suggestions are ALWAYS FOR the $loggedInUserId)
        $suggestedFriends = $this->fetchFriendSuggestions($loggedInUserId, $profileWhoseFriendsToViewId, $isViewingOwnFullPage);

        $viewData = [
            'pageTitle'         => $pageTitle,
            'profileUser'       => $mainProfileUser, // The user whose profile/friends page this primarily is
            'loggedInUserDetails' => $loggedInUserDetails, // The actual logged-in user
            'isOwnProfile'      => $isViewingOwnFullPage, // True if page is about logged-in user (/friends or /friends/own_id)
            'friendRequests'    => $friendRequests ?: [],
            'allFriends'        => $allFriends ?: [], // Now includes 'relationship_with_viewer'
            'suggestedFriends'  => $suggestedFriends ?: [],
            'defaultProfilePic' => defined('DEFAULT_AVATAR_PATH') ? DEFAULT_AVATAR_PATH : '/images/default-avatar.png'
        ];

        $this->view('friends', $viewData);
    }

    /**
     * Generates a data URI for an SVG fallback avatar with user initials.
     *
     * @param string|null $name The full name of the user.
     * @return string The base64 encoded data URI for the SVG image.
     */
    private function generateSvgAvatar(?string $name): string
    {
        $name = trim($name ?? 'User');
        $initials = '';
        $parts = explode(' ', $name);
        $initials .= mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
        } else if (mb_strlen($name) > 1) {
             $initials .= mb_strtoupper(mb_substr($name, 1, 1));
        }
        
        $hash = crc32($name);
        $hue = $hash % 360;
        $bgColor = "hsl({$hue}, 65%, 45%)";
        $textColor = "hsl({$hue}, 20%, 95%)";
        
        $svg = '
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
            <rect width="100" height="100" fill="' . $bgColor . '"/>
            <text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle"
                  font-family="Arial, sans-serif" font-size="45" font-weight="bold"
                  fill="' . $textColor . '">' . htmlspecialchars($initials) . '</text>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function fetchUserAcceptedFriendsInternal(int $userId) {
        if ($userId <= 0) return [];

        // Step 1: Get all relevant friendship records for the user
        $friendshipRecords = $this->db->select("friends", [
            "user_id",
            "friend_id",
            "accepted_at" // Select accepted_at for sorting
        ], [
            "status" => "accepted",
            "OR #user_is_participant" => [
                "user_id" => $userId,
                "friend_id" => $userId
            ]
        ]);

        if (!$friendshipRecords || empty($friendshipRecords)) {
            return [];
        }

        $friendDetails = [];
        $friendIdToAcceptedAtMap = []; // To map friend_id to their acceptance date with $userId

        // Step 2: Extract friend IDs and map their acceptance dates
        $friendIdsToFetch = [];
        foreach ($friendshipRecords as $record) {
            $otherUserId = null;
            if ($record['user_id'] == $userId) {
                $otherUserId = (int)$record['friend_id'];
            } elseif ($record['friend_id'] == $userId) {
                $otherUserId = (int)$record['user_id'];
            }

            if ($otherUserId && $otherUserId != $userId) { // Ensure it's not self and valid
                $friendIdsToFetch[] = $otherUserId;
                // Store the accepted_at timestamp associated with this specific friendship
                $friendIdToAcceptedAtMap[$otherUserId] = $record['accepted_at'];
            }
        }

        if (empty($friendIdsToFetch)) {
            return [];
        }

        $friendIdsToFetch = array_unique($friendIdsToFetch);

        // Step 3: Fetch user details for these friend IDs
        $friendUserObjects = $this->db->select("users", [
            "id",
            "username",
            "fullname",
            "avatar",
            "bio"
            // Add other fields if needed by the view directly from users table
        ], [
            "id" => $friendIdsToFetch,
            "status" => "active"
            // We will sort in PHP using the accepted_at from the friends table
        ]);

        // map DB keys to expected keys for downstream code
        if (is_array($friendUserObjects)) {
            foreach ($friendUserObjects as &$fu) {
                if (isset($fu['fullname'])) $fu['full_name'] = $fu['fullname'];
                if (isset($fu['avatar'])) $fu['profile_picture'] = $fu['avatar'];
            }
            unset($fu);
        }

        if (!$friendUserObjects || empty($friendUserObjects)) {
            return [];
        }

        // Step 4: Combine user details with their specific 'accepted_at' from the friendship record
        // and sort by it.
        foreach ($friendUserObjects as &$friendUser) { // Use reference to modify array directly
            if (isset($friendIdToAcceptedAtMap[$friendUser['id']])) {
                $friendUser['accepted_at'] = $friendIdToAcceptedAtMap[$friendUser['id']];
            } else {
                $friendUser['accepted_at'] = null; // Should not happen if logic is correct
            }
        }
        unset($friendUser); // Unset reference

        // Sort by accepted_at DESC (newest first)
        usort($friendUserObjects, function ($a, $b) {
            $timeA = isset($a['accepted_at']) ? strtotime($a['accepted_at']) : 0;
            $timeB = isset($b['accepted_at']) ? strtotime($b['accepted_at']) : 0;
            return $timeB <=> $timeA; // spaceship operator for comparison (PHP 7+)
                                    // For older PHP: return $timeB - $timeA;
        });

        return $friendUserObjects;
    }

    /**
     * Handles sending a friend request from the logged-in user to another user.
     * Route: POST /friends/suggestion/add/{friendId:\d+}  (Matches router definition)
     * Route: POST /friends/add/{friendId:\d+} (Original docblock)
     */
    public function sendRequest($friendId) 
    {
        $loggedInUserId = $_SESSION['user_id'] ?? null;
        if (!$loggedInUserId) {
            $_SESSION['error'] = 'You must be logged in to send a friend request.';
            header('Location: /login?redirect=' . urlencode($_SERVER['HTTP_REFERER'] ?? '/')); exit;
        }
        $friendId = (int)$friendId; // This is the target user ID
        if ($loggedInUserId == $friendId) {
            $_SESSION['error'] = 'You cannot send a friend request to yourself.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/')); exit;
        }
        $targetUser = $this->db->get("users", ["id", "status", "fullname"], ["id" => $friendId]);
        if ($targetUser && isset($targetUser['fullname'])) $targetUser['full_name'] = $targetUser['fullname'];
        if (!$targetUser || $targetUser['status'] !== 'active') {
            $_SESSION['error'] = 'This user cannot receive friend requests at the moment.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/')); exit;
        }
        $existingFriendship = $this->db->get("friends", "*", [ "OR" => [ "AND" => ["user_id" => $loggedInUserId, "friend_id" => $friendId], "AND" => ["user_id" => $friendId, "friend_id" => $loggedInUserId] ] ]);
        if ($existingFriendship) {
            if ($existingFriendship['status'] === 'accepted') $_SESSION['info'] = 'You are already friends.';
            elseif ($existingFriendship['status'] === 'pending') $_SESSION['info'] = ($existingFriendship['user_id'] == $loggedInUserId) ? 'Friend request already sent.' : 'This user sent you a request. Check yours.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/')); exit;
        }
        
        try {
            $result = $this->db->insert("friends", [
                "user_id" => $loggedInUserId,
                "friend_id" => $friendId,
                "status" => "pending",
                "requested_at" => Medoo::raw('NOW()')
            ]);

            if ($result && $result->rowCount() > 0) {
            $_SESSION['success'] = 'Friend request sent!';
            
            // --- ADD NOTIFICATION ---
            $newFriendshipId = $this->db->id(); 
            if ($newFriendshipId) {
                // ** FIXED LINE BELOW **
                $actorFullNameValue = $this->db->get("users", "fullname", ["id" => $loggedInUserId]);
                $actorFullName = $actorFullNameValue ?: ($_SESSION['user_full_name'] ?? 'Someone');
                
                $notificationMessage = htmlspecialchars($actorFullName) . " sent you a friend request.";
                $notificationContext = json_encode([
                    'friendship_id' => $newFriendshipId,
                    'actor_id' => $loggedInUserId
                ]);

                $this->db->insert("notifications", [
                    "user_id" => $friendId, 
                    "actor_user_id" => $loggedInUserId, 
                    "type" => "friend_request_received",
                    "message" => $notificationMessage,
                    "context_json" => $notificationContext,
                    "is_read" => 0,
                    "created_at" => Medoo::raw('NOW()')
                ]);
            }
            // --- END NOTIFICATION ---

            } else {
                $_SESSION['error'] = 'Failed to send request. DB Error: '.($this->db->error()[2] ?? 'Unknown');
            }
        } catch (\PDOException $e) {
            // Handle duplicate key race or constraint violation gracefully
            $msg = $e->getMessage();
            $sqlState = $e->getCode();
            if ($sqlState == '23000' || stripos($msg, 'Duplicate entry') !== false) {
                // Another request was inserted concurrently or duplicate attempt
                $_SESSION['info'] = 'Friend request already exists.';
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
                exit;
            }
            // Unexpected DB error - log and show friendly message
            error_log('FriendsController::sendRequest PDOException: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to send request. Database error.';
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
    }

    /**
     * Handles removing a friend suggestion for the logged-in user.
     * Route: POST /friends/suggestion/remove/{suggestedUserId:\d+}
     */
    public function removeSuggestion($suggestedUserId) 
    {
        $loggedInUserId = $_SESSION['user_id'] ?? null;
        if (!$loggedInUserId) {
            $_SESSION['error'] = 'You must be logged in.'; header('Location: /login'); exit;
        }
        $suggestedUserId = (int)$suggestedUserId;
        if ($loggedInUserId == $suggestedUserId || $suggestedUserId <= 0) {
            $_SESSION['error'] = 'Invalid action.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/')); exit;
        }
        
        if ($this->db->has("user_hidden_suggestions", ["user_id" => $loggedInUserId, "hidden_user_id" => $suggestedUserId])) {
            $_SESSION['info'] = 'Suggestion already hidden.';
        } else {
            $result = $this->db->insert("user_hidden_suggestions", ["user_id" => $loggedInUserId, "hidden_user_id" => $suggestedUserId]);
            $_SESSION[$result && $result->rowCount() > 0 ? 'success' : 'error'] = $result && $result->rowCount() > 0 ? 'Suggestion removed.' : 'Failed to remove. DB Error: '.($this->db->error()[2] ?? 'Unknown');
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
    }

    public function confirmNotification($requestId)
    {
        header('Content-Type: application/json');

        $loggedInUserId = $_SESSION['user_id'] ?? null;
        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
            exit;
        }

        $requestId = (int)$requestId;
        if ($requestId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
            exit;
        }

        $friendRequest = $this->db->get("friends", "*", ["id" => $requestId]);
        if (!$friendRequest) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
            exit;
        }

        if ($friendRequest['friend_id'] != $loggedInUserId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Not authorized to accept this request.']);
            exit;
        }

        if ($friendRequest['status'] !== 'pending') {
            http_response_code(409);
            $message = ($friendRequest['status'] === 'accepted')
                ? 'Already accepted.'
                : 'Cannot accept (Status: ' . htmlspecialchars($friendRequest['status']) . ').';
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        // Accept the friend request
        $result = $this->db->update("friends", [
            "status" => "accepted",
            "accepted_at" => Medoo::raw('NOW()')
        ], ["id" => $requestId]);

        if ($result && $result->rowCount() > 0) {
            // Step 1: Add notification for requester (friend request accepted)
            $notificationRecipientId = $friendRequest['user_id'];
            $actorUserId = $loggedInUserId;

            $actorFullNameValue = $this->db->get("users", "fullname", ["id" => $actorUserId]);
            $actorFullName = $actorFullNameValue ?: ($_SESSION['user_full_name'] ?? 'Someone');

            $notificationMessage = htmlspecialchars($actorFullName) . " accepted your friend request.";
            $notificationContext = json_encode([
                'friendship_id' => $requestId,
                'actor_id' => $actorUserId
            ]);

            $this->db->insert("notifications", [
                "user_id" => $notificationRecipientId,
                "actor_user_id" => $actorUserId,
                "type" => "friend_request_accepted",
                "message" => $notificationMessage,
                "context_json" => $notificationContext,
                "is_read" => 0,
                "created_at" => Medoo::raw('NOW()')
            ]);

            // Step 2: Delete the original "friend_request_received" notification
            $this->db->pdo->prepare("
                DELETE FROM notifications 
                WHERE user_id = :uid 
                AND type = 'friend_request_received'
                AND JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.friendship_id')) = :fid
            ")->execute([
                ':uid' => $loggedInUserId,
                ':fid' => (string)$requestId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Friend request accepted!',
                'friendship_id' => $requestId,
                'new_status' => 'accepted'
            ]);
            exit;
        } else {
            http_response_code(500);
            $dbError = $this->db->error();
            error_log("FriendsController::confirmNotification DB Error: " . ($dbError ? json_encode($dbError) : 'Unknown'));
            echo json_encode(['success' => false, 'message' => 'Failed to accept request. Please try again.']);
            exit;
        }
    }

        /**
     * Accepts a pending friend request (for traditional form submissions).
     * Route: POST /friends/accept/{requestId:\d+}
     */
    public function confirm($requestId) 
    {
        $loggedInUserId = $_SESSION['user_id'] ?? null;
        if (!$loggedInUserId) {
            $_SESSION['error'] = 'You must be logged in.';
            header('Location: /login');
            exit;
        }

        $requestId = (int)$requestId; 
        if ($requestId <= 0) {
            $_SESSION['error'] = 'Invalid request ID.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
            exit;
        }

        // Fetch the full friend request record
        $friendRequest = $this->db->get("friends", "*", ["id" => $requestId]);
        
        if (!$friendRequest) {
            $_SESSION['error'] = 'Request not found.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
            exit;
        }

        // Verify the logged-in user is the one who received the request
        if ($friendRequest['friend_id'] != $loggedInUserId) {
            $_SESSION['error'] = 'Not authorized to accept this request.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
            exit;
        }

        // Check if the request is still pending
        if ($friendRequest['status'] !== 'pending') {
            $_SESSION['info'] = ($friendRequest['status'] === 'accepted') 
                ? 'You are already friends.' 
                : 'This request cannot be accepted (Status: '.$friendRequest['status'].').';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
            exit;
        }
        
        // --- Update the friendship status to 'accepted' ---
        $result = $this->db->update("friends", [
            "status" => "accepted", 
            "accepted_at" => Medoo::raw('NOW()')
        ], ["id" => $requestId]);

        if ($result && $result->rowCount() > 0) {
            $_SESSION['success'] = 'Friend request accepted!';

            // --- Step 1: Create a new "request_accepted" notification for the original sender ---
            $notificationRecipientId = $friendRequest['user_id'];
            $actorUserId = $loggedInUserId;

            $actorFullNameValue = $this->db->get("users", "fullname", ["id" => $actorUserId]);
            $actorFullName = $actorFullNameValue ?: ($_SESSION['user_full_name'] ?? 'Someone');

            $notificationMessage = htmlspecialchars($actorFullName) . " accepted your friend request.";
            $notificationContext = json_encode([
                'friendship_id' => $requestId,
                'actor_id' => $actorUserId 
            ]);

            $this->db->insert("notifications", [
                "user_id" => $notificationRecipientId,
                "actor_user_id" => $actorUserId,
                "type" => "friend_request_accepted",
                "message" => $notificationMessage,
                "context_json" => $notificationContext,
                "is_read" => 0,
                "created_at" => Medoo::raw('NOW()')
            ]);

            // ===================================================================
            // THE FIX: Use a raw PDO query for the complex DELETE operation
            // ===================================================================
            // --- Step 2: Delete the old "friend_request_received" notification ---
            try {
                // Get the raw PDO object from Medoo via `$this->db->pdo`
                $stmt = $this->db->pdo->prepare("
                    DELETE FROM notifications 
                    WHERE user_id = :uid 
                    AND type = 'friend_request_received'
                    AND JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.friendship_id')) = :fid
                ");
                
                $stmt->execute([
                    ':uid' => $loggedInUserId,
                    ':fid' => (string)$requestId
                ]);
            } catch (\PDOException $e) {
                // Log the error but don't stop the user's successful flow.
                error_log("Failed to delete 'friend_request_received' notification: " . $e->getMessage());
            }
            // ===================================================================
            // END: FIX
            // ===================================================================

        } else {
            $_SESSION['error'] = 'Failed to accept request. DB Error: '.($this->db->error()[2] ?? 'Unknown');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
        exit;
    }

    /**
     * Declines or deletes a pending friend request.
     * Route: POST /friends/request/{requestId:\d+}/decline
     */
    public function declineRequest($requestId) 
    {
        $loggedInUserId = $_SESSION['user_id'] ?? null;
        if (!$loggedInUserId) {
            $_SESSION['error'] = 'You must be logged in.'; header('Location: /login'); exit;
        }
        $requestId = (int)$requestId;
        if ($requestId <= 0) {
            $_SESSION['error'] = 'Invalid request ID.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
        }
        $friendRequest = $this->db->get("friends", "*", ["id" => $requestId]);
        if (!$friendRequest) {
            $_SESSION['error'] = 'Request not found.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
        }
        if ($friendRequest['friend_id'] != $loggedInUserId && !($friendRequest['user_id'] == $loggedInUserId && $friendRequest['status'] === 'pending')) {
            $_SESSION['error'] = 'Not authorized to decline/cancel this request.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
        }
        $result = $this->db->delete("friends", ["id" => $requestId]); 
        $_SESSION[$result && $result->rowCount() > 0 ? 'success' : 'error'] = $result && $result->rowCount() > 0 ? 'Request removed.' : 'Failed to remove. DB Error: '.($this->db->error()[2] ?? 'Possibly already removed.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends')); exit;
    }

        /**
     * API endpoint to decline a received friend request or cancel a sent one
     * by deleting the friendship record.
     * Responds with JSON for AJAX requests.
     * Route: POST /api/friends/request/delete/{requestId:\d+}
     *
     * @param int $requestId The ID of the friends record to delete.
     */
    public function declineRequestApi(int $requestId)
    {
        header('Content-Type: application/json');
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        // 1. --- Standard Validation ---
        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
            exit;
        }

        if ($requestId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
            exit;
        }

        // 2. --- Define the EXACT record to delete in the 'friends' table ---
        $whereClause = [
            "id" => $requestId,
            "status" => "pending",
            "OR" => [
                "user_id" => $loggedInUserId,
                "friend_id" => $loggedInUserId,
            ]
        ];

        // 3. --- Attempt to Delete the Records in a Transaction ---
        try {
            // Medoo's delete method for the main friendship record.
            $statement = $this->db->delete("friends", $whereClause);

            // Check if the main deletion was successful.
            if ($statement->rowCount() > 0) {
                
                // ===================================================================
                // THE FIX: Use a raw PDO query for the complex notification deletion
                // ===================================================================
                try {
                    // This query safely deletes the corresponding notification.
                    $stmt = $this->db->pdo->prepare("
                        DELETE FROM notifications 
                        WHERE user_id = :uid 
                        AND type = 'friend_request_received'
                        AND JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.friendship_id')) = :fid
                    ");
                    
                    $stmt->execute([
                        ':uid' => $loggedInUserId,
                        ':fid' => (string)$requestId
                    ]);
                } catch (\PDOException $e) {
                    // Log this specific error but don't fail the whole request,
                    // as the main action (declining) was successful.
                    error_log("Failed to delete 'friend_request_received' notification: " . $e->getMessage());
                }
                // ===================================================================
                // END FIX
                // ===================================================================

                // Success! The request was deleted.
                echo json_encode(['success' => true, 'message' => 'Request removed.']);

            } else {
                // The query ran, but no rows matched.
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Request not found or you are not authorized to modify it.']);
            }

        } catch (\PDOException $e) {
            // This catches errors from the main `friends` table deletion.
            error_log("declineRequestApi PDOException: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
        }
        
        exit;
    }

    // --- PRIVATE HELPER METHODS ---
    
    private function countMutualFriends($userId1, $userId2) {
        if (!is_numeric($userId1) || !is_numeric($userId2) || $userId1 <= 0 || $userId2 <= 0) return 0;
        if ($userId1 == $userId2) return 0;
        $getFriendIds = function($targetUserId) {
            if (!is_numeric($targetUserId) || $targetUserId <=0) return [];
            $friendships = $this->db->select("friends", ["user_id", "friend_id"], ["AND" => [ "OR" => ["user_id" => $targetUserId, "friend_id" => $targetUserId], "status" => "accepted"]]);
            if ($friendships === false || !is_array($friendships)) return [];
            $ids = [];
            foreach ($friendships as $fs) { if(isset($fs['user_id']) && isset($fs['friend_id'])) $ids[] = (int) (($fs['user_id'] == $targetUserId) ? $fs['friend_id'] : $fs['user_id']); }
            return array_unique($ids);
        };
        return count(array_intersect($getFriendIds($userId1), $getFriendIds($userId2)));
    }

    private function fetchFriendRequests(int $userId) {
        if ($userId <=0) return []; $requests = [];
        $rawRequests = $this->db->select("friends", ["[>]users" => ["friends.user_id" => "id"]],
            ["users.id(user_id)", "users.username", "users.fullname(full_name)", "users.avatar(profile_picture)", "users.bio", "friends.id(request_id)", "friends.requested_at"],
            ["friends.friend_id" => $userId, "friends.status" => "pending", "ORDER" => ["friends.requested_at" => "DESC"], "LIMIT" => 3]
        );
        if (is_array($rawRequests)) foreach ($rawRequests as $req) { $req['mutual_friends_count'] = $this->countMutualFriends($userId, $req['user_id']); $requests[] = $req; }
        return $requests;
    }

    private function fetchUserAcceptedFriends(int $userId) {
        if ($userId <=0) return []; $friendIds = [];
        $acceptedFriendships = $this->db->select("friends", ["user_id", "friend_id"], ["OR" => ["user_id" => $userId, "friend_id" => $userId], "status" => "accepted"]);
        if (is_array($acceptedFriendships)) foreach ($acceptedFriendships as $fs) { $friendIds[] = ($fs['user_id'] == $userId) ? (int)$fs['friend_id'] : (int)$fs['user_id']; }
        $friendIds = array_values(array_unique($friendIds));
        if (!empty($friendIds)) {
            $raw = $this->db->select("users", ["id", "username", "fullname", "avatar", "bio"], ["id" => $friendIds, "status" => "active", "ORDER" => ["fullname" => "ASC"]]) ?: [];
            // map to expected keys
            foreach ($raw as &$r) {
                if (isset($r['fullname'])) $r['full_name'] = $r['fullname'];
                if (isset($r['avatar'])) $r['profile_picture'] = $r['avatar'];
            }
            unset($r);
            return $raw;
        }
        return [];
    }

    private function fetchFriendSuggestions(int $forLoggedInUserId, int $profileContextId, bool $isViewingOwnPage) {
        if ($forLoggedInUserId <=0) return []; $excludeIds = [$forLoggedInUserId];
        $connections = $this->db->select("friends", ["user_id", "friend_id"], ["OR" => ["user_id" => $forLoggedInUserId, "friend_id" => $forLoggedInUserId]]);
        if (is_array($connections)) foreach ($connections as $conn) { $excludeIds[] = ($conn['user_id'] == $forLoggedInUserId) ? (int)$conn['friend_id'] : (int)$conn['user_id']; }
        if (!$isViewingOwnPage && $profileContextId != $forLoggedInUserId) $excludeIds[] = $profileContextId;
        $hiddenIds = $this->db->select("user_hidden_suggestions", "hidden_user_id", ["user_id" => $forLoggedInUserId]);
        if (is_array($hiddenIds) && !empty($hiddenIds)) $excludeIds = array_merge($excludeIds, array_map('intval', $hiddenIds));
        $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));
        $excludeIds = array_filter($excludeIds, function($id){ return $id > 0; });
        $queryConditions = ["status" => "active", "ORDER" => Medoo::raw("RAND()"), "LIMIT" => 4];
        if (!empty($excludeIds)) $queryConditions["id[!]"] = $excludeIds;
        $rawSuggestions = $this->db->select("users", ["id", "username", "fullname", "avatar", "bio"], $queryConditions); $suggestions = [];
        if (is_array($rawSuggestions)) foreach ($rawSuggestions as $sug) { 
            if (isset($sug['fullname'])) $sug['full_name'] = $sug['fullname'];
            if (isset($sug['avatar'])) $sug['profile_picture'] = $sug['avatar'];
            $sug['mutual_friends_count'] = $this->countMutualFriends($forLoggedInUserId, $sug['id']); $suggestions[] = $sug; }
        return $suggestions;
    }

    /**
     * Unfriends a user.
     * Removes the friendship record(s) between the logged-in user and the specified friend.
     * Route: POST /friends/unfriend/{friendId:\d+}
     *
     * @param int $friendId The ID of the user to unfriend.
     */
    public function unfriend($friendId)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $loggedInUserId = $_SESSION['user_id'] ?? null;
        $friendId = (int)$friendId;

        if (!$loggedInUserId) {
            $_SESSION['error'] = 'You must be logged in to unfriend someone.';
            header('Location: /login');
            exit;
        }

        if ($friendId <= 0 || $friendId == $loggedInUserId) {
            $_SESSION['error'] = 'Invalid user to unfriend.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
            exit;
        }

        $whereConditions = [
            "status" => "accepted",
            "OR #condition1" => [
                "AND" => ["user_id" => $loggedInUserId, "friend_id" => $friendId],
            ],
            "OR #condition2" => [
                "AND" => ["user_id" => $friendId, "friend_id" => $loggedInUserId],
            ]
        ];
        
        $friendshipRecord = $this->db->get("friends", "id", $whereConditions);

        if (!$friendshipRecord) {
            $_SESSION['info'] = 'You are not currently friends with this user or the record was already removed.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
            exit;
        }

        $result = $this->db->delete("friends", ["id" => $friendshipRecord['id']]);

        if ($result && $result->rowCount() > 0) {
            $_SESSION['success'] = 'Successfully unfriended.';
        } else {
            $_SESSION['error'] = 'Failed to unfriend. Please try again. DB Error: '.($this->db->error()[2] ?? 'Unknown');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/friends'));
        exit;
    }


    public function sendRequestApi(int $friendId)
    {
        header('Content-Type: application/json');
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        // --- 1. Standard Validation ---
        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'You must be logged in to send a friend request.']);
            exit;
        }

        if ($loggedInUserId == $friendId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You cannot send a friend request to yourself.']);
            exit;
        }

        $targetUser = $this->db->get("users", ["id", "status"], ["id" => $friendId]);
        if (!$targetUser || $targetUser['status'] !== 'active') {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'This user cannot receive friend requests at this time.']);
            exit;
        }

        // --- 2. Check for an Existing Active or Pending Request ---
        // This is the only check needed since you don't have a unique constraint.
        $existingFriendship = $this->db->get("friends", "status", [
            "status[!]" => "declined", // Look for any relationship that isn't declined
            "OR" => [
                "AND" => ["user_id" => $loggedInUserId, "friend_id" => $friendId],
                "AND" => ["user_id" => $friendId, "friend_id" => $loggedInUserId]
            ]
        ]);

        if ($existingFriendship) {
            http_response_code(409); // Conflict
            $status = $existingFriendship['status'];
            $message = ($status === 'accepted') ? 'You are already friends.' : 'A friend request is already pending.';
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        
        // --- 3. All Checks Passed - Perform a Simple Insert ---
        try {
            $this->db->insert("friends", [
                "user_id" => $loggedInUserId,  // The person sending the request
                "friend_id" => $friendId,      // The person receiving the request
                "status" => "pending"
                // 'requested_at' will be set by the database default
            ]);

            $newFriendshipId = $this->db->id();

            if (!$newFriendshipId) {
                throw new \Exception("Medoo insert succeeded but returned no ID.");
            }

            // --- 4. Create the Notification ---
            $actorFullName = $_SESSION['user_full_name'] ?? 'Someone';
            $notificationMessage = htmlspecialchars($actorFullName) . " sent you a friend request.";
            $notificationContext = json_encode([
                'friendship_id' => $newFriendshipId,
                'actor_id' => $loggedInUserId
            ]);

            $this->db->insert("notifications", [
                "user_id" => $friendId,
                "actor_user_id" => $loggedInUserId,
                "type" => "friend_request_received",
                "message" => $notificationMessage,
                "context_json" => $notificationContext,
                "is_read" => 0,
            ]);

            // --- 5. Send Success Response ---
            echo json_encode(['success' => true, 'message' => 'Friend request sent!']);

        } catch (\Exception $e) {
            error_log("sendRequestApi Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'A server error occurred while sending the request.']);
        }
        
        exit;
    }

        /**
     * API endpoint to unfriend a user by deleting the friendship record.
     * Responds with JSON for AJAX requests.
     * Route: POST /api/friends/unfriend/{friendId:\d+}
     */
    public function unfriendApi(int $friendId)
    {
        header('Content-Type: application/json');
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        // --- 1. Standard Validation ---
        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
            exit;
        }

        if ($friendId <= 0 || $friendId == $loggedInUserId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user to unfriend.']);
            exit;
        }

        // --- 2. Build a RAW and EXPLICIT WHERE Clause ---
        // This removes any chance of Medoo misinterpreting the nested structure.
        $whereClause = Medoo::raw(
            "WHERE `status` = 'accepted' AND 
            ((`user_id` = :uid1 AND `friend_id` = :fid1) OR (`user_id` = :uid2 AND `friend_id` = :fid2))",
            [
                ':uid1' => $loggedInUserId,
                ':fid1' => $friendId,
                ':uid2' => $friendId,
                ':fid2' => $loggedInUserId
            ]
        );

        // --- 3. Execute the Delete Operation using Medoo ---
        try {
            // We pass the raw where clause to Medoo's delete method.
            $statement = $this->db->delete("friends", $whereClause);

            // --- 4. Check the number of affected rows ---
            if ($statement->rowCount() > 0) {
                // SUCCESS: The row was found and deleted.
                echo json_encode(['success' => true, 'message' => 'Successfully unfriended.']);
            } else {
                // FAILURE: No rows matched the raw WHERE clause.
                // This is the error you are seeing. We add a log to see the query.
                error_log("Unfriend failed. Medoo generated query: " . $this->db->last());
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Friendship record could not be found.']);
            }

        } catch (\PDOException $e) {
            error_log("unfriendApi PDOException: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
        }
        
        exit;
    }

    /**
     * API endpoint to search friends of a specific user.
     * Route: GET /api/friends/search (or similar)
     * Expects 'query' GET parameter.
     * Expects 'user_id' GET parameter (whose friends to search).
     * Returns JSON.
     */
    public function searchUserFriendsAPI()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        if (!$loggedInUserId) {
            http_response_code(401); echo json_encode(['error' => 'Authentication required.']); exit;
        }

        $searchTerm = trim($_GET['query'] ?? '');
        $profileWhoseFriendsToSearchId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $loggedInUserId;
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12; 
        $offset = ($page - 1) * $limit;

        if (empty($searchTerm)) {
            http_response_code(400); echo json_encode(['error' => 'Search query is required.']); exit;
        }
        if ($profileWhoseFriendsToSearchId <= 0) {
            http_response_code(400); echo json_encode(['error' => 'Valid user_id is required.']); exit;
        }

        $friendIdRecords = $this->db->select("friends", ["user_id", "friend_id", "accepted_at"], [
            "status" => "accepted",
            "OR" => [
                "user_id" => $profileWhoseFriendsToSearchId,
                "friend_id" => $profileWhoseFriendsToSearchId
            ]
        ]);

        if (empty($friendIdRecords)) {
            header('Content-Type: application/json');
            echo json_encode(['friends' => [], 'message' => 'No friends to search.']); exit;
        }

        $actualFriendIds = [];
        $friendIdToAcceptedAt = [];
        foreach ($friendIdRecords as $record) {
            $friendId = ($record['user_id'] == $profileWhoseFriendsToSearchId) ? (int)$record['friend_id'] : (int)$record['user_id'];
            if ($friendId != $profileWhoseFriendsToSearchId) { 
                $actualFriendIds[] = $friendId;
                $friendIdToAcceptedAt[$friendId] = $record['accepted_at'];
            }
        }
        $actualFriendIds = array_unique($actualFriendIds);

        if (empty($actualFriendIds)) {
            header('Content-Type: application/json');
            echo json_encode(['friends' => [], 'message' => 'No friends found after filtering self.']); exit;
        }

        $queryConditions = [
            "AND" => [
                "id" => $actualFriendIds, 
                "status" => "active",
                "OR" => [
                    "username[~]" => $searchTerm,
                    "fullname[~]" => $searchTerm
                ]
            ],
            "LIMIT" => [$offset, $limit],
            "ORDER" => ["fullname" => "ASC"]
        ];

        $matchingFriends = $this->db->select("users",
            ["id", "username", "fullname", "avatar", "bio"],
            $queryConditions
        );

        $results = [];
        if (is_array($matchingFriends)) {
            foreach ($matchingFriends as $friend) {
                // map DB keys to expected view keys
                if (isset($friend['fullname'])) $friend['full_name'] = $friend['fullname'];
                if (isset($friend['avatar'])) $friend['profile_picture'] = $friend['avatar'];
                $friend['relationship_with_viewer'] = 'not_friends'; 
                $friend['pending_request_id_from_them'] = null;
                 if ($loggedInUserId && $friend['id'] != $loggedInUserId) {
                    $relationship = $this->db->get("friends", ["id AS request_id", "status", "user_id"], [
                        "OR" => [
                            "AND" => ["user_id" => $loggedInUserId, "friend_id" => $friend['id']],
                            "AND" => ["user_id" => $friend['id'], "friend_id" => $loggedInUserId]
                        ]
                    ]);
                    if ($relationship) {
                        if ($relationship['status'] === 'accepted') $friend['relationship_with_viewer'] = 'friends';
                        elseif ($relationship['status'] === 'pending') {
                            $friend['relationship_with_viewer'] = ($relationship['user_id'] == $loggedInUserId) ? 'request_sent' : 'request_received';
                            if ($friend['relationship_with_viewer'] === 'request_received') $friend['pending_request_id_from_them'] = $relationship['request_id'];
                        }
                    }
                } elseif ($friend['id'] == $loggedInUserId) {
                    $friend['relationship_with_viewer'] = 'self'; 
                }
                $friend['avatar_initials'] = $this->getInitials($friend['full_name'], $friend['username']);
                $friend['avatar_bg_color'] = $this->generateBgColorForInitials($friend['avatar_initials']);
                $friend['accepted_at'] = $friendIdToAcceptedAt[$friend['id']] ?? null; 

                $results[] = $friend;
            }
            usort($results, function ($a, $b) {
                $timeA = isset($a['accepted_at']) ? strtotime($a['accepted_at']) : 0;
                $timeB = isset($b['accepted_at']) ? strtotime($b['accepted_at']) : 0;
                return $timeB <=> $timeA; 
            });
        }

        header('Content-Type: application/json');
        echo json_encode(['friends' => $results ?: []]);
        exit;
    }


    /**
     * API endpoint to search for friend suggestions.
     * Route: GET /friends/suggestions/search (or similar, matches your router)
     * Expects a 'query' GET parameter.
     * Returns JSON.
     */
    public function searchSuggestionsAPI()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required.']);
            exit;
        }

        $searchTerm = trim($_GET['query'] ?? '');

        if (empty($searchTerm)) {
            http_response_code(400);
            echo json_encode(['error' => 'Search query is required.']);
            exit;
        }

        $excludeIdsForSuggestions = [$loggedInUserId];
        $currentConnections = $this->db->select("friends", ["user_id", "friend_id"], [
            "OR" => ["user_id" => $loggedInUserId, "friend_id" => $loggedInUserId],
        ]);
        if (is_array($currentConnections)) {
            foreach ($currentConnections as $conn) {
                $excludeIdsForSuggestions[] = ($conn['user_id'] == $loggedInUserId) ? (int)$conn['friend_id'] : (int)$conn['user_id'];
            }
        }
        $hiddenUserIds = $this->db->select("user_hidden_suggestions", "hidden_user_id", ["user_id" => $loggedInUserId]);
        if (is_array($hiddenUserIds) && !empty($hiddenUserIds)) {
            $excludeIdsForSuggestions = array_merge($excludeIdsForSuggestions, array_map('intval', $hiddenUserIds));
        }
        $excludeIdsForSuggestions = array_values(array_unique(array_map('intval', $excludeIdsForSuggestions)));
        $excludeIdsForSuggestions = array_filter($excludeIdsForSuggestions, function($id){ return $id > 0; });

        $queryConditions = [
            "AND" => [
                "status" => "active",
                "OR" => [
                    "username[~]" => $searchTerm,
                    "fullname[~]" => $searchTerm
                ]
            ],
            "LIMIT" => 12, 
            "ORDER" => ["fullname" => "ASC"]
        ];
        
        if (!empty($excludeIdsForSuggestions)) {
            $queryConditions["AND"]["id[!]"] = $excludeIdsForSuggestions;
        }

        $suggestions = $this->db->select("users", 
            ["id", "username", "fullname", "avatar", "bio"],
            $queryConditions
        );

        $results = [];
        if (is_array($suggestions)) {
            foreach ($suggestions as $sug) {
                 if (isset($sug['fullname'])) $sug['full_name'] = $sug['fullname'];
                 if (isset($sug['avatar'])) $sug['profile_picture'] = $sug['avatar'];
                 $sug['mutual_friends_count'] = $this->countMutualFriends($loggedInUserId, $sug['id']);
                 $sug['avatar_initials'] = $this->getInitials($sug['full_name'], $sug['username']); 
                 $sug['avatar_bg_color'] = $this->generateBgColorForInitials($sug['avatar_initials']);
                $results[] = $sug;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['suggestions' => $results ?: []]);
        exit;
    }


    // --- PRIVATE HELPER METHODS for this controller ---

    /**
    * Generates initials from a full name.
    * Made private as it's a helper for this controller.
    */
    private function getInitials(?string $fullName, ?string $username = null, int $numInitials = 2): string
    {
        $nameToUse = trim($fullName ?? '');
        if (empty($nameToUse) && !empty($username)) {
            $nameToUse = trim($username);
        }
        if (empty($nameToUse)) {
            return "?";
        }
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
        if(empty($initials) && mb_strlen($nameToUse) > 0){
            return mb_strtoupper(mb_substr($nameToUse, 0, ($numInitials == 2 && mb_strlen($nameToUse) > 1) ? 2 : 1));
        }
        return empty($initials) ? "?" : $initials;
    }

    /**
    * Generates a somewhat consistent background color based on initials.
    * Made private as it's a helper for this controller.
    */
    private function generateBgColorForInitials(string $initials): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#06b6d4', '#d946ef', '#4ade80'];
        $hash = 0;
        if (empty($initials) || $initials === "?") { 
            return $colors[array_rand($colors)]; 
        }
        for ($i = 0; $i < strlen($initials); $i++) {
            $hash = ord($initials[$i]) + (($hash << 5) - $hash);
            $hash = $hash & $hash; 
        }
        return $colors[abs($hash) % count($colors)];
    }

    /**
     * API endpoint to get mutual friends between the logged-in user and another user.
     * Route: GET /friends/mutual/{targetUserId:\d+}
     * Supports 'page' and 'limit' GET parameters for pagination.
     *
     * @param int $targetUserId The ID of the user to find mutual friends with.
     * @return void Outputs JSON.
     */
    public function mutualFriendsAPI(int $targetUserId)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        header('Content-Type: application/json');

        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required.']);
            exit;
        }

        $targetUserId = (int)$targetUserId;
        if ($targetUserId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid target user ID.']);
            exit;
        }

        if ($loggedInUserId == $targetUserId) {
            echo json_encode(['mutual_friends' => [], 'total_mutual_friends' => 0, 'page' => 1, 'limit' => 0, 'has_more' => false, 'message' => 'Cannot find mutual friends with oneself.']);
            exit;
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 6; 
        $offset = ($page - 1) * $limit;

        $loggedInUserFriendIds = $this->getFriendIdsForUser($loggedInUserId);
        $targetUserFriendIds = $this->getFriendIdsForUser($targetUserId);

        if (empty($loggedInUserFriendIds) || empty($targetUserFriendIds)) {
            echo json_encode(['mutual_friends' => [], 'total_mutual_friends' => 0, 'page' => $page, 'limit' => $limit, 'has_more' => false, 'message' => 'One or both users have no friends to compare.']);
            exit;
        }

        $allMutualFriendIds = array_intersect($loggedInUserFriendIds, $targetUserFriendIds);
        $allMutualFriendIds = array_diff($allMutualFriendIds, [$loggedInUserId, $targetUserId]); 
        $allMutualFriendIds = array_values(array_unique($allMutualFriendIds)); 

        $totalMutualFriends = count($allMutualFriendIds);

        if ($totalMutualFriends === 0) {
            echo json_encode(['mutual_friends' => [], 'total_mutual_friends' => 0, 'page' => $page, 'limit' => $limit, 'has_more' => false, 'message' => 'No mutual friends found.']);
            exit;
        }

        $paginatedMutualFriendIds = array_slice($allMutualFriendIds, $offset, $limit);

        if (empty($paginatedMutualFriendIds)) {
            echo json_encode(['mutual_friends' => [], 'total_mutual_friends' => $totalMutualFriends, 'page' => $page, 'limit' => $limit, 'has_more' => false, 'message' => 'No mutual friends on this page.']);
            exit;
        }

        $mutualFriendsDetails = $this->db->select("users", [
            "id", "username", "fullname", "avatar", "bio"
        ], [
            "id" => $paginatedMutualFriendIds,
            "status" => "active"
        ]);

        // map fullname/avatar to expected keys
        if (is_array($mutualFriendsDetails)) {
            foreach ($mutualFriendsDetails as &$md) {
                if (isset($md['fullname'])) $md['full_name'] = $md['fullname'];
                if (isset($md['avatar'])) $md['profile_picture'] = $md['avatar'];
            }
            unset($md);
        }

        $results = [];
        if (is_array($mutualFriendsDetails)) {
            $detailsMap = [];
            foreach($mutualFriendsDetails as $detail) {
                $detailsMap[$detail['id']] = $detail;
            }
            foreach ($paginatedMutualFriendIds as $id) {
                if (isset($detailsMap[$id])) {
                    $friend = $detailsMap[$id];
                    $friend['avatar_initials'] = $this->getInitials($friend['full_name'], $friend['username']);
                    $friend['avatar_bg_color'] = $this->generateBgColorForInitials($friend['avatar_initials']);
                    $results[] = $friend;
                }
            }
        }

        $hasMore = ($offset + count($results)) < $totalMutualFriends;

        echo json_encode([
            'mutual_friends' => $results ?: [],
            'total_mutual_friends' => $totalMutualFriends,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $hasMore
        ]);
        exit;
    }

    /**
     * Helper function to get a simple array of friend IDs for a given user.
     *
     * @param int $userId
     * @return array Array of friend IDs.
     */
    private function getFriendIdsForUser(int $userId): array
    {
        if ($userId <= 0) return [];

        $friendshipRecords = $this->db->select("friends", [
            "user_id",
            "friend_id"
        ], [
            "status" => "accepted",
            "OR #user_is_participant" => [
                "user_id" => $userId,
                "friend_id" => $userId
            ]
        ]);

        if (!$friendshipRecords || empty($friendshipRecords)) {
            return [];
        }

        $friendIds = [];
        foreach ($friendshipRecords as $record) {
            $otherUserId = ($record['user_id'] == $userId) ? (int)$record['friend_id'] : (int)$record['user_id'];
            if ($otherUserId != $userId) { 
                $friendIds[] = $otherUserId;
            }
        }
        return array_unique($friendIds);
    }

    public function declineRequestNotification($requestId)
    {
        header('Content-Type: application/json');

        $loggedInUserId = $_SESSION['user_id'] ?? null;
        if (!$loggedInUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit;
        }

        $requestId = (int)$requestId;
        if ($requestId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid friend request ID.']);
            exit;
        }

        try {
            // Step 1: Attempt to delete friendship record
            $deleteFriendship = $this->db->pdo->prepare("DELETE FROM friends WHERE id = :fid");
            $deleteFriendship->execute([':fid' => $requestId]);
            $friendshipDeleted = $deleteFriendship->rowCount();

            if ($friendshipDeleted === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Friend request not found or already deleted.']);
                return;
            }

            // Step 2: Delete related notification if friendship deletion was successful
            $deleteNotification = $this->db->pdo->prepare("
                DELETE FROM notifications 
                WHERE JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.friendship_id')) = :fid 
                AND type = 'friend_request_received'
            ");
            $deleteNotification->execute([':fid' => (string)$requestId]);

            // Step 3: Return success response with updated unread count
            $unreadCount = (int)$this->db->count("notifications", [
                "user_id" => $loggedInUserId,
                "is_read" => 0
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Friend request and notification deleted.',
                'friendship_id' => $requestId,
                'data' => ['unread_count' => $unreadCount]
            ]);
            exit;

        } catch (Exception $e) {
            error_log("FriendsController::declineRequestNotification - Exception: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request.']);
            exit;
        }
    }

    /**
     * API endpoint to get a small list of friends for a profile's friends card.
     * Route: GET /api/profile/{profileId}/friends
     *
     * @param int $profileId The ID of the user whose friends we want to fetch.
     */
    public function getProfileFriendsApi(int $profileId)
    {
        header('Content-Type: application/json');

        if ($profileId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit;
        }

        // You already wrote a perfect helper method for this. Let's reuse it.
        $friends = $this->fetchUserAcceptedFriendsInternal($profileId);

        // Get the total count of friends.
        $friendIds = $this->getFriendIdsForUser($profileId);
        $totalFriendCount = count($friendIds);

        // We only need a small number for the preview card (e.g., 6 or 9).
        $friendsForPreview = array_slice($friends, 0, 6);

        // --- START: MODIFY THE FRIEND DATA BEFORE SENDING ---
        foreach ($friendsForPreview as &$friend) { // Use a reference to modify the array
            if (empty($friend['profile_picture'])) {
                $friend['profile_picture'] = $this->generateSvgAvatar($friend['full_name']);
            }
        }

        unset($friend); // Unset the reference
        // --- END: MODIFY THE FRIEND DATA ---

        echo json_encode([
            'success' => true,
            'friends' => $friendsForPreview,
            'total_count' => $totalFriendCount
        ]);
        exit;
    }

    /**
     * API endpoint to fetch the latest check-ins for a user's profile.
     * Respects post visibility settings.
     *
     * @param int $profileId The ID of the user whose check-ins to fetch.
     */
    public function getProfileCheckinsApi(int $profileId)
    {
        header('Content-Type: application/json');

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service unavailable.']);
            exit;
        }

        // --- 1. Define the base query conditions ---
        // The key condition here is that 'location_name' must not be NULL or empty.
        $whereConditions = [
            "user_id" => $profileId,
            "location_name[!]" => [null, ''], 
            "ORDER" => ["id" => "DESC"],
            "LIMIT" => 5 // Check-ins have more text, so showing 5 is good.
        ];

        // --- 2. Apply visibility rules (Identical to photos/videos) ---
        $isOwnProfile = $this->currentUserId && $this->currentUserId == $profileId;
        $areFriends = $this->currentUserId && !$isOwnProfile && $this->areFriends($this->currentUserId, $profileId);

        if ($isOwnProfile) {
            // Owner sees all
        } elseif ($areFriends) {
            $whereConditions["visibility"] = ["public", "friends"];
        } else {
            $whereConditions["visibility"] = "public";
        }

        // --- 3. Execute the query ---
        try {
            // Select fields needed for display: ID, location, content, and date.
            $checkins = $this->db->select("posts", [
                "id",
                "location_name",
                "content",
                "created_at"
            ], $whereConditions);

            if ($checkins === false) {
                throw new \Exception("Database query failed.");
            }
        } catch (\Exception $e) {
            error_log("Check-in fetch error for profile {$profileId}: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not retrieve check-ins.']);
            exit;
        }

        // --- 4. Send the successful response ---
        echo json_encode([
            'success' => true,
            'checkins' => $checkins
        ]);
        exit;
    }

}