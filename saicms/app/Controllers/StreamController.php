<?php

namespace App\Controllers;

use Core\Controller;
use Exception;
use Medoo\Medoo;

class StreamController extends Controller
{

    public function __construct()
    {
        parent::__construct(); // This should initialize $this->db from Core\Controller
                               // Ensure Core\Controller actually does this reliably.
    }

    // Helper function to redact sensitive data from arrays before logging
    private function redactSensitiveData(array $data, array $sensitiveKeys = ['token', 'key', 'secret', 'authorization', 'password', 'apikey', 'cf-api-key', 'cf-api-email', 'bearer']): array
    {
        $processedData = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $processedData[$k] = $this->redactSensitiveData($v, $sensitiveKeys);
            } elseif (is_string($k) && in_array(strtolower($k), $sensitiveKeys, true)) {
                if (is_string($v) && !empty($v)) {
                    $processedData[$k] = '[REDACTED_' . strtoupper($k) . ']';
                } else {
                    $processedData[$k] = '[REDACTED_EMPTY_OR_NON_STRING]';
                }
            } else {
                $processedData[$k] = $v;
            }
        }
        return $processedData;
    }
   
    // YOUR EXISTING getFileCategoryByExtension() METHOD - UNTOUCHED
    private function getFileCategoryByExtension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (empty($extension)) {
            return 'other';
        }
        $extensionWithDot = '.' . $extension;
        foreach (self::ALLOWED_EXTENSIONS as $category => $extensionsInCategory) {
            if (in_array($extensionWithDot, $extensionsInCategory, true)) {
                return $category;
            }
        }
        return 'other';
    }


    // Helper function to make the Cloudflare API call
    private function getCloudflareWebRTCInputDetails($accountId, $apiToken) {
        if (empty($accountId) || empty($apiToken)) {
            // Log server-side, but don't expose details to client beyond a generic message
            error_log("Cloudflare Account ID or API Token is missing for StreamController.");
            return ['success' => false, 'error' => 'Server configuration error related to streaming credentials.', 'httpCode' => 500];
        }

        $apiUrl = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream/live_inputs";
        $headers = [
            "Authorization: Bearer " . $apiToken, // This is the main API token, used for this server-to-CF call only
            "Content-Type: application/json",
            "User-Agent: MyAppStreamPlatform/1.0"
        ];

        $shorterTimeoutSeconds = 10; // 10 seconds. Very short. For recording finalization after inactivity.

        $postData = json_encode([
            "meta" => ["name" => "Live Stream via MyApp " . date("Y-m-d H:i:s")],
            "recording" => [
                "mode" => "automatic",
                "timeoutSeconds" => $shorterTimeoutSeconds,
                "requireSignedURLs" => false // This is for playback of the VOD, not for WHIP ingest
            ]
            // Note: For WebRTC ingest (WHIP), Cloudflare typically doesn't require a separate token via this API.
            // The WHIP URL itself is the endpoint.
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("cURL Error to Cloudflare (StreamController): " . $curlError);
            return ['success' => false, 'error' => 'Connection error to streaming service: ' . $curlError, 'httpCode' => 503];
        }

        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log the raw response cautiously, or a snippet of it if it's too large/binary
            $loggableResponse = (strlen($response) > 1024) ? substr($response, 0, 1024) . '... [TRUNCATED]' : $response;
            error_log("JSON decode error from Cloudflare response (StreamController): " . json_last_error_msg() . " | Response: " . $loggableResponse);
            return ['success' => false, 'error' => 'Invalid response format from streaming service.', 'httpCode' => 502];
        }

        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['success']) && $responseData['success'] === true) {
            $result = $responseData['result'];
            $whipUrl = $result['webRTC']['url'] ?? null; // WHIP URL for WebRTC ingest
            $playbackUid = $result['uid'] ?? null;     // UID for playback (live and VOD) and live input ID

            if (!$whipUrl || !$playbackUid) {
                $missingDetails = [];
                if (!$whipUrl) $missingDetails[] = "WHIP URL";
                if (!$playbackUid) $missingDetails[] = "Playback UID/LiveInputID";
                $errorDetailMsg = implode(" and ", $missingDetails) . " missing in successful Cloudflare response.";
                // Log detailed (redacted) Cloudflare response for server-side debugging
                error_log($errorDetailMsg . " (StreamController): " . json_encode($this->redactSensitiveData($result)));
                // Client gets a more generic error
                return ['success' => false, 'error' => 'Essential stream details (' . strtolower(implode(", ", $missingDetails)) . ') not available from streaming service.', 'httpCode' => 500];
            }

            // SUCCESS: Return only necessary, non-sensitive details to the client.
            // The main API Token ($apiToken) or any specific 'apiTokenForWhip' is NOT sent to the client.
            // Cloudflare WHIP URLs are generally designed to be used directly.
            return [
                'success' => true,
                'whipUrl' => $whipUrl,
                'playbackUid' => $playbackUid,      // UID for playback and also identifies the Live Input
                'liveInputId' => $playbackUid,       // For your app's internal reference, same as playbackUid
                'httpCode' => $httpCode
            ];
        } else {
            $errorMsg = 'Unknown API error from streaming service.';
            if (!empty($responseData['errors']) && is_array($responseData['errors']) && !empty($responseData['errors'][0]['message'])) {
                $errorMsg = $responseData['errors'][0]['message'];
            }
            // Log detailed (redacted) Cloudflare response for server-side debugging
            error_log("Cloudflare API Error (StreamController - HTTP $httpCode): " . $errorMsg . " | Full Response: " . json_encode($this->redactSensitiveData($responseData)));
            // Client gets the primary error message from Cloudflare or a generic one.
            return ['success' => false, 'error' => "Streaming service API error: " . $errorMsg, 'httpCode' => $httpCode];
        }
    }

    public function stream() {
        $cfAccountId = $_ENV['CF_ACCOUNT_ID'] ?? getenv('CF_ACCOUNT_ID');
        $cfApiToken  = $_ENV['CF_API_TOKEN'] ?? getenv('CF_API_TOKEN');

        if (isset($_GET['action']) && $_GET['action'] === 'get_stream_details_for_view') {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $result = $this->getCloudflareWebRTCInputDetails($cfAccountId, $cfApiToken);
            
            http_response_code(isset($result['httpCode']) ? $result['httpCode'] : ($result['success'] ? 200 : 500));
            echo json_encode($result); // $result is now sanitized by getCloudflareWebRTCInputDetails
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['info' => 'Access /post/stream?action=get_stream_details_for_view to get stream details.', 'current_config_status' => 'OK']);
        exit;
    }

    public function createPostWithStream()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred to create stream post.', 'post' => null];
        $currentUserId = $_SESSION['user_id'] ?? null;
        $streamTitleForLog = 'N/A'; // For logging context

        try {
            if (!$currentUserId) {
                $response['message'] = 'Authentication required.'; http_response_code(401);
                throw new Exception("User not authenticated for createPostWithStream.");
            }
            $currentUserId = (int) $currentUserId;

            $postContent = trim($_POST['post_content'] ?? '');
            $postVisibility = $_POST['visibility'] ?? 'public';
            $cfLiveInputId = $_POST['cf_live_input_id'] ?? null;
            $cfPlaybackUid = $_POST['cf_playback_uid'] ?? null;
            
            $streamTitleForLog = $postContent ?: "Live Stream from User {$currentUserId}, InputID: {$cfLiveInputId}";


            if (empty($cfLiveInputId) || empty($cfPlaybackUid)) {
                $response['message'] = 'Missing Cloudflare stream details (Input ID or Playback UID).'; http_response_code(400);
                throw new Exception("Missing CF Live Input ID or Playback UID for createPostWithStream. User: {$currentUserId}");
            }
            
            $this->_pm_validatePostTableVisibility($postVisibility);

            $this->db->pdo->beginTransaction();

            $originalFileName = "Live Stream - " . $cfLiveInputId . " - " . date("Y-m-d H-i-s");
            $cloudFileRecordVisibility = 'unlisted'; 
            if ($postVisibility === 'public') {
                $cloudFileRecordVisibility = 'public';
            } elseif ($postVisibility === 'private') {
                $cloudFileRecordVisibility = 'private';
            }

            $cloudFileData = [
                'user_id' => $currentUserId,
                'storage_provider' => 'cloudflare_stream',
                'provider_file_id' => $cfLiveInputId,      
                'file_path_in_provider' => $cfPlaybackUid, 
                'container_name' => $_ENV['CF_ACCOUNT_ID'] ?? 'cloudflare_live',
                'container_id' => null, 
                'original_filename' => $originalFileName,
                'content_type' => 'video/live-stream', 
                'size_bytes' => 0, 
                'content_sha1' => null, 
                'file_category' => 'video',
                'visibility' => $cloudFileRecordVisibility,
                'uploaded_at_provider' => Medoo::raw('NOW()'),
                'title' => $postContent ?: $originalFileName,
                'description' => "Live video stream started on " . date("F j, Y, g:i a"),
            ];
            $this->db->insert('cloud_files', $cloudFileData);
            $cloudFileRecordId = (int)$this->db->id();
            if (!$cloudFileRecordId) {
                throw new Exception("DB Error: Failed to insert live stream record into cloud_files for User: {$currentUserId}, InputID: {$cfLiveInputId}.");
            }

            $postThumbnailUrl = "https://videodelivery.net/{$cfLiveInputId}/thumbnails/thumbnail.jpg";

            $postDataForDb = [
                'user_id' => $currentUserId,
                'author_id' => $currentUserId,
                'content' => $postContent,
                'image' => $postThumbnailUrl,
                'cloud_file_id' => $cloudFileRecordId,
                'visibility' => $postVisibility,
                'post_type' => 'media',
                'created_at' => Medoo::raw('NOW()'),
                'updated_at' => Medoo::raw('NOW()')
            ];
            $this->db->insert('posts', $postDataForDb);
            $newPostId = (int)$this->db->id();
            if (!$newPostId) {
                throw new Exception("DB Error: Failed to insert live stream post for User: {$currentUserId}, InputID: {$cfLiveInputId}, CloudFileID: {$cloudFileRecordId}.");
            }

            $this->db->pdo->commit();

            $newlyCreatedPostFull = $this->_pm_fetchFullPostData($newPostId);
            if (!$newlyCreatedPostFull) {
                 // Log this, as it's an inconsistency. Client will get an error from the outer catch.
                 error_log("Failed to fetch newly created stream post data (PostID: {$newPostId}, User: {$currentUserId}) after successful creation.");
                 // We can still return success with partial data or indicate a fetch issue.
                 // For simplicity, we'll let the outer catch handle it if this is critical.
                 // However, the post IS created.
                 // To be robust, one might return a simpler success message here if fetch fails but DB ops succeeded.
                 // Or, throw a specific exception.
                 throw new Exception("Post created (ID: {$newPostId}), but failed to fetch its full data for response.");
            }
            $newlyCreatedPostFull['is_live_stream'] = true;

            $response = ['success' => true, 'message' => 'Live stream post created successfully!', 'post' => $newlyCreatedPostFull];
            http_response_code(201);

        } catch (Exception $e) {
            if ($this->db->pdo && $this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            // Log detailed error for server admin
            $detailedError = "createPostWithStream Exception (User:{$currentUserId}, StreamTitle:'{$streamTitleForLog}'): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            error_log($detailedError);

            // Set generic error message for client if not already set by a specific check
            if ($response['message'] === 'An unexpected error occurred to create stream post.') {
                 $response['message'] = "Server error creating stream post. Please try again.";
            }
             // Ensure a 5xx or 4xx HTTP code is set if not already
            if (http_response_code() < 400 ) { // e.g. if it's still 200 or 201
                http_response_code(500); // Default to internal server error
            }
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // Helper function _pm_validatePostTableVisibility (borrow from UploadController or make common)
    private function _pm_validatePostTableVisibility(string &$visibility): void {
        $validVisibilities = ['public', 'friends', 'private'];
        if (!in_array($visibility, $validVisibilities, true)) {
            $visibility = 'public'; // Default if invalid
        }
    }
    
    // Helper function _pm_fetchFullPostData (borrow from UploadController or make common utility)
    private function _pm_fetchFullPostData(int $postId): ?array
    {
        if (!$this->db) {
            error_log("_pm_fetchFullPostData (StreamController): DB not available for post ID {$postId}.");
            return null;
        }
        $postData = $this->db->get('posts', [
            "[>]users(u)" => ["posts.user_id" => "id"],
            "[>]cloud_files(cf)" => ["posts.cloud_file_id" => "id"]
        ], [
            'posts.id', 'posts.user_id', 'posts.content', 'posts.image', 
            'posts.visibility', 'posts.post_type', 'posts.created_at', 'posts.updated_at',
            'u.fullname AS full_name', 'u.username', 
            'u.profile_picture(user_avatar_url)',
            'cf.content_type(media_mime_type)',
            'cf.storage_provider',
            'cf.provider_file_id(cf_provider_file_id)', 
            'cf.file_path_in_provider(cf_playback_uid_or_path)'
        ], [ 'posts.id' => $postId ]);

        if ($postData) {
            $postData['id'] = (int)$postData['id'];
            $postData['user_id'] = (int)$postData['user_id'];
            $postData['user_avatar'] = $postData['user_avatar_url'] ?: $this->_pm_generateFallbackAvatar(
                $postData['full_name'] ?? $postData['username'] ?? 'User'
            );
            unset($postData['user_avatar_url']); // Clean up
            // These would typically be fetched from separate tables or aggregated queries
            $postData['like_count'] = 0; 
            $postData['comment_count'] = 0;
            $postData['is_liked_by_current_user'] = false;
            
            if ($postData['storage_provider'] === 'cloudflare_stream' && !empty($postData['cf_playback_uid_or_path'])) {
                $postData['is_live_stream'] = true;
                $postData['stream_playback_uid'] = $postData['cf_playback_uid_or_path'];
                $postData['stream_live_input_id'] = $postData['cf_provider_file_id'];
            } else {
                $postData['is_live_stream'] = false;
            }
        } else {
            error_log("_pm_fetchFullPostData (StreamController): Post not found ID {$postId}.");
        }
        return $postData;
    }

    // Helper function _pm_generateFallbackAvatar (borrow from UploadController or make common utility)
    private function _pm_generateFallbackAvatar(string $name, int $size = 40): string {
        $initial = '?'; $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $nameParts = explode(' ', $trimmedName); $nameParts = array_filter($nameParts);
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
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d" role="img" aria-label="Avatar for %s"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="%s" fill="%s" font-weight="bold">%s</text></svg>',
            $size, $size, htmlspecialchars($trimmedName, ENT_QUOTES, 'UTF-8'), htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8'), 
            $fontSizePercentage, htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'), htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
        );
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }

}