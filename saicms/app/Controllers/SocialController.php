<?php
namespace App\Controllers;

use Core\Controller;

class SocialController extends Controller {

    /**
     * A hard limit to prevent excessively deep (but not necessarily circular) shares
     * from consuming too many resources or hitting PHP's nesting limits.
     */
    private const MAX_SHARE_DEPTH = 10;

    protected $currentUserId = null;

    public function __construct() {
        parent::__construct(); // Ensures $this->db is initialized

        if (isset($_SESSION['user_id'])) {
            $this->currentUserId = (int) $_SESSION['user_id'];
        } else {
            $this->currentUserId = null;
        }
    }

    public function post()
    {
        // Delegated implementation copied from HomeController.post()
        header('Content-Type: application/json');

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not logged in or session invalid.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

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

        $content = isset($input['content']) ? trim((string) $input['content']) : '';
        $visibility = (string) $input['visibility'];
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
            "user_id" => $authorId,
            "profile_user_id" => $dbProfileUserId,
            "content" => $content,
            "visibility" => $visibility,
        ];

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
            $urlExtractor = new URLMDController();
            $metadata = $urlExtractor->extractUrlMetadata($content);
            if ($metadata && $metadata['success']) {
                $dataToInsert['link_url'] = $metadata['normalized_url'];
                $dataToInsert['link_title'] = $metadata['title'];
                $dataToInsert['link_description'] = $metadata['description'];
                $dataToInsert['link_domain'] = $metadata['domain'];
                $dataToInsert['link_image_url'] = $metadata['image'];
            }
        }

        try {
            $this->db->insert("posts", $dataToInsert);
            $newPostId = $this->db->id();
            if (!$newPostId) throw new \Exception('Failed to save post (no ID returned).');
        } catch (\PDOException $e) {
            error_log(static::class . "::post() PDOException: " . $e->getMessage());
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

        echo json_encode([
            'success' => true,
            'message' => 'Post created successfully!',
            'post' => $newPostDetails
        ]);
        exit;
    }

    protected function fetchCompletePostData($postId, array $seenIds = []) {
        if (in_array($postId, $seenIds) || count($seenIds) >= self::MAX_SHARE_DEPTH) {
            error_log("Recursion limit reached for post ID {$postId}. Chain: " . implode(' -> ', $seenIds));
            return null;
        }
        $seenIds[] = $postId;

        if (!$this->db) {
            error_log("[fetchCompletePostData] Database connection not available for post ID: " . $postId);
            return null;
        }

        $post = $this->db->get("posts (p)",
            ["[>]users (u)" => ["p.user_id" => "id"]],
            [
                "p.id", "p.user_id", "p.profile_user_id", "p.content", "p.image",
                "p.cloud_file_id", "p.visibility", "p.post_type", "p.shared_post_id",
                "p.code_language", "p.is_live_stream", "p.stream_playback_uid", "p.original_prompt",
                "p.created_at", "p.updated_at",
                "p.location_name",
                "p.link_url", "p.link_title", "p.link_description",
                "p.link_domain", "p.link_image_url",
                "u.fullname AS full_name", "u.username", "u.profile_picture", "u.gender"
            ],
            ["p.id" => $postId]
        );

        if (!$post) {
            error_log("[fetchCompletePostData] Post not found for ID: " . $postId);
            return null;
        }

        $post['user_avatar'] = $post['profile_picture'] ?: $this->generateFallbackAvatar($post['full_name'] ?? $post['username'] ?? 'User', 40);
        $post['like_count'] = $this->safeCount('likes', ["post_id" => $postId]);
        $post['comment_count'] = $this->safeCount('comments', ["post_id" => $postId]);
        $post['is_liked_by_current_user'] = $this->currentUserId ? (bool)$this->db->has("likes", ["post_id" => $postId, "user_id" => $this->currentUserId]) : false;

        if ($post['post_type'] === 'share' && !empty($post['shared_post_id'])) {
            $originalPostData = $this->fetchCompletePostData((int)$post['shared_post_id'], $seenIds);
            $post['original_post'] = $originalPostData;
        }

        return $post;
    }

    private function generateFallbackAvatar(string $name, int $size = 40): string {
        $initial = '?'; $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $nameParts = explode(' ', $trimmedName);
            $nameParts = array_filter($nameParts);
            if (count($nameParts) > 0) {
                $firstLetter = strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'));
                if (count($nameParts) >= 2) {
                    $lastLetter = strtoupper(mb_substr(end($nameParts), 0, 1, 'UTF-8'));
                    $initial = $firstLetter . $lastLetter;
                    if (!preg_match('/^[A-ZÀ-ÖØ-Þ\\d]{2}$/u', $initial)) {
                        $initial = $firstLetter;
                    }
                } else {
                    $initial = $firstLetter;
                }
            }
            if (empty($initial) || !preg_match('/^[A-ZÀ-ÖØ-Þ\\d]{1,2}$/u', $initial)) {
                $firstCharFromTrimmed = strtoupper(mb_substr($trimmedName, 0, 1, 'UTF-8'));
                $initial = preg_match('/^[A-ZÀ-ÖØ-Þ\\d]$/u', $firstCharFromTrimmed) ? $firstCharFromTrimmed : '?';
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

    private function getFriendIds(): array {
        if (!$this->currentUserId) {
            return [];
        }
        $friendships = $this->db->select('friends', ['user_id', 'friend_id'], [
            "status" => "accepted",
            "OR" => ["user_id" => $this->currentUserId, "friend_id" => $this->currentUserId]
        ]);
        if (!$friendships) { return []; }
        $friendIds = [];
        foreach ($friendships as $friendship) {
            $friendIds[] = ($friendship['user_id'] == $this->currentUserId) ? $friendship['friend_id'] : $friendship['user_id'];
        }
        return array_unique($friendIds);
    }

    public function feed() {
        header('Content-Type: application/json');

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 50]]);
        $offset = ($page - 1) * $limit;

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service unavailable.']);
            exit;
        }

        $selectColumns = ["p.id"];

        $whereConditions = [
            "ORDER" => ["p.created_at" => "DESC"],
            "LIMIT" => [$offset, $limit]
        ];

        if ($this->currentUserId) {
            $friendIds = $this->getFriendIds();

            $visibilityRules = [
                "p.visibility" => "public",
                "p.user_id" => $this->currentUserId
            ];

            if (!empty($friendIds)) {
                $visibilityRules["AND #friends_posts"] = [
                    "p.visibility" => "friends",
                    "p.user_id" => $friendIds
                ];
            }

            $whereConditions["OR #visibility_filter"] = $visibilityRules;
        } else {
            $whereConditions["p.visibility"] = "public";
        }

        $postIds = $this->db->select("posts (p)", ["[>]users (u)" => ["p.user_id" => "id"]], $selectColumns, $whereConditions);

        if ($postIds === false) {
            error_log("Error fetching feed posts: " . json_encode($this->db->errorInfo()));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching posts from database.']);
            exit;
        }

        $processedPosts = [];
        foreach ($postIds as $postId) {
            $fullPostData = $this->fetchCompletePostData($postId);
            if ($fullPostData) {
                $processedPosts[] = $fullPostData;
            }
        }

        $countQueryConditions = $whereConditions;
        unset($countQueryConditions['LIMIT'], $countQueryConditions['ORDER']);
        $totalPosts = (int)$this->db->count("posts (p)", ["[>]users (u)" => ["p.user_id" => "id"]], "p.id", $countQueryConditions);

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

    public function getPost($postId) {
        header('Content-Type: application/json');
        $postId = filter_var($postId, FILTER_VALIDATE_INT);
        if (!$postId || $postId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Post ID.']);
            exit;
        }

        $postData = $this->fetchCompletePostData($postId);

        if (!$postData) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Post not found or inaccessible.']);
            exit;
        }

        $canView = false;
        if ($postData['visibility'] === 'public') {
            $canView = true;
        } elseif ($this->currentUserId && $postData['user_id'] == $this->currentUserId) {
            $canView = true;
        }

        if (!$canView) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to view this post.']);
            exit;
        }

        echo json_encode(['success' => true, 'post' => $postData]);
        exit;
    }

    public function getPostById($postId) {
        $this->getPost($postId);
    }

    protected function getFeaturedVideoId() {
        $availableVideoIds = ['1IxG7ywSNXk', 'TjJeR_9TAKg', 'c7R94ykz0po', '5qGWeUdh78U', 'g8qoTKdXJzM'];
        return $availableVideoIds[array_rand($availableVideoIds)];
    }

    public function showAdsEndpoint() {
        header('Content-Type: application/json');
        $youtubeVideoId = $this->getFeaturedVideoId();

        if (empty($youtubeVideoId)) {
            echo json_encode(['success' => false, 'message' => 'No video ID available for ad.']);
            exit;
        }

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

        echo json_encode(['success' => true, 'embedHtml' => $embedHtml]);
        exit;
    }
}
