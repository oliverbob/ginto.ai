<?php
namespace App\Controllers;

use Core\Controller; // Assuming your Core\Controller sets up $this->db
// use Medoo\Medoo; // Not directly used if $this->db comes from Core\Controller
// use DBConnect; // Not directly used if $this->db comes from Core\Controller

class ProfileController extends Controller {

    protected $currentUserId = null; // ID of the currently logged-in user (viewer)

     /**
     * A hard limit to prevent excessively deep shares.
     * Copied from HomeController for consistency.
     */
    private const MAX_SHARE_DEPTH = 10;

    public function __construct() {
        parent::__construct(); // Ensures $this->db is initialized from Core\Controller

        if (isset($_SESSION['user_id'])) {
            $this->currentUserId = (int) $_SESSION['user_id'];
        }
        // $this->currentUserId remains null if no one is logged in.
        // The feed() method will handle visibility for anonymous users.
    }

    public function friends($param){
        $this->view('friends');
    }

    /**
     * Displays a user's profile page.
     *
     * @param int $id The ID of the user whose profile to display.
     */
    public function profile($id) {
        $loggedInUserId = $_SESSION['user_id'] ?? null;
        $profileId = (int)$id;

        // --- NEW: Referral Capture Logic ---
        // If the visitor is not logged in, this is a potential referral.
        // Store the profile ID they are visiting and redirect them to register.
        if (!$loggedInUserId) {
            // We check if the user they are trying to view exists and is active first.
            $potentialReferrer = $this->db->get("users", ["id"], ["id" => $profileId, "status" => "active"]);
            
            if ($potentialReferrer) {
                // Store the ID of the user whose profile is being viewed.
                $_SESSION['referral_id'] = $potentialReferrer['id'];

                // Optional: Add a friendly message.
                $_SESSION['info'] = 'Please create an account or log in to view user profiles.';

                // Redirect to the registration page, which is the most likely next step.
                header('Location: /register');
                exit;
            }
            // If the potential referrer doesn't exist, just fall through to the normal error handling.
        }
        // --- End of New Logic ---

        if ($profileId <= 0) {
            // Or redirect to a 404 page
            $_SESSION['error'] = 'Invalid user profile specified.';
            header('Location: /');
            exit;
        }

        // Fetch the main profile user's data
        $profileUser = $this->db->get("users", [
            "id",
            "username",
            "full_name",
            "headline", // <-- ADDED
            "bio",      // <-- ADDED/ENSURED
            "profile_picture",
            "cover_photo",
            "work_place",
            "education",
            "current_city",
            "status"
        ], [
            "id" => $profileId
        ]);

        // Handle case where user does not exist or is not active
        if (!$profileUser || ($profileUser['status'] !== 'active' && $profileUser['id'] != $loggedInUserId)) {
            $_SESSION['error'] = 'This user profile is not available.';
            header('Location: /');
            exit;
        }

        // Determine if the viewer is the owner of the profile
        $isOwnProfile = ($loggedInUserId !== null && $loggedInUserId == $profileId);

        // --- Calculate Total Friend Count ---
        $friendIds = $this->getFriendIdsForUser($profileId);
        $totalFriendCount = count($friendIds);

        // --- Determine Relationship Status with Logged-in User ---
        $relationshipStatus = 'not_friends'; // Default status for non-friends
        $friendshipRequestId = null;       // To hold the ID for pending requests

        if ($isOwnProfile) {
            $relationshipStatus = 'self';
        } elseif ($loggedInUserId) {
            // User is logged in, check for an existing relationship
            $friendship = $this->db->get("friends", ["id", "status", "user_id"], [
                "OR" => [
                    "AND #1" => ["user_id" => $loggedInUserId, "friend_id" => $profileId],
                    "AND #2" => ["user_id" => $profileId, "friend_id" => $loggedInUserId]
                ]
            ]);

            if ($friendship) {
                if ($friendship['status'] === 'accepted') {
                    $relationshipStatus = 'friends';
                } elseif ($friendship['status'] === 'pending') {
                    // Check who sent the request to display the correct state
                    if ($friendship['user_id'] == $loggedInUserId) {
                        $relationshipStatus = 'request_sent'; // Logged-in user sent the request
                    } else {
                        $relationshipStatus = 'request_received'; // Profile user sent the request
                        $friendshipRequestId = $friendship['id']; // Pass the request ID for confirm/decline actions
                    }
                }
                // Other statuses like 'declined' or 'blocked' will fall through
                // and be treated as 'not_friends' for button display purposes.
            }
        } else {
             // The viewer is not logged in
             $relationshipStatus = 'guest';
        }

        // Pass all the prepared data to the 'profile' view template
        $this->view('profile', [
            'profileUser'         => $profileUser,
            'isOwnProfile'        => $isOwnProfile,
            'totalFriendCount'    => $totalFriendCount,
            'relationshipStatus'  => $relationshipStatus,
            'friendshipRequestId' => $friendshipRequestId,
        ]);
    }

