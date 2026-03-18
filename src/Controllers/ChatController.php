<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

/**
 * Chat Controller
 * Handles chat-related routes: sandbox creation, image upload, conversations API
 */
class ChatController
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = \Ginto\Core\Database::getInstance();
        }
        $this->db = $db;
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Create sandbox for chat
     */
    public function createSandbox(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF validation
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        try {
            putenv('GINTO_SKIP_SANDBOX_START=1');
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            putenv('GINTO_SKIP_SANDBOX_START');
            $sandboxId = basename($editorRoot);
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['sandbox_id'] = $sandboxId;
            echo json_encode(['success' => true, 'sandbox_id' => $sandboxId]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create sandbox']);
            exit;
        }
    }

    /**
     * Upload image for chat
     */
    public function uploadImage(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Only for logged-in users
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Check for base64 image data
        $imageData = $_POST['image'] ?? '';
        if (empty($imageData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No image data provided']);
            exit;
        }
        
        $ext = 'png';
        $base64Data = '';

        if (preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,(.+)$/i', $imageData, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            $base64Data = $matches[2];
        } else {
            $rawType = strtolower(trim((string)($_POST['image_type'] ?? 'png')));
            if (in_array($rawType, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $ext = $rawType === 'jpeg' ? 'jpg' : $rawType;
            }
            $base64Data = trim($imageData);
        }

        $base64Data = preg_replace('/\s+/', '', $base64Data);
        $binary = base64_decode($base64Data, true);
        
        if ($binary === false || strlen($binary) < 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid image data']);
            exit;
        }
        
        // Limit image size to 25MB
        if (strlen($binary) > 25 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image too large (max 25MB)']);
            exit;
        }
        
        // Create upload directory if needed
        $uploadDir = STORAGE_PATH . '/chat_images/' . $userId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'img_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $filepath = $uploadDir . $filename;
        
        // Save file
        if (file_put_contents($filepath, $binary) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save image']);
            exit;
        }
        
        // Return URL path (relative to storage)
        $imageUrl = '/storage/chat_images/' . $userId . '/' . $filename;
        
        echo json_encode([
            'success' => true,
            'url' => $imageUrl,
            'filename' => $filename
        ]);
        exit;
    }

    /**
     * Upload document for RAG (Retrieval-Augmented Generation)
     * Supports: PDF, TXT, MD, DOC, DOCX, RTF, HTML
     */
    public function uploadDocument(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Only for logged-in users
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Login required to upload documents']);
            exit;
        }
        
        // Parse JSON body if Content-Type is application/json
        $input = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $jsonInput = json_decode($rawBody, true);
            if (is_array($jsonInput)) {
                $input = $jsonInput;
            }
        }
        
        // CSRF validation
        $token = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Get document data - support both 'document' and 'data' keys for compatibility
        $documentData = $input['document'] ?? $input['data'] ?? '';
        $filename = $input['filename'] ?? 'document';
        
        if (empty($documentData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No document data provided']);
            exit;
        }
        
        // Sanitize filename
        $filename = strip_tags($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $filename);
        if (empty($filename)) {
            $filename = 'document';
        }
        
        // Process document
        $ragService = new \Ginto\Handlers\DocumentRagService($this->db);
        
        // Ensure the table exists
        \Ginto\Handlers\DocumentRagService::ensureTable($this->db);
        
        $result = $ragService->uploadDocument($userId, $documentData, $filename);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
        exit;
    }

    /**
     * Get list of user's uploaded RAG documents
     */
    public function getDocuments(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $ragService = new \Ginto\Handlers\DocumentRagService($this->db);
        
        $documents = $ragService->getUserDocuments($userId);
        
        echo json_encode([
            'success' => true,
            'documents' => $documents,
        ]);
        exit;
    }

    /**
     * Delete a RAG document
     */
    public function deleteDocument(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $documentId = (int)($_POST['document_id'] ?? 0);
        
        if ($documentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
            exit;
        }
        
        $ragService = new \Ginto\Handlers\DocumentRagService($this->db);
        $deleted = $ragService->deleteDocument($userId, $documentId);
        
        echo json_encode([
            'success' => $deleted,
            'error' => $deleted ? null : 'Document not found or could not be deleted',
        ]);
        exit;
    }

    /**
     * Serve chat images from storage
     */
    public function serveImage($userId, $filename): void
    {
        // Security: Only allow alphanumeric and basic filename chars
        if (!preg_match('/^\d+$/', $userId) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            http_response_code(404);
            exit;
        }
        
        $filepath = STORAGE_PATH . '/chat_images/' . $userId . '/' . $filename;
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            exit;
        }
        
        // Get MIME type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        // Cache for 1 day
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Serve AI-generated images from storage
     */
    public function serveGeneratedImage($userId, $filename): void
    {
        // Security: Only allow alphanumeric and basic filename chars
        if (!preg_match('/^\d+$/', $userId) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            http_response_code(404);
            exit;
        }
        
        $filepath = STORAGE_PATH . '/generated_images/' . $userId . '/' . $filename;
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            exit;
        }
        
        // Get MIME type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        // Cache for 1 week (generated images don't change)
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=604800');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Serve ImageGen files from storage
     */
    public function serveImageGenFile($filename): void
    {
        // Security: Only allow alphanumeric and basic filename chars
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            http_response_code(404);
            exit;
        }
        
        $filepath = STORAGE_PATH . '/imagegen/' . $filename;
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            exit;
        }
        
        // Get MIME type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        // Cache for 1 week
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=604800');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Get all conversations for logged-in user
     */
    public function conversations(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Only for logged-in users
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // First, clean up expired conversations for this user
            $this->db->delete('chat_conversations', [
                'user_id' => $userId,
                'expires_at[<]' => date('Y-m-d H:i:s')
            ]);
            
            // Load remaining conversations
            $rows = $this->db->select('chat_conversations', [
                'convo_id',
                'title',
                'messages',
                'created_at',
                'expires_at',
                'updated_at'
            ], [
                'user_id' => $userId,
                'ORDER' => ['updated_at' => 'DESC']
            ]);
            
            $convos = [];
            foreach ($rows as $row) {
                $messages = json_decode($row['messages'], true) ?: [];
                // Convert expires_at to Unix timestamp (ms) for proper JS timezone handling
                $expiresAtTs = strtotime($row['expires_at']) * 1000;
                $convos[$row['convo_id']] = [
                    'id' => $row['convo_id'],
                    'title' => $row['title'],
                    'messages' => $messages,
                    'ts' => strtotime($row['updated_at']) * 1000,
                    'created_at' => $row['created_at'],
                    'expires_at' => $expiresAtTs  // Unix timestamp in milliseconds
                ];
            }
            
            echo json_encode(['success' => true, 'convos' => $convos]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load conversations']);
            exit;
        }
    }

    /**
     * Save/update a single conversation
     */
    public function saveConversation(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Only for logged-in users
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Get conversation data from POST body
        $convoId = $_POST['convo_id'] ?? '';
        $title = $_POST['title'] ?? 'New chat';
        $messagesJson = $_POST['messages'] ?? '[]';
        
        if (empty($convoId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing convo_id']);
            exit;
        }
        
        try {
            $messages = json_decode($messagesJson, true);
            if (!is_array($messages)) {
                $messages = [];
            }
            
            // Check if conversation exists
            $existing = $this->db->get('chat_conversations', 'id', [
                'user_id' => $userId,
                'convo_id' => $convoId
            ]);
            
            $now = date('Y-m-d H:i:s');
            
            if ($existing) {
                // Update existing conversation (don't change expires_at - keep original countdown)
                $this->db->update('chat_conversations', [
                    'title' => $title,
                    'messages' => json_encode($messages),
                    'updated_at' => $now
                ], [
                    'user_id' => $userId,
                    'convo_id' => $convoId
                ]);
            } else {
                // Create new conversation with 24-hour expiration from now
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $this->db->insert('chat_conversations', [
                    'user_id' => $userId,
                    'convo_id' => $convoId,
                    'title' => $title,
                    'messages' => json_encode($messages),
                    'created_at' => $now,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now
                ]);
            }
            
            echo json_encode(['success' => true]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save conversation']);
            exit;
        }
    }

    /**
     * Delete a single conversation
     */
    public function deleteConversation(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Only for logged-in users
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $convoId = $_POST['convo_id'] ?? '';
        
        if (empty($convoId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing convo_id']);
            exit;
        }
        
        try {
            $this->db->delete('chat_conversations', [
                'user_id' => $userId,
                'convo_id' => $convoId
            ]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete conversation']);
            exit;
        }
    }

    /**
     * Bulk sync all conversations from client
     */
    public function syncConversations(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Only for logged-in users
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $convosJson = $_POST['convos'] ?? '{}';
        $activeId = $_POST['active_id'] ?? null;
        
        try {
            $clientConvos = json_decode($convosJson, true);
            if (!is_array($clientConvos)) {
                $clientConvos = [];
            }
            
            $now = date('Y-m-d H:i:s');
            
            foreach ($clientConvos as $convoId => $convo) {
                if (empty($convoId) || !is_array($convo)) continue;
                
                $title = $convo['title'] ?? 'New chat';
                $messages = $convo['messages'] ?? [];
                
                // Check if exists
                $existing = $this->db->get('chat_conversations', 'id', [
                    'user_id' => $userId,
                    'convo_id' => $convoId
                ]);
                
                if ($existing) {
                    // Update (keep original expiration)
                    $this->db->update('chat_conversations', [
                        'title' => $title,
                        'messages' => json_encode($messages),
                        'updated_at' => $now
                    ], [
                        'user_id' => $userId,
                        'convo_id' => $convoId
                    ]);
                } else {
                    // Insert with 24-hour expiration
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $this->db->insert('chat_conversations', [
                        'user_id' => $userId,
                        'convo_id' => $convoId,
                        'title' => $title,
                        'messages' => json_encode($messages),
                        'created_at' => $now,
                        'expires_at' => $expiresAt,
                        'updated_at' => $now
                    ]);
                }
            }
            
            echo json_encode(['success' => true]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to sync conversations']);
            exit;
        }
    }

    /**
     * Main chat page (GET /chat)
     */
    public function index(): void
    {
        // Check if user is logged in
        $isLoggedIn = !empty($_SESSION['user_id']);
        
        // Check if user is admin using the centralized helper
        $isAdmin = UserController::isAdmin();
        
        // Don't check sandbox during page load - let the UI fetch it via API
        // This makes page load fast; sandbox status is fetched async when user opens My Files
        $sandboxId = $_SESSION['sandbox_id'] ?? null;

        // Generate CSRF token for the view (use global helper if available)
        if (function_exists('generateCsrfToken')) {
            $csrf_token = generateCsrfToken();
        } else {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $csrf_token = $_SESSION['csrf_token'];
        }

        // Get payment status for logged in users - always check DB for current status
        $paymentStatus = null;
        if ($isLoggedIn) {
            $paymentStatus = $this->db->get('users', 'payment_status', ['id' => $_SESSION['user_id']]);
            // Update session to match DB
            $_SESSION['payment_status'] = $paymentStatus;
        }

        \Ginto\Core\View::view('chat/chat', [
            'title' => 'Ginto AI - agentic chat',
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
            'sandboxId' => $sandboxId,
            'csrf_token' => $csrf_token,
            'paymentStatus' => $paymentStatus
        ]);
        exit;
    }

    /**
     * Mobile WebView chat page (GET /chat-m)
     *
     * Stripped-down version of index() for embedding in Android/iOS WebViews.
     * No header, no sidebar, no drawer JavaScript.
     */
    public function chatMobile(): void
    {
        $isLoggedIn = !empty($_SESSION['user_id']);
        $isAdmin = UserController::isAdmin();
        $sandboxId = $_SESSION['sandbox_id'] ?? null;

        if (function_exists('generateCsrfToken')) {
            $csrf_token = generateCsrfToken();
        } else {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $csrf_token = $_SESSION['csrf_token'];
        }

        $paymentStatus = null;
        if ($isLoggedIn) {
            $paymentStatus = $this->db->get('users', 'payment_status', ['id' => $_SESSION['user_id']]);
            $_SESSION['payment_status'] = $paymentStatus;
        }

        \Ginto\Core\View::view('chat-m/chat-m', [
            'title' => 'Ginto AI',
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
            'sandboxId' => $sandboxId,
            'csrf_token' => $csrf_token,
            'paymentStatus' => $paymentStatus
        ]);
        exit;
    }

    /**
     * Handle streaming chat request (POST /chat)
     */
    public function stream(): void
    {
        $handler = new \Ginto\Handlers\ChatStreamHandler($this->db);
        $handler->handle();
    }

    /**
     * PandaSearch test page (GET /pandasearch)
     */
    public function pandaSearchInfo(): void
    {
        $handler = new \Ginto\Handlers\PandaSearchHandler($this->db);
        $handler->info();
    }

    /**
     * PandaSearch streaming (POST /pandasearch)
     */
    public function pandaSearch(): void
    {
        $handler = new \Ginto\Handlers\PandaSearchHandler($this->db);
        $handler->handle();
    }

    /**
     * ImageGen test page (GET /imagegen)
     */
    public function imageGenInfo(): void
    {
        $handler = new \Ginto\Handlers\ImageGenHandler($this->db);
        $handler->info();
    }

    /**
     * ImageGen streaming (POST /imagegen)
     */
    public function imageGen(): void
    {
        $handler = new \Ginto\Handlers\ImageGenHandler($this->db);
        $handler->handle();
    }

    /**
     * Store disabled tools in session (POST /chat/disabled-tools)
     * Called from client-side to sync disabled tools preference
     */
    public function disabledTools(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        // Parse input
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        // CSRF validation
        $token = $input['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Store disabled tools in session
        $disabledTools = $input['disabled_tools'] ?? [];
        if (!is_array($disabledTools)) {
            $disabledTools = [];
        }
        
        // Sanitize: only allow alphanumeric and underscore tool names
        $disabledTools = array_filter($disabledTools, function($t) {
            return is_string($t) && preg_match('/^[a-zA-Z0-9_]+$/', $t);
        });
        
        $_SESSION['disabled_tools'] = array_values($disabledTools);
        
        echo json_encode(['success' => true, 'disabled_count' => count($_SESSION['disabled_tools'])]);
        exit;
    }
}
