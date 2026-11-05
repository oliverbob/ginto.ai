<?php

namespace App\Controllers;

use Core\Controller;
use Exception; // For throwing and catching exceptions
use Medoo\Medoo; // For Medoo::raw()

class StoriesController extends Controller
{
    // B2 and CDN Configuration properties
    private string $b2AccountId;
    private string $b2AppKey;
    private string $b2BucketId;
    private string $b2BucketName;
    private string $fileCdnBaseUrl;

    // Constants for Story Media
    private const STORY_MAX_FILE_SIZE_MB = 25;
    private const STORY_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    private const STORY_ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function __construct()
    {
        parent::__construct();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $this->b2AccountId = $_ENV['B2_ACCOUNT_ID'] ?? '';
        $this->b2AppKey    = $_ENV['B2_APP_KEY'] ?? '';
        $this->b2BucketId  = $_ENV['B2_BUCKET_ID'] ?? '';
        $this->b2BucketName = $_ENV['B2_BUCKET_NAME'] ?? '';
        // Ensure fileCdnBaseUrl always has a trailing slash if set, and is not just '/'
        $cdnBase = $_ENV['FILE_CDN_BASE_URL'] ?? '';
        $this->fileCdnBaseUrl = !empty($cdnBase) && $cdnBase !== '/' ? rtrim($cdnBase, '/') . '/' : '';


        if (empty($this->b2AccountId) || empty($this->b2AppKey) || empty($this->b2BucketId) || empty($this->b2BucketName)) {
            error_log('CRITICAL ERROR (StoriesController): Backblaze B2 configuration is incomplete in .env file.');
        }
        if (empty($this->fileCdnBaseUrl)) { // Check if it's empty after processing
            error_log('CRITICAL ERROR (StoriesController): FILE_CDN_BASE_URL is missing or invalid in .env file.');
        }
    }

    public function storiesView() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $profilePictureUrl = null;
        $sessionProfilePicPath = $_SESSION['user_profile_picture'] ?? null;

        error_log('StoriesView - Session user_profile_picture raw: ' . ($sessionProfilePicPath ?? 'NOT SET'));

        if (!empty($sessionProfilePicPath)) {
            if (strpos(strtolower($sessionProfilePicPath), 'http://') === 0 || strpos(strtolower($sessionProfilePicPath), 'https://') === 0) {
                $profilePictureUrl = $sessionProfilePicPath; // Already a full URL
                error_log("StoriesView - Profile picture from session is already full URL: " . $profilePictureUrl);
            } elseif (!empty($this->fileCdnBaseUrl)) {
                $profilePictureUrl = $this->fileCdnBaseUrl . ltrim($sessionProfilePicPath, '/'); // Prepend CDN base
                error_log("StoriesView - Constructed profile picture URL from session path: " . $profilePictureUrl);
            } else {
                error_log("StoriesView - fileCdnBaseUrl is not properly configured. Cannot form full profile picture URL from session path: " . $sessionProfilePicPath);
            }
        } else {
             error_log("StoriesView - No profile picture path in session.");
        }