    /**
     * Helper function to get a simple array of friend IDs for a given user.
     * This is very efficient for just counting friends.
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
            "OR" => [
                "user_id" => $userId,
                "friend_id" => $userId
            ]
        ]);

        if (empty($friendshipRecords)) {
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

    /**
     * Creates a new post.
     * Can be a post on the user's own wall or on another user's wall.
     */
    public function post() {
        header('Content-Type: application/json');

        // 1. Authentication Check
        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not logged in or session invalid.']);
            exit;
        }
        
        // 2. Get Input and Check for Database
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$this->db) {
            error_log(static::class . "::post() Error: Database connection not available.");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server configuration error: Database not initialized.']);
            exit;
        }

        // 3. Sanitize and Validate Core Fields
        $content = isset($input['content']) ? trim((string) $input['content']) : '';
        $visibility = $input['visibility'] ?? ''; // Visibility is now optional until the end
        $allowedVisibilities = ['public', 'friends', 'private'];

        if (empty($visibility) || !in_array($visibility, $allowedVisibilities)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid visibility (public, friends, private) is required.']);
            exit;
        }
        if (mb_strlen($content) > 100000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Post content is too long.']);
            exit;
        }

        // --- 4. CORRECTED AND UNIFIED VALIDATION ---
        // Check for any valid content type before declaring the post empty.
        $hasText = !empty($content);
        $hasImage = !empty($input['image']);
        $hasCloudFile = !empty($input['cloud_file_id']);
        $hasLocation = !empty($input['location_name']);
        $isShare = !empty($input['shared_post_id']);

        if (!$hasText && !$hasImage && !$hasCloudFile && !$hasLocation && !$isShare) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Post must have content, an image, a location, or be a share.']);
            exit;
        }

        // --- 5. Logic for Wall Owner (for ProfileController) ---
        $authorId = $this->currentUserId;
        $dbProfileUserId = null;
        if (isset($input['profile_owner_id']) && filter_var($input['profile_owner_id'], FILTER_VALIDATE_INT) && $input['profile_owner_id'] > 0) {
            $profileWallOwnerId = (int)$input['profile_owner_id'];
            $dbProfileUserId = ($profileWallOwnerId === $authorId) ? null : $profileWallOwnerId;
        }

        // --- 6. Prepare Data for Database Insertion ---
        $dataToInsert = [
            "user_id"         => $authorId,
            "author_id"       => $authorId,
            "profile_user_id" => $dbProfileUserId,
            "content"         => $content,
            "visibility"      => $visibility,
        ];

        // --- Add Optional Fields to the Insert Array ---
        if ($hasImage && filter_var($input['image'], FILTER_VALIDATE_URL)) {
            $dataToInsert['image'] = $input['image'];
        }
        if ($hasCloudFile && filter_var($input['cloud_file_id'], FILTER_VALIDATE_INT)) {
            $dataToInsert['cloud_file_id'] = (int)$input['cloud_file_id'];
        }
        
        // --- THIS IS THE FIX: ADD location_name TO THE INSERT DATA ---
        if ($hasLocation) {
            $dataToInsert['location_name'] = substr(trim($input['location_name']), 0, 255);
        }
        
        // This part handles shares and other post types
        if ($isShare && filter_var($input['shared_post_id'], FILTER_VALIDATE_INT)) {
            $dataToInsert['shared_post_id'] = (int)$input['shared_post_id'];
            $dataToInsert['post_type'] = 'share';
        } elseif (isset($input['post_type']) && in_array($input['post_type'], ['text', 'ai_code', 'media', 'live_stream'])) {
            $dataToInsert['post_type'] = $input['post_type'];
        }

        if (($dataToInsert['post_type'] ?? '') === 'ai_code' && isset($input['code_language'])) {
            $dataToInsert['code_language'] = substr(trim($input['code_language']), 0, 20);
            $dataToInsert['original_prompt'] = isset($input['original_prompt']) ? trim($input['original_prompt']) : null;
        }

        // --- 7. Execute the Database Insert ---
        try {
            $this->db->insert("posts", $dataToInsert);
            $newPostId = $this->db->id();

            if (!$newPostId) {
                throw new \Exception('Failed to save post (no ID returned).');
            }
        } catch (\PDOException $e) {
            error_log(static::class . "::post() PDOException: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error while saving post. Please try again.']);
            exit;
        }

        // --- 8. Prepare and Return the Full Post Object ---
        // Using a central method like this is best practice
        // Ensure `fetchCompletePostData` is available in the base Controller or copied to both.
        $newPostDetails = $this->fetchCompletePostData($newPostId);

        if (!$newPostDetails) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Post saved, but could not retrieve fresh details.']);
            exit;
        }

        // The fetchCompletePostData method should already handle all fields,
        // including the new location_name, so the response is automatically correct.
        echo json_encode([
            'success' => true,
            'message' => 'Post created successfully!',
            'post' => $newPostDetails
        ]);
        exit;
    }

    private function generateFallbackAvatar(string $name, int $size = 40): string {
        // ... (your existing generateFallbackAvatar method)
        $initial = '?'; $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $nameParts = explode(' ', $trimmedName);
            $nameParts = array_filter($nameParts);
            if (count($nameParts) > 0) {
                $firstLetter = strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'));
                if (count($nameParts) >= 2) {
                    $lastLetter = strtoupper(mb_substr(end($nameParts), 0, 1, 'UTF-8'));
                    $initial = $firstLetter . $lastLetter;
                    if (!preg_match('/^[A-ZÀ-ÖØ-Þ\d]{2}$/u', $initial)) {
                        $initial = $firstLetter;
                    }
                } else {
                    $initial = $firstLetter;
                }
            }
            if (empty($initial) || !preg_match('/^[A-ZÀ-ÖØ-Þ\d]{1,2}$/u', $initial)) {
                $firstCharFromTrimmed = strtoupper(mb_substr($trimmedName, 0, 1, 'UTF-8'));
                $initial = preg_match('/^[A-ZÀ-ÖØ-Þ\d]$/u', $firstCharFromTrimmed) ? $firstCharFromTrimmed : '?';
            }
        }
        $hueSeed = crc32(strtolower($trimmedName));
        $hue = $hueSeed % 360;
        $bgColor = "hsl({$hue}, 70%, 45%)";
        $textColor = "hsl({$hue}, 25%, 95%)";
        $fontSizePercentage = (mb_strlen($initial, 'UTF-8') > 1) ? '40' : '50';

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d" role="img" aria-label="Avatar for %s"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="%s" fill="%s" font-weight="bold">%s</text></svg>',
            $size, $size,
            htmlspecialchars($trimmedName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8'),
            $fontSizePercentage,
            htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
        );
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }



    // THIS IS THE CORRECT, WORKING CODE FOR THE FUNCTION:
    private function areFriends($userId1, $userId2): bool {
        if (!$this->db || !$userId1 || !$userId2 || $userId1 == $userId2) {
            return false;
        }

        // This is the REAL logic that checks your `friends` table
        // for an 'accepted' status where the users are in either column.
        return $this->db->has("friends", [
            "status" => "accepted",
            "OR" => [
                "AND #dir1" => ["user_id" => $userId1, "friend_id" => $userId2],
                "AND #dir2" => ["user_id" => $userId2, "friend_id" => $userId1]
            ]
        ]);
    }

    /**
     * Fetches a feed of posts for a specific user's profile.
     * This version uses the application's central fetchCompletePostData method,
     * ensuring consistency with the main newsfeed and correctly handling shared posts.
     */
    public function feed($profile_owner_id) {
        header('Content-Type: application/json');

        $profile_owner_id_validated = filter_var($profile_owner_id, FILTER_VALIDATE_INT);
        if (!$profile_owner_id_validated || $profile_owner_id_validated <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid profile owner ID.']);
            exit;
        }

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 50]]);
        $offset = ($page - 1) * $limit;

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service unavailable.']);
            exit;
        }

        // ===================================================================
        // START: CORRECTED WHERE CLAUSE
        // ===================================================================

        // --- 1. Build the WHERE clause for post selection ---
        // This is the core fix. We are looking for posts where the profile owner's ID
        // matches EITHER the author (user_id) OR the wall owner (profile_user_id).
        $whereConditions = [
            "OR" => [
                "p.user_id" => $profile_owner_id_validated,         // Condition 1: Posts authored by the profile owner.
                "p.profile_user_id" => $profile_owner_id_validated, // Condition 2: Posts on this profile owner's wall by others.
            ],
            "ORDER" => ["p.created_at" => "DESC"],
            "LIMIT" => [$offset, $limit]
        ];

        // ===================================================================
        // END: CORRECTED WHERE CLAUSE
        // ===================================================================

        // --- 2. Add VISIBILITY filter based on the VIEWER's relationship ---
        if ($this->currentUserId && $this->currentUserId == $profile_owner_id_validated) {
            // Viewer is the owner, sees everything. No extra visibility filter needed for their own posts.
        } else if ($this->currentUserId && $this->areFriends($this->currentUserId, $profile_owner_id_validated)) {
            // Viewer is a friend, can see 'public' and 'friends' posts.
            $whereConditions["p.visibility"] = ["public", "friends"];
        } else {
            // Viewer is a guest or non-friend, can only see 'public' posts.
            $whereConditions["p.visibility"] = "public";
        }
        
        // --- 3. Fetch the Post IDs ---
        // Note: Medoo aliases tables with parentheses in the from clause
        $postIds = $this->db->select("posts (p)", "id", $whereConditions);

        if ($postIds === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching posts from database.']);
            exit;
        }

        // --- 4. Loop through IDs and build full post objects ---
        $processedPosts = [];
        if (!empty($postIds)) {
            foreach ($postIds as $postId) {
                // Assuming fetchCompletePostData correctly joins and gets all needed data
                $fullPostData = $this->fetchCompletePostData($postId); 
                if ($fullPostData) {
                    $processedPosts[] = $fullPostData;
                }
            }
        }
        
        // --- 5. Get the total count for pagination ---
        $countQueryConditions = $whereConditions;
        unset($countQueryConditions['LIMIT'], $countQueryConditions['ORDER']);
        $totalPosts = (int)$this->db->count("posts (p)", $countQueryConditions);

        // --- 6. Send the final JSON response ---
        echo json_encode([
            'success' => true,
            'posts' => $processedPosts,
            'pagination' => [
                'total_posts' => $totalPosts,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => (int)ceil($totalPosts / $limit)
            ]
        ]);
        exit;
    }

    // --- HELPER METHODS (Copied from HomeController for consistency) ---
    
    /**
     * This is the central function for fetching post data.
     * The fix is applied here to include the location name.
     */
    protected function fetchCompletePostData($postId, array $seenIds = []) {
        if (in_array($postId, $seenIds) || count($seenIds) >= self::MAX_SHARE_DEPTH) {
            error_log("Recursion limit reached for post ID {$postId}. Chain: " . implode(' -> ', $seenIds));
            return null;
        }
        if (!$this->db) { return null; }

        $post = $this->db->get("posts (p)",
            ["[>]users (u)" => ["p.user_id" => "id"]],
            [ // This is the array of columns to select from the database
                "p.id", "p.user_id", "p.profile_user_id", "p.content", "p.image", "p.cloud_file_id", 
                "p.visibility", "p.post_type", "p.shared_post_id", "p.code_language", "p.original_prompt",
                "p.created_at", "p.updated_at",
                
                // ===============================================
                // <<< THE FIX IS HERE. THIS LINE WAS ADDED.
                // ===============================================
                "p.location_name",
                
                "u.fullname AS full_name", "u.username", "u.profile_picture", "u.gender"
            ],
            ["p.id" => $postId]
        );

        if (!$post) { return null; }

        // --- ADDED VISIBILITY CHECK ---
        // This is a crucial security check to ensure the current user can see this post.
        $isAccessible = false;
        if ($post['visibility'] === 'public') {
            $isAccessible = true;
        } elseif ($this->currentUserId) {
            if ($post['user_id'] == $this->currentUserId) { // User is the author
                $isAccessible = true;
            } elseif ($post['visibility'] === 'friends' && $this->areFriends($this->currentUserId, $post['user_id'])) { // User is friends with author
                $isAccessible = true;
            }
        }
        if (!$isAccessible) {
            return null; // Return null if the user doesn't have permission. This fixes the bug.
        }
        // --- END VISIBILITY CHECK ---

        $post['user_avatar'] = $post['profile_picture'] ?: $this->generateFallbackAvatar($post['full_name'] ?? $post['username'] ?? 'User', 40);

        // Aggregate likes/comments using safeCount to avoid fatal PDOExceptions if tables are missing
        $post['like_count'] = $this->safeCount('likes', ["post_id" => $postId]);
        $post['comment_count'] = $this->safeCount('comments', ["post_id" => $postId]);

        try {
            $post['is_liked_by_current_user'] = $this->currentUserId ? (bool)$this->db->has("likes", ["post_id" => $postId, "user_id" => $this->currentUserId]) : false;
        } catch (\PDOException $e) {
            error_log("Warning: could not check like existence for post {$postId} and user {$this->currentUserId}: " . $e->getMessage());
            $post['is_liked_by_current_user'] = false;
        }

        if ($post['post_type'] === 'share' && !empty($post['shared_post_id'])) {
            // The recursive call will also be checked for visibility, making the whole chain secure.
            $originalPostData = $this->fetchCompletePostData((int)$post['shared_post_id'], $seenIds);
            $post['original_post'] = $originalPostData;
        }

        return $post;
    }

    /**
     * API endpoint to fetch the latest photos for a user's profile.
     * Respects post visibility settings based on the viewer's relationship.
     *
     * @param int $profileId The ID of the user whose photos to fetch.
     */
    public function getProfilePhotosApi(int $profileId)
    {
        header('Content-Type: application/json');

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service unavailable.']);
            exit;
        }

        // --- 1. Define the base query conditions ---
        $whereConditions = [
            "user_id" => $profileId, // Photos posted BY this user
            "image[!]" => null,     // The 'image' column must not be NULL
            "image[!]" => '',       // and must not be an empty string.
            // This REGEXP ensures we only get image files, not videos or other links.
            "image[REGEXP]" => '\\.(jpg|jpeg|png|gif|webp)$',
            "ORDER" => ["id" => "DESC"],
            "LIMIT" => 9 // We only need the latest 9 for the card
        ];

        // --- 2. Apply visibility rules based on the VIEWER ---
        // Note: $this->currentUserId is the ID of the person viewing the page.
        $isOwnProfile = $this->currentUserId && $this->currentUserId == $profileId;
        $areFriends = $this->currentUserId && !$isOwnProfile && $this->areFriends($this->currentUserId, $profileId);

        if ($isOwnProfile) {
            // The owner can see all their own photos (public, friends, private).
            // No extra visibility condition is needed.
        } elseif ($areFriends) {
            // Friends can see 'public' and 'friends' posts.
            $whereConditions["visibility"] = ["public", "friends"];
        } else {
            // Guests or non-friends can only see 'public' posts.
            $whereConditions["visibility"] = "public";
        }

        // --- 3. Execute the query ---
        try {
            $photos = $this->db->select("posts", ["image"], $whereConditions);

            if ($photos === false) {
                // Medoo returns false on query failure.
                throw new \Exception("Database query failed.");
            }
        } catch (\Exception $e) {
            error_log("Photo fetch error for profile {$profileId}: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not retrieve photos.']);
            exit;
        }

        // --- 4. Send the successful response ---
        echo json_encode([
            'success' => true,
            'photos' => $photos // This will be an array of strings, e.g., ["url1.png", "url2.jpg"]
        ]);
        exit;
    }

    /**
     * API endpoint to update the user's intro details (headline, bio, work, etc.).
     * This is an owner-only action.
     */
    public function updateIntro()
    {
        header('Content-Type: application/json');

        // 1. Security Check: Ensure a user is logged in.
        if (!$this->currentUserId) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'You must be logged in to do that.']);
            exit;
        }

        // 2. Get and Sanitize Input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
            exit;
        }

        // Use a whitelist of fields that are allowed to be updated.
        // --- THIS IS THE KEY CHANGE ---
        $allowedFields = ['headline', 'bio', 'work_place', 'education', 'current_city'];
        $dataToUpdate = [];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                // Use different length limits for different fields for better security/validation
                $maxLength = 255; // Default max length
                if ($field === 'headline') $maxLength = 100;
                if ($field === 'bio') $maxLength = 5000; // A generous limit for the bio

                // Sanitize and trim the input before adding it to the update array
                $dataToUpdate[$field] = substr(trim($input[$field]), 0, $maxLength);
            }
        }
        
        // Handle cases where the user clears a field
        if (isset($input['headline']) && empty(trim($input['headline']))) {
             $dataToUpdate['headline'] = null;
        }
        if (isset($input['bio']) && empty(trim($input['bio']))) {
             $dataToUpdate['bio'] = null;
        }
        
        if (empty($dataToUpdate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No data provided to update.']);
            exit;
        }

        // 3. Update the Database
        try {
            $this->db->update("users", $dataToUpdate, [
                "id" => $this->currentUserId
            ]);

            // --- IMPORTANT FIX FOR MEDOO ERROR CHECKING ---
            // The error() method in Medoo returns an array on error, or null on success.
            // Your previous error check was for PDO, not Medoo. This is the correct way.
            if ($this->db->error) {
                 $errorDetails = $this->db->error;
                 // Log the detailed error but show a generic message to the user.
                 error_log("Medoo DB Error: " . ($errorDetails[1] ?? 'Unknown error'));
                 throw new \Exception("Database update failed.");
            }
            
        } catch (\Exception $e) {
            error_log("Profile intro update failed for user {$this->currentUserId}: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'A server error occurred while saving your details.']);
            exit;
        }

        // 4. Send Success Response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Your details have been updated.',
            'updated_data' => $dataToUpdate // Send back the sanitized data for the frontend
        ]);
        exit;
    }

    /**
     * API endpoint to fetch the latest videos for a user's profile.
     * THIS VERSION IS RESTRUCTURED TO MIRROR THE WORKING getProfilePhotosApi.
     *
     * @param int $profileId The ID of the user whose videos to fetch.
     */
    public function getProfileVideosApi(int $profileId)
    {
        header('Content-Type: application/json');

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service unavailable.']);
            exit;
        }

        // --- 1. Define the base query conditions (Mirrors the working photo logic) ---
        $whereConditions = [
            "user_id" => $profileId,    // Videos posted BY this user
            "image[!]" => null,         // The 'image' column must not be NULL
            "image[!]" => '',           // and must not be an empty string.
            
            // --- THIS IS THE ONLY SIGNIFICANT CHANGE FROM THE PHOTO LOGIC ---
            // This REGEXP ensures we only get video files.
            "image[REGEXP]" => '\\.(mp4|mov|webm|avi|mkv|MP4|MOV|WEBM|AVI|MKV)$',

            "ORDER" => ["id" => "DESC"],
            "LIMIT" => 9 
        ];

        // --- 2. Apply visibility rules based on the VIEWER (Identical logic to photos) ---
        $isOwnProfile = $this->currentUserId && $this->currentUserId == $profileId;
        $areFriends = $this->currentUserId && !$isOwnProfile && $this->areFriends($this->currentUserId, $profileId);

        if ($isOwnProfile) {
            // The owner can see all their own videos.
            // No extra visibility condition is needed.
        } elseif ($areFriends) {
            // Friends can see 'public' and 'friends' posts.
            $whereConditions["visibility"] = ["public", "friends"];
        } else {
            // Guests or non-friends can only see 'public' posts.
            $whereConditions["visibility"] = "public";
        }

        // --- 3. Execute the query (Identical logic to photos) ---
        try {
            $videos = $this->db->select("posts", ["id", "image"], $whereConditions);

            if ($videos === false) {
                // Medoo returns false on query failure.
                throw new \Exception("Database query failed.");
            }
        } catch (\Exception $e) {
            error_log("Video fetch error for profile {$profileId}: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not retrieve videos.']);
            exit;
        }

        // --- 4. Send the successful response ---
        echo json_encode([
            'success' => true,
            'videos' => $videos
        ]);
        exit;
    }

    /**
     * API endpoint to fetch the latest check-ins for a user's profile.
     * Respects post visibility settings.
     *
     * @param int $profileId The ID of the user whose check-ins to fetch.
     */
    // In ProfileController.php

    public function getProfileCheckinsApi(int $id) // <-- Parameter name is now $id
    {
        header('Content-Type: application/json');

        // Use $id inside the function now
        $profile_owner_id_validated = filter_var($id, FILTER_VALIDATE_INT); 
        if (!$profile_owner_id_validated || $profile_owner_id_validated <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid profile owner ID.']);
            exit;
        }

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service unavailable.']);
            exit;
        }

        $whereConditions = [
            "user_id" => $profile_owner_id_validated, // Use the validated variable
            "location_name[!]" => [null],
            "location_name[!]" => '',
            "ORDER" => ["id" => "DESC"],
            "LIMIT" => 5
        ];

        // Apply visibility rules
        $isOwnProfile = $this->currentUserId && $this->currentUserId == $profile_owner_id_validated;
        $areFriends = $this->currentUserId && !$isOwnProfile && $this->areFriends($this->currentUserId, $profile_owner_id_validated);

        if ($isOwnProfile) {
            // No visibility filter needed
        } elseif ($areFriends) {
            $whereConditions["visibility"] = ["public", "friends"];
        } else {
            $whereConditions["visibility"] = "public";
        }

        try {
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
            error_log("Check-in fetch error for profile {$profile_owner_id_validated}: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not retrieve check-ins.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'checkins' => $checkins
        ]);
        exit;
    }
}
?>