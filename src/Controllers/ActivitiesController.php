<?php
namespace Ginto\Controllers;

use Core\Controller;
use DateTime;
use Exception;

class ActivitiesController extends Controller {

    /**
     * DB instance for this controller. Kept local to avoid modifying base Controller.
     * Using dynamic or untyped property to match existing project patterns.
     */
    protected $db = null;

    private $currentUserId;
    private $currentUserFullName;
    private $currentUserProfilePicture;

    public function __construct() {
        parent::__construct();
        // Initialize controller-local DB reference without changing base Controller
        if (isset($GLOBALS['db']) && $GLOBALS['db']) {
            $this->db = $GLOBALS['db'];
        } elseif (class_exists('Ginto\\Core\\Database')) {
            try {
                $this->db = \Ginto\Core\Database::getInstance();
            } catch (\Throwable $e) {
                $this->db = null;
                error_log('ActivitiesController: could not initialize DB instance: ' . $e->getMessage());
            }
        } else {
            $this->db = null;
        }
        if (isset($_SESSION['user_id'])) {
            $this->currentUserId = (int) $_SESSION['user_id'];
            $this->currentUserFullName = $_SESSION['user_full_name'] ?? 'User';
            $this->currentUserProfilePicture = $_SESSION['user_profile_picture'] ?? null;
        } else {
            $this->currentUserId = null;
        }
    }

