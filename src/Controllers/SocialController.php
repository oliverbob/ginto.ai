<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;

class SocialController extends \Core\Controller
{
    protected const MAX_SHARE_DEPTH = 5;
    protected $currentUserId = null;
    /**
     * Local DB reference (untyped to accept Medoo or wrapper instance).
     */
    protected $db = null;

    public function __construct($db = null)
    {
        // Prefer an injected DB instance; otherwise try global or Database singleton.
        if ($db) {
            $this->db = $db;
        } elseif (isset($GLOBALS['db']) && $GLOBALS['db']) {
            $this->db = $GLOBALS['db'];
        } elseif (class_exists('\Ginto\\Core\\Database')) {
            try {
                $this->db = \Ginto\Core\Database::getInstance();
            } catch (\Throwable $e) {
                $this->db = null;
                error_log('SocialController: could not initialize DB instance: ' . $e->getMessage());
            }
        } else {
            $this->db = null;
        }

        if (isset($_SESSION['user_id'])) {
            $this->currentUserId = (int)$_SESSION['user_id'];
        }
    }

    public function post()
    {
        header('Content-Type: application/json');

        // DEBUG: log CSRF/header/session state to help diagnose 403 issues
        try {
            $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF'] ?? null;
            $sess = $_SESSION['csrf_token'] ?? null;
            $cookie = $_COOKIE[session_name()] ?? null;
            $raw = @file_get_contents('php://input');
            $dbg = [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'hdr_short' => is_string($hdr) ? substr($hdr,0,16) . '...' : null,
                'sess_short' => is_string($sess) ? substr($sess,0,16) . '...' : null,
                'cookie' => $cookie,
                'post_keys' => array_values(array_keys($_POST ?? [])),
                'json_cached' => isset($GLOBALS['_JSON_BODY']) ? true : false,
                'raw_snippet' => is_string($raw) ? substr($raw,0,200) : null,
            ];
            error_log('SocialController::post() csrf-debug: ' . json_encode($dbg, JSON_UNESCAPED_SLASHES));
            if (function_exists('validateCsrfToken') && is_string($hdr) && is_string($sess)) {
                $valid = validateCsrfToken($hdr);
                error_log('SocialController::post() validateCsrfToken(header) => ' . ($valid ? 'true' : 'false'));
            }
        } catch (\Throwable $_) { error_log('SocialController::post() debug logging failed: ' . $_->getMessage()); }

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not logged in or session invalid.']);
            exit;
        }

