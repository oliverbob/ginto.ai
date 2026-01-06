<?php

namespace App\Controllers;

use Core\Controller;
use Exception;
use Ginto\Core\Database;

class UploadController extends Controller
{

    private string $b2AccountId;
    private string $b2AppKey;
    private string $b2BucketId;
    private string $b2BucketName;
    private string $fileCdnBaseUrl;

    // YOUR EXISTING ALLOWED_EXTENSIONS constant - UNTOUCHED
    private const ALLOWED_EXTENSIONS = [
        'image'    => ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp', '.tiff', '.tif', '.ico', '.svg', '.heif', '.heic', '.avif'],
        'video'    => ['.mp4', '.webm', '.mkv', '.mov', '.avi', '.flv', '.wmv', '.m4v', '.3gp', '.ogv'],
        'audio'    => ['.mp3', '.wav', '.ogg', '.aac', '.m4a', '.flac', '.opus', '.wma', '.amr', '.aiff'],
        'document' => ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.odt', '.ods', '.odp', '.txt', '.rtf', '.csv', '.md'],
        'code'     => ['.html', '.css', '.js', '.json', '.wasm', '.xml', '.map'],
        'font'     => ['.woff', '.woff2', '.ttf', '.otf', '.eot'],
        'archive'  => ['.zip', '.rar', '.7z', '.tar', '.gz', '.tgz']
    ];

    // Constants specific to the createPostWithMedia feature
    private const PM_MAX_FILE_SIZE_MB = 50; 
    private const PM_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const PM_ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

    // Constants specific to Cover Photo Upload
    private const CP_MAX_FILE_SIZE_MB = 10; // Max 10MB for cover photos
    private const CP_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    private const CP_B2_PATH_PREFIX = 'users_media/covers'; // B2 Path: users_media/covers/{user_id}/{year_month}/filename.ext

    // Constants specific to Profile Picture Upload
    private const PP_MAX_FILE_SIZE_MB = 8; // Max 8MB for profile pictures
    private const PP_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    private const PP_B2_PATH_PREFIX = 'users_media/profile_pictures'; // B2 Path: users_media/profile_pictures/{user_id}/{year_month}/filename.ext
    private const PP_THUMB_B2_PATH_PREFIX = 'users_media/profile_pictures_thumbs'; // Optional for thumbnails


    public function __construct($db = null)
    {
        parent::__construct();
        $this->db = $db ?? Database::getInstance();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $this->b2AccountId = $_ENV['B2_ACCOUNT_ID'] ?? '';
        $this->b2AppKey    = $_ENV['B2_APP_KEY'] ?? '';
        $this->b2BucketId  = $_ENV['B2_BUCKET_ID'] ?? '';
        $this->b2BucketName = $_ENV['B2_BUCKET_NAME'] ?? '';
        $this->fileCdnBaseUrl = isset($_ENV['FILE_CDN_BASE_URL']) ? rtrim($_ENV['FILE_CDN_BASE_URL'], '/') . '/' : '';

        if (empty($this->b2AccountId) || empty($this->b2AppKey) || empty($this->b2BucketId) || empty($this->b2BucketName)) {
            error_log('CRITICAL ERROR (UploadController): Backblaze B2 configuration is incomplete in .env file.');
        }
        if (empty($this->fileCdnBaseUrl) || $this->fileCdnBaseUrl === '/') {
            error_log('CRITICAL ERROR (UploadController): FILE_CDN_BASE_URL is missing or invalid in .env file.');
        }
    }