        $currentUserForView = [
            'id' => $_SESSION['user_id'] ?? null,
            'fullName' => $_SESSION['user_full_name'] ?? 'User',
            'profilePicture' => $profilePictureUrl, // FULL CDN URL or null
            'username' => $_SESSION['user_username'] ?? ''
        ];
        $this->view('stories', ['currentUser' => $currentUserForView]);
    }

    public function getActiveStories()
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
        $now = date('Y-m-d H:i:s');
        $currentUserId = (int)$_SESSION['user_id'];

        $storiesData = $this->db->select("stories", [
            "[>]users(u)" => ["stories.user_id" => "id"],
            "[>]cloud_files(cf)" => ["stories.cloud_file_id" => "id"]
        ], [
            "stories.id", "stories.user_id", "stories.cloud_file_id", "stories.content_type",
            "stories.media_url_override", "stories.text_overlay", "stories.code_content", "stories.code_language",
            "stories.link_url", "stories.link_preview_data", "stories.background_color", "stories.font_family",
            "stories.theme_category", "stories.duration_seconds", "stories.created_at", "stories.expires_at",
            "u.fullname AS full_name", "u.username", "u.profile_picture(user_profile_picture_path)",
            "cf.file_path_in_provider", "cf.storage_provider" // storage_provider might be useful for B2 helper
        ], [
            "stories.expires_at[>]" => $now,
            "stories.visibility" => "public",
            "ORDER" => ["stories.created_at" => "DESC"], "LIMIT" => 20
        ]);

        if ($storiesData === false) {
            error_log("DB error fetching stories: " . json_encode($this->db->errorInfo()));
            http_response_code(500); echo json_encode(['success' => false, 'message' => 'Error fetching stories.']); exit;
        }

        $processedStories = [];
        foreach ($storiesData as $story) {
            // Construct final_media_url for story content
            $story['final_media_url'] = null;
            if (!empty($story['cloud_file_id']) && !empty($story['file_path_in_provider'])) {
                if (!empty($this->fileCdnBaseUrl)) { // Check if CDN base URL is configured
                    $story['final_media_url'] = $this->fileCdnBaseUrl . ltrim($story['file_path_in_provider'], '/');
                } else {
                    error_log("getActiveStories - fileCdnBaseUrl not configured for story media_url. Story ID: " . $story['id']);
                }
            } elseif (!empty($story['media_url_override'])) {
                $story['final_media_url'] = $story['media_url_override']; // Assumes override is already a full URL
            }

            // Construct user_avatar URL for story author
            $story['user_avatar'] = null;
            $profilePicturePathFromDb = $story['user_profile_picture_path'];
            if (!empty($profilePicturePathFromDb)) {
                if (strpos(strtolower($profilePicturePathFromDb), 'http://') === 0 || strpos(strtolower($profilePicturePathFromDb), 'https://') === 0) {
                    $story['user_avatar'] = $profilePicturePathFromDb;
                } elseif (!empty($this->fileCdnBaseUrl)) {
                    $story['user_avatar'] = $this->fileCdnBaseUrl . ltrim($profilePicturePathFromDb, '/');
                } else {
                     error_log("getActiveStories - fileCdnBaseUrl not configured for user_avatar. User ID: " . $story['user_id']);
                }
            }

            if ($story['content_type'] === 'link_preview' && !empty($story['link_preview_data'])) {
                $decoded_preview = json_decode($story['link_preview_data'], true);
                $story['link_preview_data'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded_preview : null;
            }
            unset($story['file_path_in_provider'], $story['storage_provider'], $story['user_profile_picture_path']);
            $processedStories[] = $story;
        }
        echo json_encode(['success' => true, 'stories' => $processedStories]);
        exit;
    }

    public function createStoryWithMedia()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred.', 'story' => null];
        $currentUserId = $_SESSION['user_id'] ?? null;
        $originalFileNameForLog = 'N/A';

        try {
            if (!$currentUserId) {
                $response['message'] = 'Authentication required.'; http_response_code(401);
                throw new Exception("User not authenticated for createStoryWithMedia.");
            }
            $currentUserId = (int) $currentUserId;

            if (!$this->_isB2ConfiguredAndReady()) {
                $response['message'] = 'Server media configuration error.'; http_response_code(500);
                throw new Exception("B2/CDN not configured. User: {$currentUserId}");
            }

            $storyTextOverlay = trim($_POST['text_overlay'] ?? '');
            $storyVisibility = $_POST['visibility'] ?? 'public';
            $this->_validateStoryTableVisibility($storyVisibility);
            $storyContentType = $_POST['content_type'] ?? 'text_only';
            $this->_validateStoryContentType($storyContentType);
            $storyExpiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            if (isset($_POST['expires_duration_hours']) && is_numeric($_POST['expires_duration_hours'])) {
                $hours = max(1, min(7 * 24, (int)$_POST['expires_duration_hours']));
                $storyExpiresAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
            }
            $storyDurationSeconds = (isset($_POST['duration_seconds']) && is_numeric($_POST['duration_seconds']))
                ? max(3, min(60, (int)$_POST['duration_seconds'])) : 15;
            $storyCodeContent = ($storyContentType === 'code_snippet') ? ($_POST['code_content'] ?? null) : null;
            $storyCodeLanguage = ($storyContentType === 'code_snippet') ? ($_POST['code_language'] ?? null) : null;
            $storyBackgroundColor = ($storyContentType === 'text_only' || $storyContentType === 'code_snippet') ? ($_POST['background_color'] ?? null) : null;
            $cloudFileRecordId = null;
            $finalMediaUrlForStoryField = null;

            if (($storyContentType === 'image' || $storyContentType === 'video') && isset($_FILES['media_file'])) {
                if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                    $uploadErrorCode = $_FILES['media_file']['error'];
                    $response['message'] = $this->_pm_getUploadErrorMessage($uploadErrorCode);
                    http_response_code(400); throw new Exception("File upload error (code: {$uploadErrorCode}).");
                }
                $file = $_FILES['media_file']; $tempFilePath = $file['tmp_name'];
                $originalFileNameForLog = basename($file['name']); $fileSize = (int) $file['size'];
                if ($fileSize > (self::STORY_MAX_FILE_SIZE_MB * 1024 * 1024)) {
                    $response['message'] = 'File too large. Max: ' . self::STORY_MAX_FILE_SIZE_MB . 'MB.'; http_response_code(400);
                    throw new Exception("File too large ({$fileSize} bytes). Max: " . self::STORY_MAX_FILE_SIZE_MB . "MB.");
                }
                $detectedContentType = $this->_pm_getFileMimeType($tempFilePath, $file['type']);
                if (!$this->_isValidStoryMimeType($detectedContentType, $storyContentType)) {
                    $response['message'] = 'Invalid file type for story content.'; http_response_code(400);
                    throw new Exception("Invalid MIME type: {$detectedContentType} for story type {$storyContentType}.");
                }
                $fileData = $this->_pm_readFileData($tempFilePath);
                if ($fileData === null) {
                    $response['message'] = 'Could not read file data.'; http_response_code(500);
                    throw new Exception("Failed to read temp file data: {$tempFilePath}");
                }
                $this->db->pdo->beginTransaction();
                $fileExtension = strtolower(pathinfo($originalFileNameForLog, PATHINFO_EXTENSION));
                $uniqueNamePart = bin2hex(random_bytes(16)); $yearMonth = date('Y/m');
                $b2FilePath = "stories_media/{$currentUserId}/{$yearMonth}/{$uniqueNamePart}.{$fileExtension}";
                $b2UploadResult = $this->_pm_uploadToB2($fileData, $b2FilePath, $detectedContentType, $fileSize);
                $cloudFileVisibility = 'public';
                $cloudFileData = [
                    'user_id' => $currentUserId, 'storage_provider' => 'backblaze_b2',
                    'provider_file_id' => $b2UploadResult['b2FileId'], 'file_path_in_provider' => $b2FilePath,
                    'container_name' => $this->b2BucketName, 'container_id' => $this->b2BucketId,
                    'original_filename' => $originalFileNameForLog, 'content_type' => $detectedContentType,
                    'size_bytes' => $fileSize, 'content_sha1' => $b2UploadResult['sha1'],
                    'file_category' => ($storyContentType === 'image') ? 'image' : 'video',
                    'visibility' => $cloudFileVisibility, 'uploaded_at_provider' => Medoo::raw('NOW()'),
                    'title' => "Story Media: " . $originalFileNameForLog,
                ];
                $this->db->insert('cloud_files', $cloudFileData);
                $cloudFileRecordId = (int)$this->db->id();
                if (!$cloudFileRecordId) throw new Exception("DB Error: Failed to insert into cloud_files for story media.");
            } elseif (($storyContentType === 'image' || $storyContentType === 'video') && isset($_POST['media_url_override'])) {
                $finalMediaUrlForStoryField = filter_var($_POST['media_url_override'], FILTER_VALIDATE_URL) ? $_POST['media_url_override'] : null;
                if (!$finalMediaUrlForStoryField) {
                    $response['message'] = 'Invalid media URL provided.'; http_response_code(400);
                    throw new Exception("Invalid media_url_override for image/video story.");
                }
            }

            if (!$this->db->pdo->inTransaction() && ($storyContentType === 'text_only' || $storyContentType === 'code_snippet')) {
                $this->db->pdo->beginTransaction();
            }
            $storyDataForDb = [
                'user_id' => $currentUserId, 'cloud_file_id' => $cloudFileRecordId,
                'content_type' => $storyContentType, 'media_url_override' => $finalMediaUrlForStoryField,
                'text_overlay' => $storyTextOverlay, 'code_content' => $storyCodeContent, 'code_language' => $storyCodeLanguage,
                'background_color' => $storyBackgroundColor, 'duration_seconds' => $storyDurationSeconds,
                'expires_at' => $storyExpiresAt, 'visibility' => $storyVisibility,
                'created_at' => Medoo::raw('NOW()'), 'updated_at' => Medoo::raw('NOW()')
            ];
            $this->db->insert('stories', $storyDataForDb);
            $newStoryId = (int)$this->db->id();
            if (!$newStoryId) throw new Exception("DB Error: Failed to insert into stories table.");
            if ($this->db->pdo->inTransaction()) $this->db->pdo->commit();

            $newlyCreatedStoryFull = $this->_fetchFullStoryData($newStoryId);
            if (!$newlyCreatedStoryFull) throw new Exception("Failed to fetch newly created story data (ID: {$newStoryId}).");
            $response = ['success' => true, 'message' => 'Story created successfully!', 'story' => $newlyCreatedStoryFull];
            http_response_code(201);

        } catch (Exception $e) {
            if ($this->db->pdo->inTransaction()) $this->db->pdo->rollBack();
            $detailedError = "createStoryWithMedia Exception (User:{$currentUserId}, File:'{$originalFileNameForLog}'): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            error_log($detailedError);
            if ($response['message'] === 'An unexpected error occurred.') {
                 if (http_response_code() < 400) http_response_code(500);
                 $response['message'] = "Server error creating story. Please try again.";
            }
            if (http_response_code() === 200) http_response_code(500); // Ensure error status if not already set
        }
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // New method to delete a story
    public function deleteStory(int $storyId)
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
        $currentUserId = $_SESSION['user_id'] ?? null;

        if (!$currentUserId) {
            http_response_code(401);
            $response['message'] = 'Authentication required.';
            echo json_encode($response);
            exit;
        }
        $currentUserId = (int) $currentUserId;

        try {
            $this->db->pdo->beginTransaction();

            $story = $this->db->get('stories', [
                "[>]cloud_files(cf)" => ["stories.cloud_file_id" => "id"]
            ],[
                'stories.id',
                'stories.user_id',
                'stories.cloud_file_id',
                'cf.file_path_in_provider',
                'cf.provider_file_id(b2_file_id)' // B2 file ID from cloud_files table
            ], [
                'stories.id' => $storyId
            ]);

            if (!$story) {
                http_response_code(404);
                $response['message'] = 'Story not found.';
                throw new Exception("Story ID {$storyId} not found for deletion attempt by user {$currentUserId}.");
            }

            if ((int)$story['user_id'] !== $currentUserId) {
                http_response_code(403);
                $response['message'] = 'You are not authorized to delete this story.';
                throw new Exception("User {$currentUserId} attempted to delete story ID {$storyId} owned by user {$story['user_id']}.");
            }

            // If there's an associated cloud file, attempt to delete it from B2 and DB
            if (!empty($story['cloud_file_id']) && !empty($story['file_path_in_provider']) && !empty($story['b2_file_id'])) {
                if ($this->_isB2ConfiguredAndReady()) {
                    $b2FileDeleted = $this->_pm_deleteFromB2($story['file_path_in_provider'], $story['b2_file_id']);
                    if (!$b2FileDeleted) {
                        // Log critical error, but proceed with DB deletion to avoid orphaned story records
                        error_log("CRITICAL: Failed to delete file '{$story['file_path_in_provider']}' (B2 ID: {$story['b2_file_id']}) from B2 for story ID {$storyId}. DB records will still be deleted.");
                        // Optionally: throw new Exception("Failed to delete media file from storage provider. Story not deleted.");
                    }
                } else {
                     error_log("CRITICAL: B2 not configured. Cannot delete file '{$story['file_path_in_provider']}' from B2 for story ID {$storyId}.");
                }

                // Delete from cloud_files table
                $deletedCloudFileRows = $this->db->delete('cloud_files', ['id' => $story['cloud_file_id']]);
                if ($deletedCloudFileRows === false) {
                    throw new Exception("DB Error: Failed to delete record from cloud_files for ID {$story['cloud_file_id']} (Story ID: {$storyId}).");
                }
                if ($deletedCloudFileRows === 0) {
                     error_log("Warning: No cloud_file record found or deleted for cloud_file_id {$story['cloud_file_id']} (Story ID: {$storyId}), though story record indicated one.");
                }
            }

            // Delete the story itself
            $deletedStoryRows = $this->db->delete('stories', ['id' => $storyId]);
            if ($deletedStoryRows === false) {
                throw new Exception("DB Error: Failed to delete story record for ID {$storyId}.");
            }
            if ($deletedStoryRows === 0) { // Should not happen if story was found
                 throw new Exception("Logic Error: Story ID {$storyId} was found but then not deleted (0 rows affected).");
            }

            $this->db->pdo->commit();
            $response = ['success' => true, 'message' => 'Story deleted successfully.'];
            http_response_code(200);

        } catch (Exception $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            error_log("DeleteStory Exception (StoryID: {$storyId}, User: {$currentUserId}): {$e->getMessage()}");
            if ($response['message'] === 'An unexpected error occurred.') {
                if (http_response_code() < 400) http_response_code(500);
                $response['message'] = 'Server error deleting story. Please try again.';
            }
            if (http_response_code() === 200 || http_response_code() === 201 ) http_response_code(500);
        }

        echo json_encode($response);
        exit;
    }


    private function _fetchFullStoryData(int $storyId): ?array {
        if (!$this->db) { error_log("_fetchFullStoryData: DB connection unavailable for story ID {$storyId}."); return null; }
        $storyData = $this->db->get('stories', [
            "[>]users(u)" => ["stories.user_id" => "id"],
            "[>]cloud_files(cf)" => ["stories.cloud_file_id" => "id"]
        ], [
            'stories.id', 'stories.user_id', 'stories.cloud_file_id', 'stories.content_type',
            'stories.media_url_override', 'stories.text_overlay', 'stories.code_content', 'stories.code_language',
            'stories.link_url', 'stories.link_preview_data', 'stories.background_color', 'stories.font_family',
            'stories.theme_category', 'stories.duration_seconds', 'stories.expires_at', 'stories.visibility',
            'stories.created_at', 'stories.updated_at',
            'u.fullname AS full_name', 'u.username', 'u.profile_picture(user_profile_picture_path)',
            'cf.file_path_in_provider(media_file_path_in_provider)', 'cf.content_type(media_actual_mime_type)'
        ], ['stories.id' => $storyId]);

        if ($storyData) {
            $storyData['id'] = (int)$storyData['id']; $storyData['user_id'] = (int)$storyData['user_id'];
            // Construct final_media_url for story content
            $storyData['final_media_url'] = null;
            if (!empty($storyData['cloud_file_id']) && !empty($storyData['media_file_path_in_provider'])) {
                 if (!empty($this->fileCdnBaseUrl)) {
                    $storyData['final_media_url'] = $this->fileCdnBaseUrl . ltrim($storyData['media_file_path_in_provider'], '/');
                } else {
                    error_log("_fetchFullStoryData - fileCdnBaseUrl not configured for story media. Story ID: " . $storyId);
                }
            } elseif (!empty($storyData['media_url_override'])) {
                $storyData['final_media_url'] = $storyData['media_url_override'];
            }

            // Construct user_avatar URL for story author
            $storyData['user_avatar'] = null;
            $profilePicturePathFromDb = $storyData['user_profile_picture_path'];
            if (!empty($profilePicturePathFromDb)) {
                if (strpos(strtolower($profilePicturePathFromDb), 'http://') === 0 || strpos(strtolower($profilePicturePathFromDb), 'https://') === 0) {
                    $storyData['user_avatar'] = $profilePicturePathFromDb;
                } elseif (!empty($this->fileCdnBaseUrl)) {
                    $storyData['user_avatar'] = $this->fileCdnBaseUrl . ltrim($profilePicturePathFromDb, '/');
                } else {
                    error_log("_fetchFullStoryData - fileCdnBaseUrl not configured for user_avatar. User ID: " . $storyData['user_id']);
                }
            }
            unset($storyData['user_profile_picture_path'], $storyData['media_file_path_in_provider']);
        } else { error_log("_fetchFullStoryData: Story not found for ID {$storyId}."); }
        return $storyData;
    }

    // --- Helper methods (Identical or adapted from UploadController) ---
    private function _isB2ConfiguredAndReady(): bool {
        return !empty($this->b2AccountId) && !empty($this->b2AppKey) &&
               !empty($this->b2BucketId) && !empty($this->b2BucketName) &&
               $this->db && !empty($this->fileCdnBaseUrl); // Check non-empty after processing
    }
    private function _validateStoryTableVisibility(string &$visibility): void {
        $validVisibilities = ['public', 'friends_only', 'private'];
        if (!in_array($visibility, $validVisibilities, true)) $visibility = 'public';
    }
    private function _validateStoryContentType(string &$contentType): void {
        $validTypes = ['image', 'video', 'text_only', 'code_snippet', 'link_preview'];
        if (!in_array($contentType, $validTypes, true)) $contentType = 'text_only';
    }
    private function _pm_getUploadErrorMessage(int $errorCode): string {
        $errors = [ 1 => "File larger than upload_max_filesize.", 2 => "File larger than MAX_FILE_SIZE.", 3 => "File partially uploaded.", 4 => "No file uploaded.", 6 => "Missing temp folder.", 7 => "Failed to write file.", 8 => "PHP extension stopped upload."];
        return $errors[$errorCode] ?? "Unknown upload error (Code: {$errorCode})";
    }
    private function _pm_getFileMimeType(string $tempFilePath, string $fallbackType): string {
        if (!is_readable($tempFilePath)) return $fallbackType ?: 'application/octet-stream';
        $finfo = finfo_open(FILEINFO_MIME_TYPE); if (!$finfo) return $fallbackType ?: 'application/octet-stream';
        $contentType = finfo_file($finfo, $tempFilePath); finfo_close($finfo);
        return $contentType ?: $fallbackType ?: 'application/octet-stream';
    }
    private function _isValidStoryMimeType(string $contentType, string $storyTypeGoal): bool {
        if ($storyTypeGoal === 'image') return in_array($contentType, self::STORY_ALLOWED_IMAGE_TYPES, true);
        if ($storyTypeGoal === 'video') return in_array($contentType, self::STORY_ALLOWED_VIDEO_TYPES, true);
        return false;
    }
    private function _pm_readFileData(string $tempFilePath): ?string {
        if (!is_readable($tempFilePath)) { error_log("Helper: Cannot read temp file: {$tempFilePath}"); return null; }
        $fileData = file_get_contents($tempFilePath);
        if ($fileData === false || strlen($fileData) === 0) { error_log("Helper: Failed to read file data or file is empty: {$tempFilePath}"); return null; }
        return $fileData;
    }
    private function _pm_uploadToB2(string $fileData, string $b2ObjectName, string $contentType, int $fileSize): array {
        $sha1 = sha1($fileData);
        $authHeader = "Authorization: Basic " . base64_encode($this->b2AccountId . ":" . $this->b2AppKey);
        $authOpts = ['http' => ['method' => 'GET', 'header' => $authHeader . "\r\n", 'timeout' => 30, 'ignore_errors' => true]];
        $authCtx = stream_context_create($authOpts);
        $authJson = @file_get_contents('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', false, $authCtx);
        if ($authJson === false) throw new Exception('B2 Auth: Network error.');
        $authRsp = json_decode($authJson, true);
        if (json_last_error() || !is_array($authRsp) || !isset($authRsp['apiUrl'], $authRsp['authorizationToken'])) {
             error_log("B2 Auth Fail. Rsp: " . substr($authJson, 0, 500)); throw new Exception('B2 Auth: Invalid rsp.');}
        $apiUrl = $authRsp['apiUrl']; $accAuthToken = $authRsp['authorizationToken'];
        $getUpUrlPayload = json_encode(['bucketId' => $this->b2BucketId]);
        $getUpUrlOpts = ['http' => ['method' => 'POST', 'header' => "Authorization: {$accAuthToken}\r\nContent-Type: application/json\r\nContent-Length: " . strlen($getUpUrlPayload) . "\r\n", 'content' => $getUpUrlPayload, 'timeout' => 30, 'ignore_errors' => true]];
        $getUpUrlCtx = stream_context_create($getUpUrlOpts);
        $getUpUrlJson = @file_get_contents($apiUrl . '/b2api/v2/b2_get_upload_url', false, $getUpUrlCtx);
        if ($getUpUrlJson === false) throw new Exception('B2 GetUpUrl: Network error.');
        $getUpUrlRsp = json_decode($getUpUrlJson, true);
        if (json_last_error() || !is_array($getUpUrlRsp) || !isset($getUpUrlRsp['uploadUrl'], $getUpUrlRsp['authorizationToken'])) {
             error_log("B2 GetUpUrl Fail. Rsp: " . substr($getUpUrlJson, 0, 500)); 
             throw new Exception('B2 GetUpUrl: Invalid rsp.');
        }
        $fileUpUrl = $getUpUrlRsp['uploadUrl']; $fileUpAuthToken = $getUpUrlRsp['authorizationToken'];
            $curlHdrs = ["Authorization: {$fileUpAuthToken}", "X-Bz-File-Name: " . rawurlencode($b2ObjectName), "Content-Type: {$contentType}", "X-Bz-Content-Sha1: {$sha1}", "Content-Length: {$fileSize}"];
            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL => $fileUpUrl, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fileData, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, CURLOPT_HTTPHEADER => $curlHdrs, CURLOPT_FAILONERROR => false]);
            $upB2Json = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlErrNo = curl_errno($ch); $curlErrMsg = curl_error($ch); curl_close($ch);
        if ($curlErrNo) throw new Exception("B2 Up cURL Error ($curlErrNo): {$curlErrMsg}");
            $upB2Rsp = json_decode($upB2Json, true);
        if ($httpCode !== 200) {
            $b2Msg = "B2 Up HTTP Error ({$httpCode})."; if ($upB2Rsp && isset($upB2Rsp['message'])) $b2Msg .= " B2 Msg: " . $upB2Rsp['message'];
            error_log("B2 Up Fail HTTP {$httpCode}. Rsp: " . substr($upB2Json ?: '', 0, 300)); throw new Exception($b2Msg);
        }
        if (json_last_error() || !is_array($upB2Rsp) || !isset($upB2Rsp['fileId'])) {
             error_log("B2 Up Invalid JSON. HTTP {$httpCode}. Rsp: " . substr($upB2Json ?: '',0,200)); throw new Exception("B2 Up success, but API rsp invalid.");}
        return ['b2FileId' => $upB2Rsp['fileId'], 'sha1' => $sha1];
    }

    // New helper method to delete a file from Backblaze B2
    private function _pm_deleteFromB2(string $b2FileName, string $b2FileId): bool
    {
        if (empty($this->b2AccountId) || empty($this->b2AppKey)) {
            error_log("B2 Delete Error: Account ID or App Key missing for deleting '{$b2FileName}'.");
            return false; // Cannot proceed without credentials
        }

        try {
            // 1. Authorize Account
            $authHeader = "Authorization: Basic " . base64_encode($this->b2AccountId . ":" . $this->b2AppKey);
            $authOpts = ['http' => ['method' => 'GET', 'header' => $authHeader . "\r\n", 'timeout' => 30, 'ignore_errors' => true]];
            $authCtx = stream_context_create($authOpts);
            $authJson = @file_get_contents('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', false, $authCtx);

            if ($authJson === false) {
                throw new Exception('B2 Delete Auth: Network error during b2_authorize_account.');
            }
            $authRsp = json_decode($authJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($authRsp) || !isset($authRsp['apiUrl'], $authRsp['authorizationToken'])) {
                error_log("B2 Delete Auth Fail. Response: " . substr($authJson, 0, 500));
                throw new Exception('B2 Delete Auth: Invalid response from b2_authorize_account.');
            }
            $apiUrl = $authRsp['apiUrl'];
            $accountAuthToken = $authRsp['authorizationToken'];

            // 2. Call b2_delete_file_version API
            $deletePayload = json_encode(['fileName' => $b2FileName, 'fileId' => $b2FileId]);
            $deleteOpts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: {$accountAuthToken}\r\n" .
                                "Content-Type: application/json\r\n" .
                                "Content-Length: " . strlen($deletePayload) . "\r\n",
                    'content' => $deletePayload,
                    'timeout' => 30,
                    'ignore_errors' => true // Important to capture B2 error responses in the body
                ]
            ];
            $deleteCtx = stream_context_create($deleteOpts);
            $deleteUrl = $apiUrl . '/b2api/v2/b2_delete_file_version';
            $deleteJson = @file_get_contents($deleteUrl, false, $deleteCtx);
            
            $httpStatusCode = 0;
            if (isset($http_response_header[0])) { // $http_response_header is a magic PHP variable
                if (preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match)) {
                    $httpStatusCode = (int)$match[1];
                }
            }

            if ($deleteJson === false && $httpStatusCode === 0) { // No response and no status code likely means network issue
                throw new Exception("B2 Delete: Network error or no response calling b2_delete_file_version for file '{$b2FileName}'.");
            }
            
            $deleteRsp = json_decode($deleteJson, true);

            // B2 returns 200 OK even if file is not found, and includes fileName/fileId in response.
            if ($httpStatusCode === 200 && $deleteRsp !== null && isset($deleteRsp['fileName'], $deleteRsp['fileId'])) {
                 if (isset($deleteRsp['code']) && ($deleteRsp['code'] === 'file_not_present' || $deleteRsp['code'] === 'no_such_file')) {
                    error_log("B2 Delete Info: File '{$b2FileName}' (B2 ID: {$b2FileId}) was not found on B2 (already deleted or never existed). Treating as success.");
                } else {
                    error_log("B2 Delete Success: File '{$b2FileName}' (B2 ID: {$b2FileId}) deleted from B2.");
                }
                return true;
            } else {
                $b2ErrorMessage = $deleteRsp['message'] ?? 'Unknown B2 error or malformed response';
                $b2ErrorCode = $deleteRsp['code'] ?? 'unknown_code';
                error_log("B2 Delete Fail: File '{$b2FileName}' (B2 ID: {$b2FileId}). HTTP Status: {$httpStatusCode}. B2 Code: {$b2ErrorCode}. B2 Msg: {$b2ErrorMessage}. Raw Rsp: " . substr($deleteJson ?: 'No JSON response', 0, 500));
                throw new Exception("B2 API error deleting file '{$b2FileName}': {$b2ErrorMessage} (Code: {$b2ErrorCode}, HTTP Status: {$httpStatusCode})");
            }

        } catch (Exception $e) {
            error_log("B2 Delete Exception for '{$b2FileName}' (B2 ID: {$b2FileId}): " . $e->getMessage());
            return false; // Indicate failure, decision to proceed with DB delete is up to caller
        }
    }
}