    // Centralized auth guard for mutating endpoints
    private function requireAuth(): void {
        if (!$this->currentUserId) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
            exit;
        }
    }

    // --- HEADER DATA & NOTIFICATIONS ---
    public function getHeaderData() {
        header('Content-Type: application/json');
        if (!$this->currentUserId) {
            echo json_encode(['success' => false, 'isLoggedIn' => false, 'message' => 'User not authenticated.']);
            exit;
        }

        // --- Pagination Parameters ---
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $notificationLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 7; // Default limit, can be overridden by JS
        $offset = ($page - 1) * $notificationLimit;

        $responseData = [
            'success' => true,
            'isLoggedIn' => true,
            // User data is typically only needed for the initial load (page 1)
            // Or if you want to send it every time, that's fine too.
        ];

        if ($page === 1) {
            $responseData['user'] = [
                'id' => $this->currentUserId,
                'fullName' => $this->currentUserFullName,
                'avatarUrl' => $this->currentUserProfilePicture,
                'avatarFallback' => $this->generateFallbackAvatar($this->currentUserFullName, 32, $this->currentUserProfilePicture)
            ];
        }

        $notificationsData = [];
        $unreadNotificationCount = 0; // Only meaningful for the initial load typically
        $rawNotifications = [];
        $hasMoreNotifications = false;
        $totalUserNotifications = 0;


        try {
            $rawNotifications = $this->db->select("notifications (n)",
                [
                    "[>]users (actor)" => ["n.actor_user_id" => "id"]
                ],
                [
                    "n.id", "n.type", "n.message", "n.is_read", "n.created_at", "n.context_json",
                    "n.actor_user_id",
                    "actor.fullname AS actor_full_name",
                    "actor.profile_picture AS actor_profile_picture"
                ],
                [
                    "n.user_id" => $this->currentUserId,
                    "ORDER" => ["n.created_at" => "DESC"],
                    "LIMIT" => [$offset, $notificationLimit] // Apply pagination to the query
                ]
            );

            if ($rawNotifications) {
                foreach ($rawNotifications as $rn) {
                    $actorName = $rn['actor_full_name'] ?? 'A user';
                    $actorAvatarActualUrl = $rn['actor_profile_picture'] ?? null;

                    $notificationsData[] = [
                        'id' => (int)$rn['id'],
                        'type' => $rn['type'],
                        'message' => $rn['message'],
                        'is_read' => (bool)$rn['is_read'],
                        'created_at' => $rn['created_at'],
                        'actor_name_parsed' => $actorName,
                        'actor_avatar_url' => $this->generateFallbackAvatar($actorName, 32, $actorAvatarActualUrl), // Generate fallback if needed
                        'context' => json_decode($rn['context_json'] ?? '', true)
                    ];
                }
            }

            // Get total count once to calculate hasMoreNotifications
            $totalUserNotifications = (int)$this->db->count("notifications", ["user_id" => $this->currentUserId]);
            $hasMoreNotifications = ($offset + count($notificationsData)) < $totalUserNotifications;

            // Unread count is generally more relevant for the overall header, not per page
            // If it's for page 1 only:
            if ($page === 1) {
                $unreadNotificationCount = (int)$this->db->count("notifications", [
                    "user_id" => $this->currentUserId,
                    "is_read" => 0
                ]);
                $responseData['unreadNotificationCount'] = $unreadNotificationCount;
            }


        } catch (\PDOException $e) {
            error_log("Error fetching header data (notifications): " . $e->getMessage());
            // For subsequent pages, if an error occurs, indicate no more and empty notifications
            $notificationsData = [];
            $hasMoreNotifications = false;
            // If it's page 1, you might want to set success to false or provide an error message
            if ($page === 1) {
                $responseData['success'] = false;
                $responseData['message'] = 'Error fetching notifications.';
            }
        }

        $responseData['notifications'] = $notificationsData;
        $responseData['hasMoreNotifications'] = $hasMoreNotifications; // Use this key consistently

        echo json_encode($responseData);
        exit;
    }

    protected function formatTimeAgo(DateTime $date): string 
    {
        $now = new DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) { if ($diff->d == 1) return 'yesterday'; return $diff->d . ' days ago'; }
        if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        if ($diff->s > 0) { if ($diff->s < 10) return 'just now'; return $diff->s . ' second' . ($diff->s > 1 ? 's' : '') . ' ago';}
        return 'just now';
    }

    /**
     * Marks a single notification as read, identified by an ID in the URL.
     * The ID is expected as a direct argument from the router.
     *
     * @param int $notificationId The ID of the notification from the URL.
     */
    public function markNotificationAsRead($notificationId) { // Changed signature
        header('Content-Type: application/json');

        $this->requireAuth();

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }

        // The method check should still be here if you only want POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Only POST requests are accepted.']);
            exit;
        }

        // Validate the $notificationId received directly
        $validatedNotificationId = filter_var($notificationId, FILTER_VALIDATE_INT);
        if ($validatedNotificationId === false || $validatedNotificationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID provided.']);
            exit;
        }
        // Use the validated ID
        $specificNotificationId = $validatedNotificationId;

        try {
            $notificationExists = $this->db->has("notifications", [
                "id" => $specificNotificationId,
                "user_id" => $this->currentUserId
            ]);

            if (!$notificationExists) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notification not found or access denied.']);
                exit;
            }

            $updateResult = $this->db->update("notifications",
                ["is_read" => 1],
                [
                    "id" => $specificNotificationId,
                    "user_id" => $this->currentUserId
                ]
            );

            $message = 'Notification marked as read.';

            if ($updateResult instanceof \PDOStatement) {
                $newUnreadCount = $this->db->count("notifications", [
                    "user_id" => $this->currentUserId,
                    "is_read" => 0
                ]);
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'data' => ['unread_count' => (int)$newUnreadCount]
                ]);
            } else {
                $dbError = $this->db->error();
                error_log("Failed to mark notification {$specificNotificationId} as read for user {$this->currentUserId}. DB Error: " . ($dbError ? json_encode($dbError) : 'Unknown Medoo Error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not mark notification as read.']);
            }

        } catch (\PDOException $e) {
            error_log("PDOException marking notification {$specificNotificationId} as read (user {$this->currentUserId}): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        exit;
    }
    
    public function getUserAvatarInfo(int $userId) {
        header('Content-Type: application/json');
        if ($userId <= 0) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid user ID']); exit;
        }
        try {
            // DB column is `fullname` (not `full_name`). Select the correct column and map to response keys.
            $user = $this->db->get("users", ["id", "fullname", "profile_picture"], ["id" => $userId]);
            if ($user) {
                $avatarUrl = $user['profile_picture'] ?? null;
                $displayName = $user['fullname'] ?? 'User';
                $fallback = $this->generateFallbackAvatar($displayName, 40, $avatarUrl);
                echo json_encode([
                    'success' => true,
                    'userId' => (int)$user['id'],
                    'name' => $displayName,
                    'avatarUrl' => $avatarUrl,
                    'avatarFallback' => $fallback
                ]);
            } else {
                http_response_code(404); echo json_encode(['success' => false, 'error' => 'User not found']);
            }
        } catch (\PDOException $e) {
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        exit;
    }

    // --- POST ACTIONS ---
    public function toggleLike() {
        header('Content-Type: application/json');
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']); exit;
        }
        $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
        if (!$postId) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid Post ID.']); exit;
        }
        $postOwnerData = $this->db->get("posts", ["user_id"], ["id" => $postId]);
        if (!$postOwnerData) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Post not found.']); exit;
        }
        $postOwnerId = (int) $postOwnerData['user_id'];
        $existingLike = $this->db->get("likes", "id", ["user_id" => $this->currentUserId, "post_id" => $postId]);
        $isLiked = false;
        $likeId = null;
        try {
            if ($existingLike) {
                // Medoo::get may return a scalar when selecting a single column or an array
                $existingLikeId = is_array($existingLike) ? ($existingLike['id'] ?? null) : $existingLike;
                if ($existingLikeId) {
                    $this->db->delete("likes", ["id" => $existingLikeId]);
                } else {
                    // Fallback: delete by composite keys
                    $this->db->delete("likes", ["user_id" => $this->currentUserId, "post_id" => $postId]);
                }
                $isLiked = false;
            } else {
                $this->db->insert("likes", ["user_id" => $this->currentUserId, "post_id" => $postId]);
                $likeId = $this->db->id();
                $isLiked = true;
                if ($postOwnerId !== $this->currentUserId) {
                    $this->createNotification(
                        $postOwnerId,
                        'post_like',
                        $this->currentUserFullName . " liked your post.",
                        ['post_id' => $postId, 'like_id' => $likeId]
                    );
                }
            }
        } catch (\PDOException $e) {
            error_log("PDOException toggling like: " . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Database error updating like.']); exit;
        }
        $likeCountValue = $this->safeCount('likes', ["post_id" => $postId]);
        echo json_encode(['success' => true, 'isLiked' => $isLiked, 'likeCount' => $likeCountValue]);
        exit;
    }

    public function addComment() {
        header('Content-Type: application/json');
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']); exit;
        }
        $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
        $content = trim(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_SPECIAL_CHARS));
        if (!$postId) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid Post ID.']); exit;
        }
        if (empty($content)) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Comment content cannot be empty.']); exit;
        }
        if (mb_strlen($content) > 1000) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Comment is too long.']); exit;
        }
        $postOwnerData = $this->db->get("posts", ["user_id"], ["id" => $postId]);
        if (!$postOwnerData) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Post not found.']); exit;
        }
        $postOwnerId = (int) $postOwnerData['user_id'];
        $commentId = null;
        try {
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
            }
            $this->db->insert("comments", ["post_id" => $postId, "user_id" => $this->currentUserId, "content" => $content]);
            $commentId = (int)$this->db->id();
        } catch (\PDOException $e) {
            $dbErr = (is_object($this->db) && method_exists($this->db, 'error')) ? $this->db->error() : null;
            error_log("PDOException inserting comment: " . $e->getMessage() . " DB Error: " . ($dbErr ? json_encode($dbErr) : 'none'));
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Database error saving comment.']); exit;
        }
        if (!$commentId || $commentId <= 0) {
            $dbErr = (is_object($this->db) && method_exists($this->db, 'error')) ? $this->db->error() : null;
            error_log("Failed insert comment (invalid ID: " . var_export($commentId, true) . "). DB Err: " . ($dbErr ? json_encode($dbErr) : 'none'));
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Could not save comment.']); exit;
        }
        if ($postOwnerId !== $this->currentUserId) {
            $this->createNotification( // ** UPDATED CALL **
                $postOwnerId, 'post_comment',
                $this->currentUserFullName . " commented on your post: \"" . mb_strimwidth($content, 0, 50, "...") . "\"",
                ['post_id' => $postId, 'comment_id' => $commentId]
            );
        }
        $newCommentData = $this->db->get(
            "comments (com)",
            ["[>]users (u)" => ["com.user_id" => "id"]],
            [
                "com.id",
                "com.post_id",
                "com.user_id",
                "com.content",
                "com.created_at",
                "u.fullname(user_full_name)",
                "u.username(user_username)",
                "u.profile_picture(user_profile_picture)"
            ],
            ["com.id" => $commentId]
        );
        if ($newCommentData) {
            $nameForAvatar = $newCommentData['user_full_name'] ?? $newCommentData['user_username'] ?? 'User';
            if (isset($newCommentData['content']) && !mb_check_encoding($newCommentData['content'], 'UTF-8')) {
                $newCommentData['content'] = mb_convert_encoding($newCommentData['content'], 'UTF-8', mb_detect_encoding($newCommentData['content']));
            }
            $newCommentData['user_avatar_fallback'] = $newCommentData['user_profile_picture'] ?: $this->generateFallbackAvatar($nameForAvatar, 32);
        }
        $commentCountValue = $this->safeCount('comments', ["post_id" => $postId]);
        $responseArray = ['success' => true, 'message' => 'Comment posted.', 'comment' => $newCommentData, 'commentCount' => $commentCountValue];
        $jsonResponse = json_encode($responseArray, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonResponse === false) {
            error_log("json_encode failed in addComment: " . json_last_error_msg());
            http_response_code(500); echo '{"success": false, "error": "Server error formatting response."}';
        } else {
            echo $jsonResponse;
        }
        exit;
    }

    public function getComments(int $id) {
        header('Content-Type: application/json');
        $postId = $id;
        if ($postId <= 0) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid Post ID.']); exit;
        }
        if (!$this->db->has("posts", ["id" => $postId])) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Post not found.']); exit;
        }
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 5, 'min_range' => 1, 'max_range' => 20]]);
        $offset = ($page - 1) * $limit;
        $commentsData = $this->db->select(
            "comments (com)",
            ["[>]users (u)" => ["com.user_id" => "id"]],
            [
                "com.id (comment_id)",
                "com.user_id",
                "com.content",
                "com.created_at",
                "u.fullname(user_full_name)",
                "u.username(user_username)",
                "u.profile_picture(user_profile_picture)"
            ],
            ["com.post_id" => $postId, "ORDER" => ["com.created_at" => "ASC"], "LIMIT" => [$offset, $limit]]
        );
        $processedComments = [];
        if ($commentsData) {
            foreach ($commentsData as $comment) {
                $commenterName = $comment['user_full_name'] ?? $comment['user_username'] ?? 'User';
                $content = $comment['content'];
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
                }
                $processedComments[] = [
                    'id' => (int)$comment['comment_id'], 'user_id' => (int)$comment['user_id'],
                    'user_full_name' => $commenterName,
                    'user_avatar_fallback' => $comment['user_profile_picture'] ?: $this->generateFallbackAvatar($commenterName, 32),
                    'content' => $content, 'created_at' => $comment['created_at']
                ];
            }
        }
        $totalComments = $this->safeCount('comments', ["post_id" => $postId]);
        $responseArray = [
            'success' => true, 'comments' => $processedComments,
            'pagination' => ['total_comments' => $totalComments, 'per_page' => $limit, 'current_page' => $page, 'total_pages' => (int)ceil($totalComments / $limit)]
        ];
        echo json_encode($responseArray, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function updatePost() {
        // ... (updatePost method remains largely the same as your last provided version) ...
        // Ensure it returns enough post data for PostFeedManager to update the UI.
        header('Content-Type: application/json');
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']); exit;
        }

        $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
        $content = trim(filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW)); 
        $visibility = filter_input(INPUT_POST, 'visibility', FILTER_SANITIZE_STRING);
        $code_language = filter_input(INPUT_POST, 'code_language', FILTER_SANITIZE_STRING);

        if (!$postId) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid Post ID.']); exit;
        }
        if (empty($content)) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Content cannot be empty.']); exit;
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
        }

        $validVisibilities = ['public', 'friends', 'private'];
        if (!in_array($visibility, $validVisibilities)) $visibility = 'public';

        $post = $this->db->get("posts", ["user_id", "post_type"], ["id" => $postId]);
        if (!$post) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Post not found.']); exit;
        }
        if ((int)$post['user_id'] !== $this->currentUserId) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Not authorized.']); exit;
        }

        $updateData = ["content" => $content, "visibility" => $visibility, "updated_at" => \Medoo\Medoo::raw('NOW()')];
        if ($post['post_type'] === 'ai_code' && !empty($code_language)) {
            $validCodeLanguages = ['html', 'javascript', 'typescript', 'css', 'python', 'java', 'csharp', 'php', 'ruby', 'go', 'swift', 'kotlin', 'sql', 'markdown', 'json', 'xml', 'yaml'];
            if (in_array($code_language, $validCodeLanguages)) $updateData['code_language'] = $code_language;
        }

        $updateResult = $this->db->update("posts", $updateData, ["id" => $postId]);
        if ($updateResult instanceof \PDOStatement && $updateResult->rowCount() > 0) {
            $updatedPostData = $this->db->get("posts", "*", ["id" => $postId]);
            if ($updatedPostData) {
                $user = $this->db->get("users", ["id", "full_name", "username", "profile_picture"], ["id" => $updatedPostData['user_id']]);
                $updatedPostData['user'] = $user;
                $updatedPostData['user_avatar'] = $user['profile_picture'] ?: $this->generateFallbackAvatar($user['full_name'] ?? $user['username'] ?? 'User', 40);
                $updatedPostData['like_count'] = $this->safeCount('likes', ["post_id" => $postId]);
                $updatedPostData['comment_count'] = $this->safeCount('comments', ["post_id" => $postId]);
                $updatedPostData['is_liked_by_current_user'] = $this->currentUserId ? (bool)$this->db->has("likes", ["post_id" => $postId, "user_id" => $this->currentUserId]) : false;
            }
            echo json_encode(['success' => true, 'message' => 'Post updated.', 'post' => $updatedPostData], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } elseif ($updateResult instanceof \PDOStatement && $updateResult->rowCount() === 0) {
             $currentPostData = $this->db->get("posts", "*", ["id" => $postId]); // Fetch current data to return
             if ($currentPostData) { // Augment with user details like above
                $user = $this->db->get("users", ["id", "full_name", "username", "profile_picture"], ["id" => $currentPostData['user_id']]);
                $currentPostData['user'] = $user;
                $currentPostData['user_avatar'] = $user['profile_picture'] ?: $this->generateFallbackAvatar($user['full_name'] ?? $user['username'] ?? 'User', 40);
                $currentPostData['like_count'] = $this->safeCount('likes', ["post_id" => $postId]);
                $currentPostData['comment_count'] = $this->safeCount('comments', ["post_id" => $postId]);
                $currentPostData['is_liked_by_current_user'] = $this->currentUserId ? (bool)$this->db->has("likes", ["post_id" => $postId, "user_id" => $this->currentUserId]) : false;
            }
             echo json_encode(['success' => true, 'message' => 'No changes made.', 'post' => $currentPostData], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            error_log("Failed to update post {$postId}. DB Error: " . json_encode($this->db->error()));
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Could not update post.']);
        }
        exit;
    }

    /**
     * Handles the deletion of a post.
     * The post ID is expected as a parameter from the URL, e.g., /post/123/delete
     *
     * @param int $id The ID of the post to delete, passed from the URL route.
     */
    public function deletePost($id) { // The $id parameter is passed by your router from the URL
        header('Content-Type: application/json');

        $this->requireAuth();

        // The route is defined as POST, so this check is still relevant.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']);
            exit;
        }

        // Use the $id from the URL parameter.
        // Your route {id:\d+} already ensures it's digits.
        // We'll still validate it as a positive integer.
        $postId = filter_var($id, FILTER_VALIDATE_INT);

        if ($postId === false || $postId <= 0) { // Ensure it's a valid positive integer
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid Post ID provided in URL.']);
            exit;
        }

        // Ensure DB is available
        if (!$this->db) {
            error_log("deletePost called but DB instance is not available. User={$this->currentUserId}, Post={$postId}");
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server DB unavailable.']);
            exit;
        }

        // Fetch the post owner's user ID (handle Medoo returning scalar or associative array)
        $postOwnerUserIdResult = $this->db->get("posts", "user_id", ["id" => $postId]);
        $postOwnerUserId = null;
        if (is_array($postOwnerUserIdResult) && isset($postOwnerUserIdResult['user_id'])) {
            $postOwnerUserId = (int)$postOwnerUserIdResult['user_id'];
        } elseif (!is_array($postOwnerUserIdResult) && $postOwnerUserIdResult !== null && $postOwnerUserIdResult !== false) {
            $postOwnerUserId = (int)$postOwnerUserIdResult;
        }

        if ($postOwnerUserId === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Post not found.']);
            exit;
        }

        // Authorization: current user must be the owner
        if ($postOwnerUserId !== $this->currentUserId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not authorized to delete this post.']);
            exit;
        }

        // Attempt to delete the post
        $deleteResult = $this->db->delete("posts", ["id" => $postId]);

        // Normalize deletion detection across DB drivers/return types
        $deleted = false;
        try {
            if ($deleteResult instanceof \PDOStatement) {
                $deleted = $deleteResult->rowCount() > 0;
            } elseif (is_bool($deleteResult)) {
                // If boolean true, verify by checking existence
                if ($deleteResult === true) {
                    $deleted = !$this->db->has("posts", ["id" => $postId]);
                }
            } elseif (is_int($deleteResult)) {
                $deleted = $deleteResult > 0;
            }
        } catch (\Throwable $e) {
            error_log("Error evaluating delete result for post {$postId}: " . $e->getMessage());
            $deleted = false;
        }

        if ($deleted) {
            // Successfully deleted the post
            // Consider cascading deletes in DB or deleting related records here if necessary (likes, comments, notifications)
            // Example (if not handled by DB foreign key constraints with ON DELETE CASCADE):
            // $this->db->delete("likes", ["post_id" => $postId]);
            // $this->db->delete("comments", ["post_id" => $postId]);
            // $this->db->delete("notifications", Medoo::raw("JSON_EXTRACT(context_json, '$.post_id') = :post_id", [":post_id" => $postId]));

            echo json_encode(['success' => true, 'message' => 'Post deleted successfully.']);
        } else {
            // Failed to delete the post (e.g., DB error, or post already deleted)
            $dbError = method_exists($this->db, 'error') ? $this->db->error() : null; // Get database error info if available
            error_log("Failed to delete post {$postId}. User ID: {$this->currentUserId}. DB Error: " . ($dbError ? json_encode($dbError) : 'Unknown DB error or delete returned no rows'));
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not delete the post. It might have been already removed or a server error occurred.']);
        }
        exit;
    }

    private function generateFallbackAvatar(string $name, int $size = 32, ?string $profilePictureUrl = null): string {
        // ... (generateFallbackAvatar method remains the same as your last provided version) ...
        if ($profilePictureUrl && filter_var($profilePictureUrl, FILTER_VALIDATE_URL)) {
            return htmlspecialchars($profilePictureUrl);
        }
        $initial = '?'; $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $nameParts = explode(' ', $trimmedName); // Split by any whitespace
            $nameParts = array_filter($nameParts); // Remove empty elements from multiple spaces

            if (count($nameParts) > 0) {
                $firstLetter = strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'));
                if (count($nameParts) >= 2) {
                    $lastLetter = strtoupper(mb_substr(end($nameParts), 0, 1, 'UTF-8'));
                    $initial = $firstLetter . $lastLetter;
                    if (!preg_match('/^[A-ZÀ-ÖØ-Þ\d]{2}$/u', $initial)) { // Allow digits, check if both are valid
                        $initial = $firstLetter; 
                    }
                } else {
                    $initial = $firstLetter;
                }
            }
            if (empty($initial) || !preg_match('/^[A-ZÀ-ÖØ-Þ\d]{1,2}$/u', $initial)) {
                $firstChar = strtoupper(mb_substr($trimmedName, 0, 1, 'UTF-8'));
                $initial = preg_match('/^[A-ZÀ-ÖØ-Þ\d]$/u', $firstChar) ? $firstChar : '?';
            }
        }
        $hueSeed = crc32(strtolower($trimmedName)); $hue = $hueSeed % 360;
        $bgColor = "hsl({$hue}, 70%, 45%)"; $textColor = "hsl({$hue}, 25%, 95%)";
        $fontSizePercentage = (mb_strlen($initial, 'UTF-8') > 1) ? '40' : '50';
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d" role="img" aria-label="Avatar for %s"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="%s" fill="%s" font-weight="bold">%s</text></svg>',
            $size, $size, htmlspecialchars($trimmedName), htmlspecialchars($bgColor), $fontSizePercentage, htmlspecialchars($textColor), htmlspecialchars($initial)
        );
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }

    /**
     * Handles deletion of a comment via a parameterized URL and DELETE HTTP verb.
     * The authenticated user must be the owner of the post to which the comment belongs.
     *
     * @param int $commentId The ID of the comment to delete, passed from the URL.
     */
    public function deleteCommentById($commentId) {
        header('Content-Type: application/json');

        $this->requireAuth();

        // Validate the $commentId (though router regex \d+ already does a basic check)
        $commentId = filter_var($commentId, FILTER_VALIDATE_INT);
        if (!$commentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Comment ID in URL.']);
            exit;
        }

        // Fetch the comment details including its user_id and post_id
        // Assuming your $this->db->get returns the row as an associative array or false/null if not found
        $comment = $this->db->get("comments", ["id", "post_id", "user_id"], ["id" => $commentId]);

        if (!$comment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Comment not found.']);
            exit;
        }

        $commentOwnerUserId = (int)$comment['user_id']; // User who wrote the comment
        $postId = (int)$comment['post_id'];

        // Fetch the user_id of the post owner
        // Adjust this based on how your $this->db->get returns a single column
        $postOwnerUserIdResult = $this->db->get("posts", "user_id", ["id" => $postId]);

        $postOwnerUserId = null;
        if (is_array($postOwnerUserIdResult) && isset($postOwnerUserIdResult['user_id'])) {
            $postOwnerUserId = (int)$postOwnerUserIdResult['user_id'];
        } elseif (!is_array($postOwnerUserIdResult) && $postOwnerUserIdResult !== null && $postOwnerUserIdResult !== false) {
            // If db->get returns the value directly for a single column query
            $postOwnerUserId = (int)$postOwnerUserIdResult;
        }


        if ($postOwnerUserId === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Associated post or post owner not found.']);
            exit;
        }

        // Authorization: Check if current user is post owner OR comment owner
        $isPostOwner = ($postOwnerUserId === $this->currentUserId);
        $isCommentOwner = ($commentOwnerUserId === $this->currentUserId); // Corrected this line

        if (!($isPostOwner || $isCommentOwner)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. You are not authorized to delete this comment.']);
            exit;
        }

        // Perform Deletion
        $deleteResult = $this->db->delete("comments", ["id" => $commentId]);

        if ($deleteResult instanceof \PDOStatement && $deleteResult->rowCount() > 0) {
            // Fetch the new comment count for the post after deletion
            // Ensure your $this->db->count method works correctly for this context
            $newCommentCount = $this->safeCount('comments', ["post_id" => $postId]);
            if ($newCommentCount === false || $newCommentCount === null) { // Check for count failure
                $newCommentCount = 0; // Default to 0 if count fails
                error_log("Failed to get new comment count for post {$postId} after deleting comment {$commentId}.");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Comment deleted successfully.',
                'comment_count' => (int)$newCommentCount // Send back the updated comment count
            ]);
        } else {
            $dbError = $this->db->error(); // Assuming your DB wrapper has an error() method
            error_log("Failed to delete comment {$commentId}. DB Error: " . ($dbError ? json_encode($dbError) : 'No specific error from DB driver or delete returned 0 rows.'));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not delete comment. An internal error occurred or comment was already deleted.']);
        }
        exit;
    }

    public function editCommentById($commentId) {
        header('Content-Type: application/json');

        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Only POST is accepted.']);
            exit;
        }

        $commentId = filter_var($commentId, FILTER_VALIDATE_INT);
        if (!$commentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Comment ID.']);
            exit;
        }

        $newContent = $_POST['content'] ?? null; 

        if ($newContent === null || trim($newContent) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Comment content cannot be empty.']);
            exit;
        }
        
        $newContent = trim($newContent);

        // Fetch the comment details including its user_id and post_id
        $comment = $this->db->get("comments", ["id", "post_id", "user_id"], ["id" => $commentId]);

        if (!$comment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Comment not found.']);
            exit;
        }

        $commentOwnerUserId = (int)$comment['user_id']; // User who wrote the comment
        $postId = (int)$comment['post_id'];

        // Fetch the user_id of the post owner
        $postOwnerUserIdResult = $this->db->get("posts", "user_id", ["id" => $postId]);

        $postOwnerUserId = null;
        if (is_array($postOwnerUserIdResult) && isset($postOwnerUserIdResult['user_id'])) {
            $postOwnerUserId = (int)$postOwnerUserIdResult['user_id'];
        } elseif (!is_array($postOwnerUserIdResult) && $postOwnerUserIdResult !== null && $postOwnerUserIdResult !== false) {
            // If db->get returns the value directly for a single column query
            $postOwnerUserId = (int)$postOwnerUserIdResult;
        }

        if ($postOwnerUserId === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Associated post or post owner not found.']);
            exit;
        }

        // Authorization: Check if current user is post owner OR comment owner
        $isPostOwner = ($postOwnerUserId === $this->currentUserId);
        $isCommentOwner = ($commentOwnerUserId === $this->currentUserId);

        if (!($isPostOwner || $isCommentOwner)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. You are not authorized to edit this comment.']);
            exit;
        }

        // Perform Update
        $updateData = [
            "content" => $newContent,
            "updated_at" => date('Y-m-d H:i:s')
        ];
        
        $updateResult = $this->db->update("comments", $updateData, ["id" => $commentId]);

        if ($updateResult instanceof \PDOStatement && $updateResult->rowCount() > 0) {
            // Just return the updated comment data without joins
            $updatedCommentData = $this->db->get("comments", [
                "id",
                "user_id",
                "post_id", 
                "content",
                "created_at",
                "updated_at"
            ], [
                "id" => $commentId
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Comment updated successfully.',
                'comment' => $updatedCommentData
            ]);
            
        } else if ($updateResult instanceof \PDOStatement && $updateResult->rowCount() === 0) {
            // Get unchanged comment data
            $unchangedCommentData = $this->db->get("comments", [
                "id",
                "user_id",
                "post_id",
                "content", 
                "created_at",
                "updated_at"
            ], [
                "id" => $commentId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Comment content was not changed.',
                'comment' => $unchangedCommentData 
            ]);
        }
        else {
            $dbError = $this->db->error();
            error_log("Failed to update comment {$commentId}. DB Error: " . ($dbError ? json_encode($dbError) : 'No specific error from DB driver.'));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not update comment. An internal error occurred.']);
        }
        exit;
    }

    public function editPostById($postId) {
        header('Content-Type: application/json');

        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Only POST is accepted.']);
            exit;
        }

        $postId = filter_var($postId, FILTER_VALIDATE_INT);
        if (!$postId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Post ID.']);
            exit;
        }

        // Fetch the post details
        $post = $this->db->get("posts", [
            "id",
            "user_id", 
            "post_type",
            "content",
            "visibility",
            "code_language"
        ], ["id" => $postId]);

        if (!$post) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Post not found.']);
            exit;
        }

        // Authorization: Check if current user is the post owner
        if ((int)$post['user_id'] !== $this->currentUserId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. You are not authorized to edit this post.']);
            exit;
        }

        // Use existing values as defaults if not provided in POST
        $newContent = $_POST['content'] ?? $post['content'];
        $newVisibility = $_POST['visibility'] ?? $post['visibility'];
        $newCodeLanguage = $_POST['code_language'] ?? ($post['post_type'] === 'ai_code' ? $post['code_language'] : null);

        // Validate content for specific post types
        if (($post['post_type'] === 'text' || $post['post_type'] === 'ai_code') && trim($newContent) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Content cannot be empty for this post type.']);
            exit;
        }

        // Validate visibility
        if (!in_array(strtolower($newVisibility), ['public', 'friends', 'private'])) {
            $newVisibility = $post['visibility']; // Revert to old if invalid
        }

        // Prepare update data
        $updateData = [
            "content" => trim($newContent),
            "visibility" => strtolower($newVisibility),
            "updated_at" => date('Y-m-d H:i:s')
        ];

        // Handle ai_code specific fields
        if ($post['post_type'] === 'ai_code') {
            if ($newCodeLanguage !== null && trim($newCodeLanguage) !== '') {
                $updateData['code_language'] = strtolower(trim($newCodeLanguage));
            } else {
                $updateData['code_language'] = $post['code_language']; // Keep old if new is invalid/empty
            }
        }

        // Check if any changes were made
        $changed = false;
        if ($updateData['content'] !== $post['content']) $changed = true;
        if ($updateData['visibility'] !== $post['visibility']) $changed = true;
        if (isset($updateData['code_language']) && $updateData['code_language'] !== $post['code_language']) $changed = true;

        if (!$changed) {
            echo json_encode([
                'success' => true,
                'message' => 'Post content was not changed.',
                'post' => $post
            ]);
            exit;
        }

        // Perform Update
        $updateResult = $this->db->update("posts", $updateData, ["id" => $postId]);

        if ($updateResult instanceof \PDOStatement && $updateResult->rowCount() > 0) {
            // Get updated post data
            $updatedPostData = $this->db->get("posts", [
                "id",
                "user_id",
                "post_type",
                "content",
                "visibility",
                "code_language",
                "created_at",
                "updated_at"
            ], [
                "id" => $postId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Post updated successfully.',
                'post' => $updatedPostData
            ]);
            
        } else if ($updateResult instanceof \PDOStatement && $updateResult->rowCount() === 0) {
            // Get unchanged post data
            $unchangedPostData = $this->db->get("posts", [
                "id",
                "user_id",
                "post_type",
                "content",
                "visibility",
                "code_language",
                "created_at",
                "updated_at"
            ], [
                "id" => $postId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Post content was not changed.',
                'post' => $unchangedPostData
            ]);
        } else {
            $dbError = $this->db->error();
            error_log("Failed to update post {$postId}. DB Error: " . ($dbError ? json_encode($dbError) : 'No specific error from DB driver.'));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not update post. An internal error occurred.']);
        }
        exit;
    }

    protected function fetchCompletePostData($postId) {
        $post = $this->db->get("posts (p)",
            [
                "[>]users (u)" => ["p.user_id" => "id"]
            ],
            [
                "p.id", "p.user_id", "p.profile_user_id", "p.content", "p.image",
                "p.cloud_file_id", "p.visibility", "p.post_type", "p.shared_post_id",
                "p.code_language", "p.is_live_stream", "p.stream_playback_uid",
                "p.original_prompt", "p.created_at", "p.updated_at", "p.location_name", // Added location_name for the main post
                "u.fullname AS full_name", "u.username", "u.profile_picture", "u.gender" // Added gender for the main user
            ],
            ["p.id" => $postId]
        );

        if (!$post) {
            error_log("[fetchCompletePostData] Post not found for ID: " . $postId);
            return null;
        }

        // Augment with user avatar, like/comment counts
        $post['user_avatar'] = $post['profile_picture'] ?: $this->generateFallbackAvatar($post['full_name'] ?? $post['username'] ?? 'User', 40);
        $post['like_count'] = $this->safeCount('likes', ["post_id" => $postId]);
        $post['comment_count'] = $this->safeCount('comments', ["post_id" => $postId]);
        $post['is_liked_by_current_user'] = $this->currentUserId ? (bool)$this->db->has("likes", ["post_id" => $postId, "user_id" => $this->currentUserId]) : false;

        // If it's a shared post, fetch the original post's data
        if ($post['post_type'] === 'share' && !empty($post['shared_post_id'])) {
            error_log("[fetchCompletePostData] Post ID " . $postId . " is a share. Fetching original post ID: " . $post['shared_post_id']);
            
            $originalPostData = $this->db->get("posts (op)", // op is the alias for the original post
                [
                    "[>]users (ou)" => ["op.user_id" => "id"]
                ],
                [
                    "op.id", "op.user_id", "op.profile_user_id", "op.content", "op.image",
                    "op.cloud_file_id", "op.visibility", "op.post_type", "op.code_language",
                    "op.is_live_stream", "op.stream_playback_uid", "op.original_prompt",
                    "op.created_at", "op.updated_at",
                    "op.location_name", // <<< --- FIX #1: THIS WAS THE MISSING LINE
                    "ou.fullname AS user_full_name", "ou.username", "ou.profile_picture", "ou.gender" // Also get gender for original author
                ],
                ["op.id" => $post['shared_post_id']]
            );

            if ($originalPostData) {
                // Use the aliased user_full_name key from the select
                $originalPostData['user_avatar'] = $originalPostData['profile_picture'] ?: $this->generateFallbackAvatar($originalPostData['user_full_name'] ?? $originalPostData['username'] ?? 'User', 32);

                $post['original_post'] = $originalPostData;
                error_log("[fetchCompletePostData] Successfully fetched original post data for shared_post_id: " . $post['shared_post_id']);
            } else {
                error_log("[fetchCompletePostData] Could not find original post data for shared_post_id: " . $post['shared_post_id']);
                $post['original_post'] = null;
            }
        }
        return $post;
    }

    public function createSharePost() {
        header('Content-Type: application/json');
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']); exit;
        }

        $jsonPayload = file_get_contents('php://input');
        $requestData = json_decode($jsonPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']); exit;
        }

        $originalPostId = null;
        if (isset($requestData['original_post_id'])) {
            if (is_string($requestData['original_post_id']) && trim($requestData['original_post_id']) === '') {
                // It's just an empty string, do nothing, will be caught by final check
            } else {
                $originalPostId = filter_var($requestData['original_post_id'], FILTER_VALIDATE_INT);
            }
        }

        $content = trim($requestData['content'] ?? '');
        $rawVisibility = $requestData['visibility'] ?? 'public';

        $visibility = 'public';
        $validVisibilities = ['public', 'friends', 'private'];
        if (!empty($rawVisibility) && in_array(strtolower($rawVisibility), $validVisibilities)) {
            $visibility = strtolower($rawVisibility);
        }

        if ($originalPostId === false || $originalPostId === null || $originalPostId === 0) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Original Post ID is required or invalid.']); exit;
        }

        if (!$this->db->has("posts", ["id" => $originalPostId])) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Original post not found.']); exit;
        }

        try {
            $dataToInsert = [
                "user_id" => $this->currentUserId,
                // support schemas using `author_id` as FK
                "author_id" => $this->currentUserId,
                "content" => $content,
                "post_type" => "share",
                "shared_post_id" => $originalPostId,
                "visibility" => $visibility,
            ];

            $this->db->insert("posts", $dataToInsert);
            $newPostId = $this->db->id();

            if (!$newPostId) {
                throw new \Exception("Failed to insert shared post. DB Error: " . json_encode($this->db->error()));
            }

            $newSharePostData = $this->fetchCompletePostData($newPostId);

            if (!$newSharePostData) {
                throw new \Exception("Failed to retrieve newly shared post.");
            }

            // This block correctly handles the self-notification check.
            $originalPostOwner = $this->db->get("posts", ["user_id"], ["id" => $originalPostId]);
            if ($originalPostOwner && isset($originalPostOwner['user_id']) && (int)$originalPostOwner['user_id'] !== $this->currentUserId) {
                $this->createNotification(
                    (int)$originalPostOwner['user_id'],
                    'post_share',
                    ($this->currentUserFullName ?: 'Someone') . " shared your post.",
                    ['post_id' => $originalPostId, 'share_post_id' => $newPostId]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Post shared successfully.', 'post' => $newSharePostData]);

        } catch (\PDOException $e) {
            error_log("PDOException creating share post: " . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Database error sharing post.']); exit;
        } catch (\Exception $e) {
            error_log("Exception creating share post: " . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Server error sharing post: ' . $e->getMessage()]); exit;
        }
        exit;
    }

    private function createNotification(int $userIdToNotify, string $type, string $message, ?array $contextData = null) {
        $actorId = $this->currentUserId;
        if (!$actorId || $userIdToNotify === $actorId) {
            return;
        }
        try {
            $dataToInsert = [
                "user_id"       => $userIdToNotify,
                "actor_user_id" => $actorId,
                "type"          => $type,
                "message"       => $message,
                "is_read"       => 0
            ];
            $jsonContext = [];
            if (isset($contextData['post_id'])) $jsonContext['post_id'] = $contextData['post_id'];
            if (isset($contextData['like_id'])) $jsonContext['like_id'] = $contextData['like_id'];
            if (isset($contextData['comment_id'])) $jsonContext['comment_id'] = $contextData['comment_id'];
            // Added this line to support the share context
            if (isset($contextData['share_post_id'])) $jsonContext['share_post_id'] = $contextData['share_post_id'];
            
            if (!empty($jsonContext)) {
                $dataToInsert["context_json"] = json_encode($jsonContext);
            }
            $this->db->insert("notifications", $dataToInsert);
        } catch (\PDOException | \Exception $e) {
            error_log("Exception creating notification: UserToNotify={$userIdToNotify}, Actor={$actorId}, Type={$type}, Error: " . $e->getMessage() . " Context: " . json_encode($contextData));
        }
    }

    /**
     * Safe wrapper around DB count to avoid throwing fatal errors when DB is missing
     * or the DB driver returns unexpected values.
     *
     * @param string $table
     * @param array  $where
     * @return int
     */
    protected function safeCount(string $table, array $where = []): int {
        try {
            if (!$this->db) return 0;
            $res = $this->db->count($table, $where);
            return is_numeric($res) ? (int)$res : 0;
        } catch (\Throwable $e) {
            error_log("safeCount error for {$table}: " . $e->getMessage());
            return 0;
        }
    }

}