    public function upload()
    {
        $data = [
            'pageTitle' => 'Cloud File Manager',
            'fileCdnBaseUrl' => $this->fileCdnBaseUrl,
            'uploadedFiles' => [],
        ];
        $currentUserId = $_SESSION['user_id'] ?? null;

        $data['successFlashMessage'] = $_SESSION['upload_success_flash'] ?? null;
        $data['errorFlashMessage'] = $_SESSION['upload_error_flash'] ?? null;
        $data['infoFlashMessage'] = $_SESSION['upload_info_flash'] ?? null;
        unset($_SESSION['upload_success_flash'], $_SESSION['upload_error_flash'], $_SESSION['upload_info_flash']);

        $isB2Configured = !empty($this->b2AccountId) && !empty($this->b2AppKey) && !empty($this->b2BucketId) && !empty($this->b2BucketName);
        $isDbAvailable = $this->db !== null;
        $isCdnConfigured = !empty($this->fileCdnBaseUrl) && $this->fileCdnBaseUrl !== '/';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['file']) && !isset($_POST['update_metadata'])) { // File Upload
                if (!$currentUserId) { $_SESSION['upload_error_flash'] = 'You must be logged in to upload files.'; }
                elseif (!$isB2Configured || !$isCdnConfigured) { $_SESSION['upload_error_flash'] = 'File upload service is not properly configured.'; }
                elseif (!$isDbAvailable) { $_SESSION['upload_error_flash'] = 'Database service not available for upload processing.'; }
                else {
                    try { 
                        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                            throw new Exception($this->_pm_getUploadErrorMessage($_FILES['file']['error'])); 
                        }
                        $originalFileName = basename($_FILES['file']['name']);
                        $tempFilePath = $_FILES['file']['tmp_name'];
                        $fileData = $this->_pm_readFileData($tempFilePath); 
                        if ($fileData === null) throw new Exception('Could not read uploaded file data or file is empty.');
                        $fileSize = strlen($fileData);

                        $contentType = $this->_pm_getFileMimeType($tempFilePath, $_FILES['file']['type']); 
                        $fileCategory = $this->getFileCategoryByExtension($originalFileName); 

                        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                        $uniqueNamePart = bin2hex(random_bytes(16));
                        $newServerFileName = $uniqueNamePart . '.' . $fileExtension; 

                        $sha1 = sha1($fileData);
                        $authHttpHeader = "Authorization: Basic " . base64_encode($this->b2AccountId . ":" . $this->b2AppKey);
                        $authHttpContextOptions = ['http' => ['method' => 'GET', 'header' => $authHttpHeader . "\r\n", 'timeout' => 30, 'ignore_errors' => true]];
                        $authHttpContext = stream_context_create($authHttpContextOptions);
                        $authResponseJson = @file_get_contents('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', false, $authHttpContext);
                        if ($authResponseJson === false) throw new Exception('B2 Auth: Network error.');
                        $authResponse = json_decode($authResponseJson, true);
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($authResponse) || !isset($authResponse['apiUrl'], $authResponse['authorizationToken'])) { error_log("B2 Auth Failure. Rsp: " . substr($authResponseJson, 0, 500)); throw new Exception('B2 Auth: Invalid rsp.');}
                        $b2ApiUrl = $authResponse['apiUrl']; $b2AccountAuthToken = $authResponse['authorizationToken'];
                        $getUploadUrlPayload = json_encode(['bucketId' => $this->b2BucketId]);
                        $getUploadUrlHttpOptions = ['http' => ['method' => 'POST', 'header' => "Authorization: " . $b2AccountAuthToken . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($getUploadUrlPayload) . "\r\n", 'content' => $getUploadUrlPayload, 'timeout' => 30, 'ignore_errors' => true]];
                        $getUploadUrlContext = stream_context_create($getUploadUrlHttpOptions);
                        $getUploadUrlResponseJson = @file_get_contents($b2ApiUrl . '/b2api/v2/b2_get_upload_url', false, $getUploadUrlContext);
                        if ($getUploadUrlResponseJson === false) throw new Exception('B2 Get Upload URL: Network error.');
                        $getUploadUrlResponse = json_decode($getUploadUrlResponseJson, true);
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($getUploadUrlResponse) || !isset($getUploadUrlResponse['uploadUrl'], $getUploadUrlResponse['authorizationToken'])) { error_log("B2 GetUpURL Fail. Rsp: " . substr($getUploadUrlResponseJson, 0, 500)); throw new Exception('B2 GetUpURL: Invalid rsp.');}
                        $b2FileUploadUrl = $getUploadUrlResponse['uploadUrl']; $b2FileUploadAuthToken = $getUploadUrlResponse['authorizationToken'];
                        $curlHeaders = ["Authorization: " . $b2FileUploadAuthToken, "X-Bz-File-Name: " . rawurlencode($newServerFileName), "Content-Type: " . $contentType, "X-Bz-Content-Sha1: " . $sha1, "Content-Length: " . $fileSize];
                        $ch = curl_init();
                        curl_setopt_array($ch, [ CURLOPT_URL => $b2FileUploadUrl, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fileData, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, CURLOPT_HTTPHEADER => $curlHeaders ]);
                        $uploadB2ResponseJson = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
                        if ($uploadB2ResponseJson === false || !empty($curlError)) throw new Exception("B2 Upload cURL Error: " . $curlError);
                        $uploadB2Response = json_decode($uploadB2ResponseJson, true);
                        if ($httpCode !== 200 || json_last_error() !== JSON_ERROR_NONE || !is_array($uploadB2Response) || !isset($uploadB2Response['fileId'])) { error_log("B2 Upload Fail: HTTP {$httpCode}. Rsp: " . substr($uploadB2ResponseJson, 0, 500)); throw new Exception("B2 Upload failed (HTTP {$httpCode}).");}
                        $b2UploadedFileId = $uploadB2Response['fileId'];
                        $dbData = [
                            'user_id' => $currentUserId, 'storage_provider' => 'backblaze_b2', 'provider_file_id' => $b2UploadedFileId,
                            'file_path_in_provider' => $newServerFileName, 
                            'container_name' => $this->b2BucketName, 'container_id' => $this->b2BucketId,
                            'original_filename' => $originalFileName, 'content_type' => $contentType, 'size_bytes' => $fileSize,
                            'content_sha1' => $sha1, 'file_category' => $fileCategory,
                            'visibility' => 'private', 'uploaded_at_provider' => Medoo::raw('NOW()'),
                            'title' => $originalFileName,
                        ];
                        $this->db->insert('cloud_files', $dbData); $lastInsertId = $this->db->id();
                        if (!$lastInsertId) { $dbError = $this->db->error(); error_log("Medoo Insert Err (upload page): " . ($dbError ? implode(" ", $dbError) : "Unknown")); throw new Exception("Failed to save to DB.");}
                        $_SESSION['upload_success_flash'] = "File '{$originalFileName}' uploaded as '{$newServerFileName}'! DB ID: {$lastInsertId}";

                    } catch (Exception $e) {
                        error_log("Upload Page - Process Err (User: {$currentUserId}): " . $e->getMessage());
                        $_SESSION['upload_error_flash'] = "Upload failed: " . $e->getMessage();
                    }
                }
                header('Location: /upload'); exit;
            }

            if (isset($_POST['update_metadata'])) {
                if (!$currentUserId) { $_SESSION['upload_error_flash'] = 'Authentication required for metadata update.'; }
                elseif (!$isDbAvailable) { $_SESSION['upload_error_flash'] = 'Database service unavailable for metadata update.'; }
                else {
                    try {
                        $fileId = $_POST['file_id'] ?? null; if (!$fileId) throw new Exception("File ID missing.");
                        $title = $_POST['title'] ?? ''; $description = $_POST['description'] ?? ''; $tags = $_POST['tags'] ?? ''; $visibility = $_POST['visibility'] ?? 'private';
                        if(!in_array($visibility, ['public', 'unlisted', 'private'])) $visibility = 'private';
                        $tagsJson = !empty($tags) ? json_encode(array_map('trim', explode(',', $tags))) : null;
                        $updateData = ["title" => $title, "description" => $description, "tags" => $tagsJson, "visibility" => $visibility, "updated_at" => Medoo::raw('NOW()')];
                        $updateResult = $this->db->update('cloud_files', $updateData, ["id" => $fileId, "user_id" => $currentUserId]);
                        if ($updateResult === false) { $dbError = $this->db->error(); error_log("Medoo MetaUpd Err: " . ($dbError ? implode(" ", $dbError) : "Unknown")); throw new Exception("DB error during metadata update."); }
                        elseif ($updateResult->rowCount() === 0) { $_SESSION['upload_info_flash'] = "No changes detected or file not found for your account."; }
                        else { $_SESSION['upload_success_flash'] = "File metadata updated!"; }
                    } catch (Exception $e) { error_log("MetaUpd Err (User: {$currentUserId}): " . $e->getMessage()); $_SESSION['upload_error_flash'] = "Meta update failed: " . $e->getMessage(); }
                }
                header('Location: /upload'); exit;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
            $fileIdToDelete = (int)$_GET['delete_id'];
            if (!$currentUserId) { $_SESSION['upload_error_flash'] = 'Authentication required to delete files.'; }
            elseif (!$isDbAvailable) { $_SESSION['upload_error_flash'] = 'Database service not available for deletion.'; }
            elseif (!$isB2Configured) { $_SESSION['upload_error_flash'] = 'Cloud storage service not configured for deletion.';}
            else {
                try {
                    $fileInfo = $this->db->get('cloud_files', ['provider_file_id', 'file_path_in_provider'], ["AND" => ["id" => $fileIdToDelete, "user_id" => $currentUserId]]);
                    if (!$fileInfo) throw new Exception("File not found for your account, or permission denied.");
                    // TODO: Implement B2 actual file deletion 
                    $deleteResult = $this->db->delete('cloud_files', ["AND" => ["id" => $fileIdToDelete, "user_id" => $currentUserId]]);
                    if ($deleteResult === false) { $dbError = $this->db->error(); error_log("Medoo Del Err: " . ($dbError ? implode(" ", $dbError) : "Unknown")); throw new Exception("DB error during file record deletion.");}
                    elseif ($deleteResult->rowCount() === 0) { throw new Exception("File record not found in database (already deleted or permission issue).");}
                    else { $_SESSION['upload_success_flash'] = "File record deleted successfully! (Actual cloud file deletion TODO)";}
                } catch (Exception $e) { error_log("Del Err (User: {$currentUserId}): " . $e->getMessage()); $_SESSION['upload_error_flash'] = "Delete failed: " . $e->getMessage();}
            }
            header('Location: /upload'); exit;
        }

        if ($isDbAvailable) {
            try {
                $conditions = ["storage_provider" => "backblaze_b2"];
                if ($currentUserId) {
                     $conditions["user_id"] = $currentUserId; 
                } else {
                    $conditions["visibility"] = "public"; 
                }
                $data['uploadedFiles'] = $this->db->select('cloud_files', '*', array_merge($conditions, ["ORDER" => ["created_at" => "DESC"], "LIMIT" => 50]));
            } catch (Exception $e) { error_log("Err fetching files for /upload page: " . $e->getMessage()); $data['errorFlashMessage'] = ($data['errorFlashMessage'] ?? '') . ($data['errorFlashMessage'] ? "<br>" : "") . 'Could not load the list of files.'; }
        } else { $data['errorFlashMessage'] = ($data['errorFlashMessage'] ?? '') . ($data['errorFlashMessage'] ? "<br>" : "") . 'Database service is currently unavailable. Cannot list files.'; }
        if (!$isB2Configured || !$isCdnConfigured) {
            $configError = "CRITICAL: File services are not fully configured. ";
            if(!$isB2Configured) $configError .= "B2 storage details missing. "; if(!$isCdnConfigured) $configError .= "CDN Base URL missing. ";
            $data['errorFlashMessage'] = ($data['errorFlashMessage'] ?? '') . ($data['errorFlashMessage'] ? "<br>" : "") . $configError;
        }
        $this->view('upload', $data);
    }


    private function getFileCategoryByExtension(string $filename): string
    {
        // ... (YOUR EXISTING getFileCategoryByExtension METHOD - UNCHANGED)
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


    // ---------------------------------------------------------------------------
    // ---- METHOD: createPostWithMedia() and its DEDICATED helpers ----
    // ---------------------------------------------------------------------------

    public function createPostWithMedia()
    {
        // ... (YOUR EXISTING createPostWithMedia METHOD - UNCHANGED)
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred.', 'post' => null];
        $currentUserId = $_SESSION['user_id'] ?? null;
        $originalFileNameForLog = 'N/A'; 

        try {
            if (!$currentUserId) {
                $response['message'] = 'Authentication required.'; http_response_code(401); throw new Exception("User not authenticated for createPostWithMedia.");
            }
            $currentUserId = (int) $currentUserId;

            if (!$this->_pm_isB2ConfiguredAndReady()) {
                $response['message'] = 'Server media configuration error.'; http_response_code(500);
                throw new Exception("B2/CDN not configured for createPostWithMedia. User: {$currentUserId}");
            }

            $postContent = trim($_POST['post_content'] ?? '');
            $postVisibility = $_POST['visibility'] ?? 'public';
            $this->_pm_validatePostTableVisibility($postVisibility); 

            if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrorCode = $_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                $response['message'] = $this->_pm_getUploadErrorMessage($uploadErrorCode);
                http_response_code(400); throw new Exception("File upload error (code: {$uploadErrorCode}) for createPostWithMedia.");
            }

            $file = $_FILES['media_file'];
            $tempFilePath = $file['tmp_name'];
            $originalFileNameForLog = basename($file['name']); 
            $fileSize = (int) $file['size'];

            if ($fileSize > (self::PM_MAX_FILE_SIZE_MB * 1024 * 1024)) {
                $response['message'] = 'File too large. Max: ' . self::PM_MAX_FILE_SIZE_MB . 'MB.'; http_response_code(400);
                throw new Exception("File too large for post ({$fileSize} bytes). Max: " . self::PM_MAX_FILE_SIZE_MB . "MB.");
            }

            $contentType = $this->_pm_getFileMimeType($tempFilePath, $file['type']);
            if (!$this->_pm_isValidPostMimeType($contentType)) {
                $response['message'] = 'Invalid file type for post.'; http_response_code(400);
                throw new Exception("Invalid MIME type for post: {$contentType}.");
            }
            
            $fileCategory = $this->getFileCategoryByExtension($originalFileNameForLog); 
            if ($fileCategory !== 'image' && $fileCategory !== 'video') {
                 $response['message'] = "Invalid file category. Only images/videos for posts."; http_response_code(400);
                 throw new Exception("Invalid file category for post media: {$fileCategory}.");
            }

            $fileData = $this->_pm_readFileData($tempFilePath);
            if ($fileData === null) {
                $response['message'] = 'Could not read file data.'; http_response_code(500);
                throw new Exception("Failed to read temp file data: {$tempFilePath}");
            }

            $this->db->pdo->beginTransaction();

            $fileExtension = strtolower(pathinfo($originalFileNameForLog, PATHINFO_EXTENSION));
            $uniqueNamePart = bin2hex(random_bytes(16));
            $yearMonth = date('Y/m');
            $b2FilePath = "posts_media/{$currentUserId}/{$yearMonth}/{$uniqueNamePart}.{$fileExtension}";

            $b2UploadResult = $this->_pm_uploadToB2($fileData, $b2FilePath, $contentType, $fileSize);

            $cloudFileVisibility = 'public'; 
            $cloudFileData = [
                'user_id' => $currentUserId, 'storage_provider' => 'backblaze_b2',
                'provider_file_id' => $b2UploadResult['b2FileId'],
                'file_path_in_provider' => $b2FilePath, 
                'container_name' => $this->b2BucketName, 'container_id' => $this->b2BucketId,
                'original_filename' => $originalFileNameForLog, 'content_type' => $contentType,
                'size_bytes' => $fileSize, 'content_sha1' => $b2UploadResult['sha1'],
                'file_category' => $fileCategory, 'visibility' => $cloudFileVisibility,
                'uploaded_at_provider' => Medoo::raw('NOW()'),
                'title' => $originalFileNameForLog, 
            ];
            $this->db->insert('cloud_files', $cloudFileData);
            $cloudFileRecordId = (int)$this->db->id();
            if (!$cloudFileRecordId) {
                throw new Exception("DB Error: Failed to insert into cloud_files for post media.");
            }

            $mediaUrlForPost = $this->fileCdnBaseUrl . rawurlencode($b2FilePath);
            $postDataForDb = [
                'user_id' => $currentUserId, 'content' => $postContent, 'image' => $mediaUrlForPost,
                'cloud_file_id' => $cloudFileRecordId, 'visibility' => $postVisibility,
                'post_type' => 'media', 'created_at' => Medoo::raw('NOW()'), 'updated_at' => Medoo::raw('NOW()')
            ];
            $this->db->insert('posts', $postDataForDb);
            $newPostId = (int)$this->db->id();
            if (!$newPostId) {
                throw new Exception("DB Error: Failed to insert into posts table.");
            }

            $this->db->pdo->commit();

            $newlyCreatedPostFull = $this->_pm_fetchFullPostData($newPostId);
            if (!$newlyCreatedPostFull) {
                 throw new Exception("Failed to fetch newly created post data (ID: {$newPostId}).");
            }

            $response = ['success' => true, 'message' => 'Post created successfully!', 'post' => $newlyCreatedPostFull];
            http_response_code(201);

        } catch (Exception $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            $detailedError = "createPostWithMedia Exception (User:{$currentUserId}, File:'{$originalFileNameForLog}'): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            error_log($detailedError);

            if ($response['message'] === 'An unexpected error occurred.') { 
                 if (http_response_code() < 400 ) http_response_code(500); 
                 $response['message'] = "Server error creating post. Please try again.";
            }
            if (http_response_code() === 200) { 
                 http_response_code(500);
            }

        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }


    // ---------------------------------------------------------------------------
    // ---- NEW METHOD: cover() for managing profile cover photo ----
    // ---------------------------------------------------------------------------
    public function cover()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleGetCoverPhoto();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleUploadCoverPhoto();
        } else {
            header('Content-Type: application/json');
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
            exit;
        }
    }

    private function handleGetCoverPhoto()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'cover_photo_url' => null];
        $currentUserId = $_SESSION['user_id'] ?? null;

        if (!$currentUserId) {
            $response['message'] = 'Authentication required.';
            http_response_code(401);
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        try {
            if (!$this->db) {
                $response['message'] = 'Database service unavailable.';
                http_response_code(503); // Service Unavailable
                throw new Exception("Database service unavailable for handleGetCoverPhoto. User: {$currentUserId}");
            }
            $userData = $this->db->get('users', ['cover_photo'], ['id' => (int)$currentUserId]);
            
            if ($userData) {
                $response['success'] = true;
                $response['cover_photo_url'] = $userData['cover_photo']; // This could be null
                http_response_code(200);
            } else {
                $response['message'] = 'User not found.'; // Should not happen for a valid session
                http_response_code(404);
            }
        } catch (Exception $e) {
            error_log("handleGetCoverPhoto Error (User:{$currentUserId}): " . $e->getMessage());
            if (http_response_code() < 400) http_response_code(500); // Ensure error status if not already set
            if ($response['message'] === null) $response['message'] = 'Error retrieving cover photo information.';
        }
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function handleUploadCoverPhoto()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
        $currentUserId = $_SESSION['user_id'] ?? null;
        $originalFileNameForLog = 'N/A';

        try {
            if (!$currentUserId) {
                $response['message'] = 'Authentication required.'; http_response_code(401);
                throw new Exception("User not authenticated for handleUploadCoverPhoto.");
            }
            $currentUserId = (int) $currentUserId;

            if (!$this->_pm_isB2ConfiguredAndReady()) { // Reusing this helper as it checks all necessary components
                $response['message'] = 'Server media configuration error.'; http_response_code(500);
                throw new Exception("B2/CDN not configured for handleUploadCoverPhoto. User: {$currentUserId}");
            }
            
            $fileInputName = 'cover_photo_file'; // Expected file input name from frontend
            if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
                $uploadErrorCode = $_FILES[$fileInputName]['error'] ?? UPLOAD_ERR_NO_FILE;
                $response['message'] = $this->_pm_getUploadErrorMessage($uploadErrorCode); // Reusing helper
                http_response_code(400); throw new Exception("File upload error (code: {$uploadErrorCode}) for cover photo.");
            }

            $file = $_FILES[$fileInputName];
            $tempFilePath = $file['tmp_name'];
            $originalFileNameForLog = basename($file['name']);
            $fileSize = (int) $file['size'];

            if ($fileSize > (self::CP_MAX_FILE_SIZE_MB * 1024 * 1024)) {
                $response['message'] = 'File too large. Max: ' . self::CP_MAX_FILE_SIZE_MB . 'MB.'; http_response_code(400);
                throw new Exception("Cover photo file too large ({$fileSize} bytes). Max: " . self::CP_MAX_FILE_SIZE_MB . "MB.");
            }

            $contentType = $this->_pm_getFileMimeType($tempFilePath, $file['type']); // Reusing helper
            if (!$this->_cp_isValidCoverMimeType($contentType)) {
                $response['message'] = 'Invalid file type for cover photo. Allowed: JPG, PNG, GIF, WebP, AVIF.'; http_response_code(400);
                throw new Exception("Invalid MIME type for cover photo: {$contentType}.");
            }
            
            $fileCategory = $this->getFileCategoryByExtension($originalFileNameForLog);
            if ($fileCategory !== 'image') {
                 $response['message'] = "Invalid file category. Only images are allowed for cover photos."; http_response_code(400);
                 throw new Exception("Invalid file category for cover photo: {$fileCategory}.");
            }

            $fileData = $this->_pm_readFileData($tempFilePath); // Reusing helper
            if ($fileData === null) {
                $response['message'] = 'Could not read file data.'; http_response_code(500);
                throw new Exception("Failed to read temp file data for cover photo: {$tempFilePath}");
            }

            $this->db->pdo->beginTransaction();

            $fileExtension = strtolower(pathinfo($originalFileNameForLog, PATHINFO_EXTENSION));
            if (empty($fileExtension) && $contentType) { // Try to get extension from MIME if not in filename
                 $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif'];
                 if(isset($mimeToExt[$contentType])) $fileExtension = $mimeToExt[$contentType];
                 else $fileExtension = 'img'; // Fallback extension
            } elseif (empty($fileExtension)) {
                $fileExtension = 'img'; // Absolute fallback
            }

            $uniqueNamePart = bin2hex(random_bytes(16));
            $yearMonth = date('Y/m');
            // Construct B2 object name using defined prefix
            $b2ObjectName = self::CP_B2_PATH_PREFIX . "/{$currentUserId}/{$yearMonth}/{$uniqueNamePart}.{$fileExtension}";

            // Reusing the B2 upload helper from createPostWithMedia
            $b2UploadResult = $this->_pm_uploadToB2($fileData, $b2ObjectName, $contentType, $fileSize);
            // $b2UploadResult is ['b2FileId' => ..., 'sha1' => ...]

            $cloudFileVisibility = 'public'; // Cover photos are public
            $cloudFileData = [
                'user_id' => $currentUserId, 'storage_provider' => 'backblaze_b2',
                'provider_file_id' => $b2UploadResult['b2FileId'],
                'file_path_in_provider' => $b2ObjectName, 
                'container_name' => $this->b2BucketName, 'container_id' => $this->b2BucketId,
                'original_filename' => $originalFileNameForLog, 'content_type' => $contentType,
                'size_bytes' => $fileSize, 'content_sha1' => $b2UploadResult['sha1'],
                'file_category' => 'image', 'visibility' => $cloudFileVisibility,
                'uploaded_at_provider' => Medoo::raw('NOW()'),
                'title' => "Cover Photo - " . $originalFileNameForLog,
            ];
            $this->db->insert('cloud_files', $cloudFileData);
            $cloudFileRecordId = (int)$this->db->id();
            if (!$cloudFileRecordId) {
                throw new Exception("DB Error: Failed to insert cover photo into cloud_files.");
            }

            $newCoverPhotoUrl = $this->fileCdnBaseUrl . rawurlencode($b2ObjectName);
            $updateUserResult = $this->db->update('users', [
                'cover_photo' => $newCoverPhotoUrl,
                'updated_at' => Medoo::raw('NOW()')
            ], ['id' => $currentUserId]);

            if ($updateUserResult === false) { // Medoo returns false on failure
                 throw new Exception("DB Error: Failed to update users table with new cover photo URL.");
            }
            // Medoo's update returns a PDOStatement, check rowCount for actual change.
            // if ($updateUserResult->rowCount() === 0) {
            //     // This could mean the URL was already the same, or user ID not found (less likely here due to auth check)
            //     // For simplicity, we'll assume success if no DB error.
            // }


            $this->db->pdo->commit();
            
            $response = [
                'success' => true, 
                'message' => 'Cover photo updated successfully!', 
                'new_cover_photo_url' => $newCoverPhotoUrl
            ];
            http_response_code(200);

        } catch (Exception $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            $detailedError = "handleUploadCoverPhoto Exception (User:{$currentUserId}, File:'{$originalFileNameForLog}'): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            error_log($detailedError);

            if ($response['message'] === 'An unexpected error occurred.') {
                 if (http_response_code() < 400 ) http_response_code(500);
                 $response['message'] = "Server error updating cover photo. Please try again.";
            }
             if (http_response_code() === 200) { // Check if still default 200 (PHP default for success)
                 http_response_code(500); // Ensure error code if not set by specific validation
            }
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // --- Helper methods for Cover Photo (prefixed with _cp_) ---
    private function _cp_isValidCoverMimeType(string $contentType): bool {
        return in_array($contentType, self::CP_ALLOWED_IMAGE_TYPES, true);
    }


    // --- Helper methods (some prefixed with _pm_ from createPostWithMedia, reused or adapted) ---

    private function _pm_isB2ConfiguredAndReady(): bool { // Generic enough to be reused
        return !empty($this->b2AccountId) && !empty($this->b2AppKey) &&
               !empty($this->b2BucketId) && !empty($this->b2BucketName) &&
               $this->db && !empty($this->fileCdnBaseUrl) && $this->fileCdnBaseUrl !== '/';
    }

    private function _pm_validatePostTableVisibility(string &$visibility): void {
        $validVisibilities = ['public', 'friends', 'private']; 
        if (!in_array($visibility, $validVisibilities, true)) {
            $visibility = 'public';
        }
    }

    private function _pm_getUploadErrorMessage(int $errorCode): string { // Generic enough
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => "File is larger than server allows.", UPLOAD_ERR_FORM_SIZE  => "File is larger than form allows.",
            UPLOAD_ERR_PARTIAL    => "File was only partially uploaded.", UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Server error: Missing temp folder.", UPLOAD_ERR_CANT_WRITE => "Server error: Failed to write file.",
            UPLOAD_ERR_EXTENSION  => "PHP extension stopped upload.",
        ];
        return $uploadErrors[$errorCode] ?? "Unknown upload error (Code: {$errorCode})";
    }

    private function _pm_getFileMimeType(string $tempFilePath, string $fallbackType): string { // Generic enough
        if (!is_readable($tempFilePath)) return $fallbackType ?: 'application/octet-stream';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) return $fallbackType ?: 'application/octet-stream';
        $contentType = finfo_file($finfo, $tempFilePath);
        finfo_close($finfo);
        return $contentType ?: $fallbackType ?: 'application/octet-stream';
    }

    private function _pm_isValidPostMimeType(string $contentType): bool {
        $allowedPostMimeTypes = array_merge(self::PM_ALLOWED_IMAGE_TYPES, self::PM_ALLOWED_VIDEO_TYPES);
        return in_array($contentType, $allowedPostMimeTypes, true);
    }

    private function _pm_readFileData(string $tempFilePath): ?string { // Generic enough
        if (!is_readable($tempFilePath)) {
            error_log("Helper: Cannot read temp file: {$tempFilePath}"); return null;
        }
        $fileData = file_get_contents($tempFilePath);
        if ($fileData === false || strlen($fileData) === 0) {
            error_log("Helper: Failed to read file data or file is empty: {$tempFilePath}"); return null;
        }
        return $fileData;
    }

    private function _pm_uploadToB2(string $fileData, string $b2ObjectName, string $contentType, int $fileSize): array
    { // This is generic enough for B2 uploads if $b2ObjectName is pre-constructed
        $sha1 = sha1($fileData);

        $authHttpHeader = "Authorization: Basic " . base64_encode($this->b2AccountId . ":" . $this->b2AppKey);
        $authHttpContextOptions = ['http' => ['method' => 'GET', 'header' => $authHttpHeader . "\r\n", 'timeout' => 30, 'ignore_errors' => true]];
        $authHttpContext = stream_context_create($authHttpContextOptions);
        $authResponseJson = @file_get_contents('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', false, $authHttpContext);
        if ($authResponseJson === false) throw new Exception('B2 Auth: Network error during auth.');
        $authResponse = json_decode($authResponseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($authResponse) || !isset($authResponse['apiUrl'], $authResponse['authorizationToken'])) {
            error_log("B2 Auth Failure (Helper). Rsp: " . substr($authResponseJson, 0, 500));
            throw new Exception('B2 Auth: Invalid API response.');
        }
        $b2ApiUrl = $authResponse['apiUrl'];
        $b2AccountAuthToken = $authResponse['authorizationToken'];

        $getUploadUrlPayload = json_encode(['bucketId' => $this->b2BucketId]);
        $getUploadUrlHttpOptions = ['http' => ['method' => 'POST', 'header' => "Authorization: " . $b2AccountAuthToken . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($getUploadUrlPayload) . "\r\n", 'content' => $getUploadUrlPayload, 'timeout' => 30, 'ignore_errors' => true]];
        $getUploadUrlContext = stream_context_create($getUploadUrlHttpOptions);
        $getUploadUrlResponseJson = @file_get_contents($b2ApiUrl . '/b2api/v2/b2_get_upload_url', false, $getUploadUrlContext);
        if ($getUploadUrlResponseJson === false) throw new Exception('B2 GetUploadURL: Network error.');
        $getUploadUrlResponse = json_decode($getUploadUrlResponseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($getUploadUrlResponse) || !isset($getUploadUrlResponse['uploadUrl'], $getUploadUrlResponse['authorizationToken'])) {
            error_log("B2 GetUpURL Fail (Helper). Rsp: " . substr($getUploadUrlResponseJson, 0, 500));
            throw new Exception('B2 GetUploadURL: Invalid API response.');
        }
        $b2FileUploadUrl = $getUploadUrlResponse['uploadUrl'];
        $b2FileUploadAuthToken = $getUploadUrlResponse['authorizationToken'];

        $curlHeaders = [
            "Authorization: " . $b2FileUploadAuthToken, "X-Bz-File-Name: " . rawurlencode($b2ObjectName),
            "Content-Type: " . $contentType, "X-Bz-Content-Sha1: " . $sha1, "Content-Length: " . $fileSize
        ];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $b2FileUploadUrl, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fileData,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, 
            CURLOPT_HTTPHEADER => $curlHeaders, CURLOPT_FAILONERROR => false
        ]);
        $uploadB2ResponseJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNum = curl_errno($ch); $curlErrorMsg = curl_error($ch);
        curl_close($ch);

        if ($curlErrorNum !== 0) throw new Exception("B2 Upload cURL Error ($curlErrorNum): " . $curlErrorMsg);
        
        $uploadB2ResponseArr = json_decode($uploadB2ResponseJson, true);
        if ($httpCode !== 200) {
            $b2ErrorMsg = "B2 Upload HTTP Error ({$httpCode}).";
            if ($uploadB2ResponseArr && isset($uploadB2ResponseArr['message'])) $b2ErrorMsg .= " B2 Msg: " . $uploadB2ResponseArr['message'];
            error_log("B2 Upload Fail (Helper) HTTP {$httpCode}. Rsp: " . substr($uploadB2ResponseJson ?: '', 0, 300));
            throw new Exception($b2ErrorMsg);
        }
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($uploadB2ResponseArr) || !isset($uploadB2ResponseArr['fileId'])) {
            error_log("B2 Upload Invalid JSON Rsp (Helper). HTTP {$httpCode}. Rsp: " . substr($uploadB2ResponseJson ?: '',0,200));
            throw new Exception("B2 Upload success, but API response was invalid.");
        }
        return ['b2FileId' => $uploadB2ResponseArr['fileId'], 'sha1' => $sha1];
    }

    private function _pm_fetchFullPostData(int $postId): ?array
    {
        // ... (YOUR EXISTING _pm_fetchFullPostData METHOD - UNCHANGED)
        if (!$this->db) {
            error_log("_pm_fetchFullPostData: Database connection not available for post ID {$postId}.");
            return null;
        }

        $postData = $this->db->get('posts', [
            "[>]users(u)" => ["posts.user_id" => "id"],
            "[>]cloud_files(cf)" => ["posts.cloud_file_id" => "id"]
        ], [
            'posts.id', 'posts.user_id', 'posts.content', 'posts.image', 
            'posts.visibility', 'posts.post_type', 'posts.created_at', 'posts.updated_at',
            'u.full_name', 'u.username', 'u.profile_picture(user_avatar_url)', 
            'cf.content_type(media_mime_type)'    
        ], [ 'posts.id' => $postId ]);

        if ($postData) {
            $postData['id'] = (int)$postData['id'];
            $postData['user_id'] = (int)$postData['user_id'];
            $postData['user_avatar'] = $postData['user_avatar_url'] ?: $this->_pm_generateFallbackAvatar(
                $postData['user_full_name'] ?? $postData['user_username'] ?? 'User', 40 
            );
            unset($postData['user_avatar_url']); 
            $postData['like_count'] = 0; $postData['comment_count'] = 0;
            $postData['is_liked_by_current_user'] = false;
        } else {
            error_log("_pm_fetchFullPostData: Post not found for ID {$postId}.");
        }
        return $postData;
    }

    private function _pm_generateFallbackAvatar(string $name, int $size = 32): string 
    {
        // ... (YOUR EXISTING _pm_generateFallbackAvatar METHOD - UNCHANGED)
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
            $size, $size, htmlspecialchars($trimmedName, ENT_QUOTES, 'UTF-8'), 
            htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8'), $fontSizePercentage, 
            htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'), htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
        );
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }

        // ---------------------------------------------------------------------------
    // ---- NEW METHOD: profilePicture() for managing profile picture ----
    // ---------------------------------------------------------------------------
    public function profilePicture()
    {
        // For simplicity in this example, we'll handle GET (to fetch current) and POST (to upload new)
        // A more robust API might have separate endpoints for fetching, uploading initial, and applying crop.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleGetProfilePicture();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleUploadProfilePicture();
        } else {
            header('Content-Type: application/json');
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
            exit;
        }
    }

    private function handleGetProfilePicture()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'profile_picture_url' => null];
        $currentUserId = $_SESSION['user_id'] ?? null;

        if (!$currentUserId) {
            $response['message'] = 'Authentication required.';
            http_response_code(401);
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        try {
            if (!$this->db) {
                $response['message'] = 'Database service unavailable.';
                http_response_code(503);
                throw new Exception("Database service unavailable for handleGetProfilePicture. User: {$currentUserId}");
            }
            $userData = $this->db->get('users', ['profile_picture'], ['id' => (int)$currentUserId]);
            
            if ($userData) {
                $response['success'] = true;
                $response['profile_picture_url'] = $userData['profile_picture'];
                http_response_code(200);
            } else {
                $response['message'] = 'User not found.';
                http_response_code(404);
            }
        } catch (Exception $e) {
            error_log("handleGetProfilePicture Error (User:{$currentUserId}): " . $e->getMessage());
            if (http_response_code() < 400) http_response_code(500);
            if (empty($response['message'])) $response['message'] = 'Error retrieving profile picture information.';
        }
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function handleUploadProfilePicture()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
        $currentUserId = $_SESSION['user_id'] ?? null;
        $originalFileNameForLog = 'N/A';

        // NOTE: Actual cropping should happen on the client or server using an image library (GD, Imagick).
        // This example will simulate receiving a "cropped" file or simply use the uploaded one.
        // If client sends crop coordinates, PHP would need to process the original uploaded image
        // with those coordinates using GD or Imagick before uploading to B2.
        // For this example, we assume the 'profile_picture_file' IS the final (possibly client-side cropped) image.

        try {
            if (!$currentUserId) {
                $response['message'] = 'Authentication required.'; http_response_code(401);
                throw new Exception("User not authenticated for handleUploadProfilePicture.");
            }
            $currentUserId = (int) $currentUserId;

            if (!$this->_pm_isB2ConfiguredAndReady()) {
                $response['message'] = 'Server media configuration error.'; http_response_code(500);
                throw new Exception("B2/CDN not configured for handleUploadProfilePicture. User: {$currentUserId}");
            }
            
            $fileInputName = 'profile_picture_file'; // Expected file input name
            if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
                $uploadErrorCode = $_FILES[$fileInputName]['error'] ?? UPLOAD_ERR_NO_FILE;
                $response['message'] = $this->_pm_getUploadErrorMessage($uploadErrorCode);
                http_response_code(400); throw new Exception("File upload error (code: {$uploadErrorCode}) for profile picture.");
            }

            $file = $_FILES[$fileInputName];
            $tempFilePath = $file['tmp_name'];
            $originalFileNameForLog = basename($file['name']);
            $fileSize = (int) $file['size'];

            if ($fileSize > (self::PP_MAX_FILE_SIZE_MB * 1024 * 1024)) {
                $response['message'] = 'File too large. Max: ' . self::PP_MAX_FILE_SIZE_MB . 'MB.'; http_response_code(400);
                throw new Exception("Profile picture file too large ({$fileSize} bytes). Max: " . self::PP_MAX_FILE_SIZE_MB . "MB.");
            }

            $contentType = $this->_pm_getFileMimeType($tempFilePath, $file['type']);
            if (!$this->_pp_isValidProfilePictureMimeType($contentType)) {
                $response['message'] = 'Invalid file type for profile picture. Allowed: JPG, PNG, GIF, WebP, AVIF.'; http_response_code(400);
                throw new Exception("Invalid MIME type for profile picture: {$contentType}.");
            }
            
            $fileCategory = $this->getFileCategoryByExtension($originalFileNameForLog);
            if ($fileCategory !== 'image') {
                 $response['message'] = "Invalid file category. Only images are allowed for profile pictures."; http_response_code(400);
                 throw new Exception("Invalid file category for profile picture: {$fileCategory}.");
            }

            $fileData = $this->_pm_readFileData($tempFilePath);
            if ($fileData === null) {
                $response['message'] = 'Could not read file data.'; http_response_code(500);
                throw new Exception("Failed to read temp file data for profile picture: {$tempFilePath}");
            }

            // --- Server-side cropping would happen here if coordinates were passed ---
            // Example: if (isset($_POST['crop_x'], $_POST['crop_y'], $_POST['crop_w'], $_POST['crop_h'])) {
            //    $fileData = $this->cropImageWithGD($tempFilePath, $_POST['crop_x'], ...); // You'd implement cropImageWithGD
            //    $fileSize = strlen($fileData); // Update fileSize if cropped
            // }
            // For now, we use the $fileData as is.

            $this->db->pdo->beginTransaction();

            $fileExtension = strtolower(pathinfo($originalFileNameForLog, PATHINFO_EXTENSION));
             if (empty($fileExtension) && $contentType) {
                 $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif'];
                 if(isset($mimeToExt[$contentType])) $fileExtension = $mimeToExt[$contentType];
                 else $fileExtension = 'img';
            } elseif (empty($fileExtension)) {
                $fileExtension = 'img';
            }


            $uniqueNamePart = bin2hex(random_bytes(16));
            $yearMonth = date('Y/m');
            $b2ObjectName = self::PP_B2_PATH_PREFIX . "/{$currentUserId}/{$yearMonth}/{$uniqueNamePart}.{$fileExtension}";

            $b2UploadResult = $this->_pm_uploadToB2($fileData, $b2ObjectName, $contentType, $fileSize);

            $cloudFileVisibility = 'public'; // Profile pictures are public
            $cloudFileData = [
                'user_id' => $currentUserId, 'storage_provider' => 'backblaze_b2',
                'provider_file_id' => $b2UploadResult['b2FileId'],
                'file_path_in_provider' => $b2ObjectName, 
                'container_name' => $this->b2BucketName, 'container_id' => $this->b2BucketId,
                'original_filename' => $originalFileNameForLog, 'content_type' => $contentType,
                'size_bytes' => $fileSize, 'content_sha1' => $b2UploadResult['sha1'],
                'file_category' => 'image', 'visibility' => $cloudFileVisibility,
                'uploaded_at_provider' => Medoo::raw('NOW()'),
                'title' => "Profile Picture - " . $originalFileNameForLog,
            ];
            $this->db->insert('cloud_files', $cloudFileData);
            $cloudFileRecordId = (int)$this->db->id();
            if (!$cloudFileRecordId) {
                throw new Exception("DB Error: Failed to insert profile picture into cloud_files.");
            }

            $newProfilePictureUrl = $this->fileCdnBaseUrl . rawurlencode($b2ObjectName);
            $updateUserResult = $this->db->update('users', [
                'profile_picture' => $newProfilePictureUrl,
                'updated_at' => Medoo::raw('NOW()')
            ], ['id' => $currentUserId]);

            if ($updateUserResult === false) {
                 throw new Exception("DB Error: Failed to update users table with new profile picture URL.");
            }

            $this->db->pdo->commit();
            
            $response = [
                'success' => true, 
                'message' => 'Profile picture updated successfully!', 
                'new_profile_picture_url' => $newProfilePictureUrl
            ];
            http_response_code(200);

        } catch (Exception $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            $detailedError = "handleUploadProfilePicture Exception (User:{$currentUserId}, File:'{$originalFileNameForLog}'): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            error_log($detailedError);

            if (empty($response['message']) || $response['message'] === 'An unexpected error occurred.') {
                 if (http_response_code() < 400 ) http_response_code(500);
                 $response['message'] = "Server error updating profile picture. Please try again.";
            }
             if (http_response_code() === 200) { 
                 http_response_code(500);
            }
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // --- Helper methods for Profile Picture (prefixed with _pp_) ---
    private function _pp_isValidProfilePictureMimeType(string $contentType): bool {
        return in_array($contentType, self::PP_ALLOWED_IMAGE_TYPES, true);
    }

} // End of UploadController class