            // CSRF validation (match ChatStreamHandler pattern)
            $providedToken = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!$providedToken && !empty($GLOBALS['_JSON_BODY']) && is_array($GLOBALS['_JSON_BODY'])) {
                $providedToken = $GLOBALS['_JSON_BODY']['csrf_token'] ?? $GLOBALS['_JSON_BODY']['_csrf'] ?? $providedToken;
            }
            if (!function_exists('validateCsrfToken') || empty($providedToken) || !validateCsrfToken($providedToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit;
            }

        // Prefer JSON already parsed by middleware (CsrfMiddleware) to avoid
        // re-reading php://input which may have been consumed earlier.
        if (!empty($GLOBALS['_JSON_BODY']) && is_array($GLOBALS['_JSON_BODY'])) {
            $input = $GLOBALS['_JSON_BODY'];
        } else {
            $raw = @file_get_contents('php://input');
            $input = json_decode((string)$raw, true);
            // Fallback: if middleware stored the raw body, try decoding that too
            if ((empty($input) || !is_array($input)) && !empty($GLOBALS['_RAW_BODY'])) {
                $maybe = $GLOBALS['_RAW_BODY'];
                if (is_array($maybe)) {
                    $input = $maybe;
                } else {
                    $input = json_decode((string)$maybe, true);
                }
            }
        }

        if (!$input || !isset($input['visibility'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required visibility parameter.']);
            exit;
        }

        if (!$this->db) {
            error_log(static::class . "::post() Error: Database connection not available.");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server configuration error: Database not initialized.']);
            exit;
        }

        $content = isset($input['content']) ? trim((string)$input['content']) : '';
        $visibility = (string)$input['visibility'];
        $allowedVisibilities = ['public', 'friends', 'private'];

        if (!in_array($visibility, $allowedVisibilities)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid visibility value.']);
            exit;
        }
        if (mb_strlen($content) > 100000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Post content is too long.']);
            exit;
        }

        $hasText = !empty($content);
        $hasImage = !empty($input['image']);
        $hasCloudFile = !empty($input['cloud_file_id']);
        $hasLocation = !empty($input['location_name']);
        $isShare = !empty($input['shared_post_id']);

        if (!$hasText && !$hasImage && !$hasCloudFile && !$hasLocation && !$isShare) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Post must have content, media, a location, or be a share.']);
            exit;
        }

        $authorId = $this->currentUserId;
        $dbProfileUserId = null;
        if (isset($input['profile_owner_id']) && filter_var($input['profile_owner_id'], FILTER_VALIDATE_INT) && $input['profile_owner_id'] > 0) {
            $profileWallOwnerId = (int)$input['profile_owner_id'];
            $dbProfileUserId = ($profileWallOwnerId === $authorId) ? null : $profileWallOwnerId;
        }

        $dataToInsert = [
            'content' => $content,
            'visibility' => $visibility,
        ];

        // Match saicms behavior: prefer `user_id` for authorship. Only use
        // `author_id` as a fallback if `user_id` is not present in the schema.
        if ($this->hasColumn('posts', 'user_id')) {
            $dataToInsert['user_id'] = $authorId;
            // If author_id exists and is NOT NULL in the schema, set it as well
            // to satisfy strict FK constraints on some installations.
            if ($this->hasColumn('posts', 'author_id')) {
                $nullable = $this->isColumnNullable('posts', 'author_id');
                if ($nullable === false) { // explicitly NOT NULL
                    $dataToInsert['author_id'] = $authorId;
                }
            }
        } elseif ($this->hasColumn('posts', 'author_id')) {
            $dataToInsert['author_id'] = $authorId;
        }

        // Only include profile_user_id if the column exists in the posts table
        if ($dbProfileUserId !== null && $this->hasColumn('posts', 'profile_user_id')) {
            $dataToInsert['profile_user_id'] = $dbProfileUserId;
        }

        if ($hasImage && filter_var($input['image'], FILTER_VALIDATE_URL)) {
            $dataToInsert['image'] = $input['image'];
        }
        if ($hasCloudFile && filter_var($input['cloud_file_id'], FILTER_VALIDATE_INT)) {
            $dataToInsert['cloud_file_id'] = (int)$input['cloud_file_id'];
        }
        if ($hasLocation) {
            $dataToInsert['location_name'] = substr(trim($input['location_name']), 0, 255);
        }
        if ($isShare && filter_var($input['shared_post_id'], FILTER_VALIDATE_INT)) {
            $dataToInsert['shared_post_id'] = (int)$input['shared_post_id'];
            $dataToInsert['post_type'] = 'share';
        }
        if (isset($input['post_type']) && in_array($input['post_type'], ['text', 'ai_code', 'media', 'live_stream', 'share'])) {
            $dataToInsert['post_type'] = $input['post_type'];
        }
        if (($dataToInsert['post_type'] ?? '') === 'ai_code' && isset($input['code_language'])) {
            $dataToInsert['code_language'] = substr(trim($input['code_language']), 0, 20);
            $dataToInsert['original_prompt'] = isset($input['original_prompt']) ? trim($input['original_prompt']) : null;
        }

        if ($hasText) {
            $metadata = null;
            if (class_exists('\\Ginto\\Controllers\\URLMDController')) {
                $urlExtractor = new \Ginto\Controllers\URLMDController();
                if (method_exists($urlExtractor, 'extractUrlMetadata')) {
                    $metadata = $urlExtractor->extractUrlMetadata($content);
                }
            } elseif (class_exists('\\App\\Controllers\\URLMDController')) {
                $urlExtractor = new \App\Controllers\URLMDController();
                if (method_exists($urlExtractor, 'extractUrlMetadata')) {
                    $metadata = $urlExtractor->extractUrlMetadata($content);
                }
            }
            if ($metadata && !empty($metadata['success'])) {
                $dataToInsert['link_url'] = $metadata['normalized_url'] ?? null;
                $dataToInsert['link_title'] = $metadata['title'] ?? null;
                $dataToInsert['link_description'] = $metadata['description'] ?? null;
                $dataToInsert['link_domain'] = $metadata['domain'] ?? null;
                $dataToInsert['link_image_url'] = $metadata['image'] ?? null;
            }
        }

        try {
            // Debug: log the data we're about to insert (sanitized)
            $debugPayload = $dataToInsert;
            foreach ($debugPayload as $k => $v) {
                if (is_string($v) && strlen($v) > 1000) { $debugPayload[$k] = substr($v, 0, 1000) . '...'; }
            }
            error_log(static::class . "::post() inserting payload: " . json_encode($debugPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $this->db->insert('posts', $dataToInsert);
            $newPostId = $this->db->id();
            if (!$newPostId) throw new \Exception('Failed to save post (no ID returned).');
        } catch (\Throwable $e) {
            error_log(static::class . "::post() Exception: " . $e->getMessage() . " -- payload: " . json_encode($dataToInsert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error while saving post.']);
            exit;
        }

        $newPostDetails = $this->fetchCompletePostData($newPostId);
        if (!$newPostDetails) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Post saved, but could not retrieve fresh details.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Post created successfully!', 'post' => $newPostDetails]);
        exit;
    }

    protected function fetchCompletePostData($postId, array $seenIds = []) {
        if (in_array($postId, $seenIds) || count($seenIds) >= self::MAX_SHARE_DEPTH) {
            error_log("Recursion limit reached for post ID {$postId}. Chain: " . implode(' -> ', $seenIds));
            return null;
        }
        $seenIds[] = $postId;

        if (!$this->db) { return null; }

        // Build a safe select list depending on whether optional columns exist
        // Include both `fullname` and `full_name` from users (different installs use different column names).
        // We'll alias them so we can pick whichever is populated.
        $selectFields = ['p.id','p.user_id','p.content','p.image','p.cloud_file_id','p.post_type','p.shared_post_id',
            'p.code_language','p.is_live_stream','p.stream_playback_uid','p.original_prompt','p.created_at','p.updated_at','p.location_name',
            'p.link_url','p.link_title','p.link_description','p.link_domain','p.link_image_url',
            'u.fullname','u.full_name AS full_name_alt','u.username','u.profile_picture','u.gender'
        ];
        if ($this->hasColumn('posts', 'profile_user_id')) { array_splice($selectFields, 2, 0, 'p.profile_user_id'); }
        if ($this->hasColumn('posts', 'author_id')) { $selectFields[] = 'p.author_id'; }
        if ($this->hasColumn('posts', 'visibility')) { $selectFields[] = 'p.visibility'; }

        try {
            // Try joining users on p.user_id first
            $post = $this->db->get('posts (p)', ['[>]users (u)' => ['p.user_id' => 'id']], $selectFields, ['p.id' => $postId]);
            // If the join returned no user info but table has author_id, try joining on author_id as a fallback
            if ($post && empty($post['full_name']) && empty($post['username']) && $this->hasColumn('posts', 'author_id')) {
                try {
                    $post = $this->db->get('posts (p)', ['[>]users (u)' => ['p.author_id' => 'id']], $selectFields, ['p.id' => $postId]);
                } catch (\Throwable $_) { /* ignore and fallthrough to other fallback below */ }
            }
        } catch (\Throwable $e) {
            // Fallback: try a minimal single-table fetch and then attach user info
            try {
                $post = $this->db->get('posts', '*', ['id' => $postId]);
                if ($post) {
                    $userIdToFetch = null;
                    if (!empty($post['user_id'])) {
                        $userIdToFetch = $post['user_id'];
                    } elseif (!empty($post['author_id'])) {
                        $userIdToFetch = $post['author_id'];
                    }
                    if ($userIdToFetch) {
                        $user = $this->db->get('users', ['fullname', 'username', 'profile_picture', 'gender'], ['id' => $userIdToFetch]);
                        if ($user) {
                            $post['full_name'] = $user['fullname'] ?? null;
                            $post['username'] = $user['username'] ?? null;
                            $post['profile_picture'] = $user['profile_picture'] ?? null;
                            $post['gender'] = $user['gender'] ?? null;
                        }
                    }
                }
            } catch (\Throwable $_) {
                return null;
            }
        }

        if (!$post) { return null; }

        // Canonical display name: prefer `users.fullname`, fall back to `users.full_name`, then `username`.
        if (empty($post['fullname'])) {
            if (!empty($post['full_name_alt'])) {
                $post['fullname'] = $post['full_name_alt'];
            } elseif (!empty($post['full_name'])) {
                $post['fullname'] = $post['full_name'];
            } else {
                $post['fullname'] = $post['username'] ?? null;
            }
        }

        // Keep legacy variants populated for clients that still read them.
        $post['full_name'] = $post['full_name'] ?? $post['fullname'];
        $post['fullName'] = $post['fullName'] ?? $post['fullname'];

        $post['user_avatar'] = $post['profile_picture'] ?: $this->generateFallbackAvatar($post['fullname'] ?? $post['username'] ?? 'User', 40);
        $post['like_count'] = $this->safeCount('likes', ['post_id' => $postId]);
        $post['comment_count'] = $this->safeCount('comments', ['post_id' => $postId]);
        $post['is_liked_by_current_user'] = $this->currentUserId ? (bool)$this->db->has('likes', ['post_id' => $postId, 'user_id' => $this->currentUserId]) : false;

        // Compatibility: some UIs expect `fullname` (no underscore). Ensure it's present
        // and prefers the full_name returned by the join, falling back to username.
        // Also ensure client-friendly variants are present for different frontends
        if (!isset($post['fullname'])) { $post['fullname'] = $post['full_name'] ?? $post['username'] ?? null; }
        if (!isset($post['fullName'])) { $post['fullName'] = $post['fullname'] ?? $post['username'] ?? null; }

        if (($post['post_type'] ?? '') === 'share' && !empty($post['shared_post_id'])) {
            $originalPostData = $this->fetchCompletePostData((int)$post['shared_post_id'], $seenIds);
            $post['original_post'] = $originalPostData;
        }

        return $post;
    }

    private function generateFallbackAvatar(string $name, int $size = 40): string {
        $initial = '?'; $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $nameParts = array_filter(explode(' ', $trimmedName));
            if (count($nameParts) > 0) {
                $firstLetter = strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'));
                if (count($nameParts) >= 2) {
                    $lastLetter = strtoupper(mb_substr(end($nameParts), 0, 1, 'UTF-8'));
                    $initial = $firstLetter . $lastLetter;
                    if (!preg_match('/^[A-ZÀ-ÖØ-Þ\d]{2}$/u', $initial)) { $initial = $firstLetter; }
                } else { $initial = $firstLetter; }
            }
            if (empty($initial) || !preg_match('/^[A-ZÀ-ÖØ-Þ\d]{1,2}$/u', $initial)) {
                $firstCharFromTrimmed = strtoupper(mb_substr($trimmedName, 0, 1, 'UTF-8'));
                $initial = preg_match('/^[A-ZÀ-ÖØ-Þ\d]$/u', $firstCharFromTrimmed) ? $firstCharFromTrimmed : '?';
            }
        }
        $hueSeed = crc32(strtolower($trimmedName)); $hue = $hueSeed % 360;
        $bgColor = "hsl({$hue}, 70%, 45%)"; $textColor = "hsl({$hue}, 25%, 95%)";
        $fontSizePercentage = (mb_strlen($initial, 'UTF-8') > 1) ? '40' : '50';
        $svg = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d" role="img" aria-label="Avatar for %s"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="%s" fill="%s" font-weight="bold">%s</text></svg>', $size, $size, htmlspecialchars($trimmedName, ENT_QUOTES, 'UTF-8'), htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8'), $fontSizePercentage, htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'), htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'));
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }

    private function getFriendIds(): array {
        if (!$this->currentUserId) { return []; }
        $friendships = $this->db->select('friends', ['user_id', 'friend_id'], ['status' => 'accepted', 'OR' => ['user_id' => $this->currentUserId, 'friend_id' => $this->currentUserId]]);
        if (!$friendships) { return []; }
        $friendIds = [];
        foreach ($friendships as $f) { $friendIds[] = ($f['user_id'] == $this->currentUserId) ? $f['friend_id'] : $f['user_id']; }
        return array_values(array_unique($friendIds));
    }

    /**
     * Check whether a column exists in the current DB for a given table.
     */
    protected function hasColumn(string $table, string $column): bool {
        if (!$this->db) return false;
        try {
            $stmt = $this->db->query("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . addslashes($table) . "' AND column_name = '" . addslashes($column) . "'");
            if ($stmt === false) return false;
            $row = $stmt->fetch();
            return !empty($row) && (!empty($row['c']) && (int)$row['c'] > 0);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return true if the column exists and is nullable, false if it exists and is NOT NULL.
     * Returns null if undetermined or DB unavailable.
     */
    protected function isColumnNullable(string $table, string $column): ?bool {
        if (!$this->db) return null;
        try {
            $stmt = $this->db->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . addslashes($table) . "' AND column_name = '" . addslashes($column) . "' LIMIT 1");
            if ($stmt === false) return null;
            $row = $stmt->fetch();
            if (empty($row) || !isset($row['IS_NULLABLE'])) return null;
            return strtoupper($row['IS_NULLABLE']) === 'YES';
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function feed() {
        header('Content-Type: application/json');
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 50]]);
        $offset = ($page - 1) * $limit;
        if (!$this->db) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Database service unavailable.']); exit; }

        // We only need post IDs here — avoid joins and alias-based ON clauses which
        // can fail on some DBs / configs. Build unaliased where conditions and select
        // directly from the `posts` table.
        $whereConditions = ['ORDER' => ['created_at' => 'DESC'], 'LIMIT' => [$offset, $limit]];
        $hasVisibilityColumn = $this->hasColumn('posts', 'visibility');

        if ($hasVisibilityColumn) {
            if ($this->currentUserId) {
                $friendIds = $this->getFriendIds();
                $or = ['visibility' => 'public', 'user_id' => $this->currentUserId];
                if (!empty($friendIds)) { $or[] = ['visibility' => 'friends', 'user_id' => $friendIds]; }
                $whereConditions['OR #visibility_filter'] = $or;
            } else {
                $whereConditions['visibility'] = 'public';
            }
        } else {
            if ($this->currentUserId) {
                $friendIds = $this->getFriendIds();
                $or = ['status' => 'published', 'user_id' => $this->currentUserId];
                if (!empty($friendIds)) { $or[] = ['user_id' => $friendIds]; }
                $whereConditions['OR #visibility_fallback'] = $or;
            } else {
                $whereConditions['status'] = 'published';
            }
        }

        try {
            $postIds = $this->db->select('posts', 'id', $whereConditions);
        } catch (\Throwable $e) {
            error_log('SocialController::feed() primary query failed: ' . $e->getMessage());
            // Last-resort fallback using minimal conditions
            $friendIds = $this->currentUserId ? $this->getFriendIds() : [];
            if ($this->currentUserId) {
                $or = ['status' => 'published', 'user_id' => $this->currentUserId];
                if (!empty($friendIds)) { $or[] = ['user_id' => $friendIds]; }
                $fallbackWhere = ['OR #visibility_fallback' => $or, 'ORDER' => $whereConditions['ORDER'], 'LIMIT' => $whereConditions['LIMIT']];
            } else {
                $fallbackWhere = ['status' => 'published', 'ORDER' => $whereConditions['ORDER'], 'LIMIT' => $whereConditions['LIMIT']];
            }
            try {
                $postIds = $this->db->select('posts', 'id', $fallbackWhere);
            } catch (\Throwable $_) {
                error_log('SocialController::feed() fallback query failed: ' . ($_ instanceof \Throwable ? $_->getMessage() : 'unknown'));
                http_response_code(500); echo json_encode(['success' => false, 'message' => 'Error fetching posts from database.']); exit;
            }
        }

        $processedPosts = [];
        foreach ($postIds as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) { continue; }
            $fullPostData = $this->fetchCompletePostData((int)$id);
            if ($fullPostData) { $processedPosts[] = $fullPostData; }
        }

        $countQueryConditions = $whereConditions; unset($countQueryConditions['LIMIT'], $countQueryConditions['ORDER']);
        // Count directly from posts to avoid ambiguous column errors from joins
        try {
            $totalPosts = (int)$this->db->count('posts', $countQueryConditions);
        } catch (\Throwable $_) {
            $totalPosts = 0;
        }

        echo json_encode(['success' => true, 'posts' => $processedPosts, 'pagination' => ['total_posts' => $totalPosts, 'per_page' => $limit, 'current_page' => $page, 'total_pages' => (int)ceil($totalPosts / $limit)]]);
        exit;
    }

    /**
     * Render the social feed page (HTML)
     */
    public function index()
    {
        // Pass minimal data; View will ensure CSRF token is present
        $expiryTime = null;
        return $this->view('social/home', ['expiryTime' => $expiryTime]);
    }

    public function getPost($postId) {
        header('Content-Type: application/json');
        $postId = filter_var($postId, FILTER_VALIDATE_INT);
        if (!$postId || $postId <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid Post ID.']); exit; }
        $postData = $this->fetchCompletePostData($postId);
        if (!$postData) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Post not found or inaccessible.']); exit; }
        $canView = false;
        if ($postData['visibility'] === 'public') { $canView = true; }
        elseif ($this->currentUserId && $postData['user_id'] == $this->currentUserId) { $canView = true; }
        if (!$canView) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'You do not have permission to view this post.']); exit; }
        echo json_encode(['success' => true, 'post' => $postData]); exit;
    }

    public function getPostById($postId) { $this->getPost($postId); }

    protected function getFeaturedVideoId() { $availableVideoIds = ['1IxG7ywSNXk','TjJeR_9TAKg','c7R94ykz0po','5qGWeUdh78U','g8qoTKdXJzM']; return $availableVideoIds[array_rand($availableVideoIds)]; }

    public function showAdsEndpoint() {
        header('Content-Type: application/json');
        $youtubeVideoId = $this->getFeaturedVideoId();
        if (empty($youtubeVideoId)) { echo json_encode(['success' => false, 'message' => 'No video ID available for ad.']); exit; }
        $embedHtml = <<<HTML
<div style="position: relative; overflow: hidden; width: 100%; padding-bottom: 56.25%; margin-bottom: 1rem;">
    <iframe
        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
        src="https://www.youtube.com/embed/{$youtubeVideoId}"
        title="Featured Video"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen
        loading="lazy">
    </iframe>
</div>
HTML;
        echo json_encode(['success' => true, 'embedHtml' => $embedHtml]); exit;
    }

    private function safeCount(string $table, $where = []) : int {
        if (!$this->db) return 0;
        try { $res = $this->db->count($table, $where); return is_numeric($res) ? (int)$res : 0; } catch (\Throwable $e) { return 0; }
    }

    private function createNotification(int $userIdToNotify, string $type, string $message, ?array $contextData = null) {
        $actorId = $this->currentUserId;
        // Defensive: do not create notifications with invalid target user IDs or when actor is missing
        if (!$actorId || $userIdToNotify === $actorId || $userIdToNotify <= 0) {
            if ($userIdToNotify <= 0) {
                error_log(static::class . "::createNotification() skipped: invalid userIdToNotify={$userIdToNotify}, Actor={$actorId}, Type={$type}");
            }
            return;
        }
        try {
            $dataToInsert = [
                'user_id' => $userIdToNotify,
                'actor_user_id' => $actorId,
                'type' => $type,
                'message' => $message,
                'is_read' => 0
            ];
            $jsonContext = [];
            if (isset($contextData['post_id'])) $jsonContext['post_id'] = $contextData['post_id'];
            if (isset($contextData['like_id'])) $jsonContext['like_id'] = $contextData['like_id'];
            if (isset($contextData['comment_id'])) $jsonContext['comment_id'] = $contextData['comment_id'];
            if (isset($contextData['share_post_id'])) $jsonContext['share_post_id'] = $contextData['share_post_id'];
            if (!empty($jsonContext)) { $dataToInsert['context_json'] = json_encode($jsonContext); }
            $this->db->insert('notifications', $dataToInsert);
        } catch (\Throwable $e) {
            error_log(static::class . "::createNotification() error: " . $e->getMessage());
        }
    }
}
