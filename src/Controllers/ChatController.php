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
        
        // Parse base64 data URL
        if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,(.+)$/i', $imageData, $matches)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid image format']);
            exit;
        }
        
        $ext = strtolower($matches[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
        $base64Data = $matches[2];
        $binary = base64_decode($base64Data);
        
        if ($binary === false || strlen($binary) < 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid image data']);
            exit;
        }
        
        // Limit image size to 5MB
        if (strlen($binary) > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image too large (max 5MB)']);
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
}
