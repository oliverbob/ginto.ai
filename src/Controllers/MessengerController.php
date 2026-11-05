<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

/**
 * Messenger Controller
 * Handles member-to-member chat functionality (Facebook Messenger-like)
 */
class MessengerController
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
     * Check if user is authenticated
     */
    private function requireAuth(): bool
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please login to use messenger']);
            return false;
        }
        return true;
    }

    /**
     * Get JSON input (handles php://input being consumed)
     */
    private function getJsonInput(): ?array
    {
        // Check if middleware already parsed JSON
        if (isset($GLOBALS['_JSON_BODY']) && is_array($GLOBALS['_JSON_BODY'])) {
            return $GLOBALS['_JSON_BODY'];
        }
        
        // Check for raw body stored by middleware
        if (isset($GLOBALS['_RAW_BODY'])) {
            $data = json_decode($GLOBALS['_RAW_BODY'], true);
            if (is_array($data)) {
                return $data;
            }
        }
        
        // Try reading php://input directly
        $raw = file_get_contents('php://input');
        if ($raw) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
        
        return null;
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrf(): bool
    {
        // Check POST first
        $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? null;
        
        // Check header
        if (!$token) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        
        // Check JSON body
        if (!$token) {
            $input = $this->getJsonInput();
            $token = $input['_csrf'] ?? $input['csrf_token'] ?? null;
        }
        
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        // Debug logging removed to avoid leaking tokens/session identifiers
        
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            // CSRF failure (no detailed debug logging to avoid sensitive output)
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return false;
        }
        return true;
    }

    /**
     * Main messenger view
     */
    public function index(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login?redirect=/messenger');
            exit;
        }

        $isLoggedIn = !empty($_SESSION['user_id']);
        $isAdmin = !empty($_SESSION['is_admin']);
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $userId = (int)$_SESSION['user_id'];
        
        // Get current user info
        $currentUser = $this->db->get('users', ['id', 'username', 'firstname', 'lastname', 'email'], ['id' => $userId]);
        
        include ROOT_PATH . '/src/Views/messenger/index.php';
    }

    /**
     * Get all conversations for current user
     */
    public function getConversations(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Get conversations with last message and other participant info
            $conversations = $this->db->query(
                "SELECT 
                    mc.id,
                    mc.type,
                    mc.name,
                    mc.avatar_url,
                    mc.last_message_at,
                    mcp.last_read_at,
                    mcp.is_muted,
                    mcp.is_archived,
                    (SELECT COUNT(*) FROM member_messages mm 
                     WHERE mm.conversation_id = mc.id 
                     AND mm.created_at > COALESCE(mcp.last_read_at, '1970-01-01')
                     AND mm.sender_id != :user_id1) as unread_count,
                    (SELECT mm.content FROM member_messages mm 
                     WHERE mm.conversation_id = mc.id 
                     ORDER BY mm.created_at DESC LIMIT 1) as last_message,
                    (SELECT mm.sender_id FROM member_messages mm 
                     WHERE mm.conversation_id = mc.id 
                     ORDER BY mm.created_at DESC LIMIT 1) as last_message_sender_id
                FROM member_conversations mc
                INNER JOIN member_conversation_participants mcp ON mc.id = mcp.conversation_id
                WHERE mcp.user_id = :user_id2
                AND mcp.is_archived = FALSE
                ORDER BY mc.last_message_at DESC",
                [':user_id1' => $userId, ':user_id2' => $userId]
            )->fetchAll(\PDO::FETCH_ASSOC);
            
            // For each conversation, get other participants
            foreach ($conversations as &$conv) {
                $participants = $this->db->query(
                    "SELECT u.id, u.username, u.fullname, u.firstname, u.lastname, 
                            u.created_at as member_since,
                            COALESCE(mos.is_online, FALSE) as is_online,
                            mos.last_seen_at
                     FROM member_conversation_participants mcp
                     INNER JOIN users u ON mcp.user_id = u.id
                     LEFT JOIN member_online_status mos ON u.id = mos.user_id
                     WHERE mcp.conversation_id = :conv_id AND mcp.user_id != :user_id",
                    [':conv_id' => $conv['id'], ':user_id' => $userId]
                )->fetchAll(\PDO::FETCH_ASSOC);
                
                $conv['participants'] = $participants;
                
                // For direct chats, use other user's name as conversation name
                if ($conv['type'] === 'direct' && count($participants) === 1) {
                    $other = $participants[0];
                    // Priority: fullname > firstname+lastname > username
                    $conv['display_name'] = !empty($other['fullname']) 
                        ? $other['fullname'] 
                        : (trim(($other['firstname'] ?? '') . ' ' . ($other['lastname'] ?? '')) ?: $other['username']);
                    $conv['is_online'] = (bool)$other['is_online'];
                    $conv['other_user_id'] = $other['id'];
                    $conv['member_since'] = $other['member_since'] ?? null;
                } else {
                    // Group chat - generate display name from other participants (excluding current user)
                    if (count($participants) > 0) {
                        $names = array_map(function($p) {
                            return !empty($p['fullname']) 
                                ? $p['fullname'] 
                                : (trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? '')) ?: $p['username']);
                        }, $participants);
                        
                        // Show up to 3 names, then "+N" for the rest
                        $displayNames = array_slice($names, 0, 3);
                        $conv['display_name'] = implode(', ', $displayNames);
                        if (count($names) > 3) {
                            $conv['display_name'] .= ' +' . (count($names) - 3);
                        }
                    } else {
                        $conv['display_name'] = $conv['name'] ?: 'Group Chat';
                    }
                    $conv['is_online'] = false;
                }
            }
            
            echo json_encode(['success' => true, 'conversations' => $conversations]);
        } catch (\Throwable $e) {
            error_log('Messenger getConversations error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load conversations']);
        }
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
        $before = $_GET['before'] ?? null; // For pagination
        $limit = min((int)($_GET['limit'] ?? 50), 100);
        
        if ($conversationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
            return;
        }
        
        // Verify user is participant
        $isParticipant = $this->db->has('member_conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $userId
        ]);
        
        if (!$isParticipant) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        try {
            $params = [':conv_id' => $conversationId, ':limit' => $limit];
            $beforeClause = '';
            
            if ($before) {
                $beforeClause = 'AND mm.id < :before';
                $params[':before'] = (int)$before;
            }
            
            $messages = $this->db->query(
                "SELECT 
                    mm.id,
                    mm.sender_id,
                    mm.content,
                    mm.message_type,
                    mm.attachment_url,
                    mm.attachment_name,
                    mm.attachment_size,
                    mm.payload,
                    mm.reply_to_id,
                    mm.is_edited,
                    mm.is_deleted,
                    mm.created_at,
                    u.username as sender_username,
                    u.firstname as sender_firstname,
                    u.lastname as sender_lastname,
                    (SELECT mm2.content FROM member_messages mm2 WHERE mm2.id = mm.reply_to_id) as reply_content,
                    (SELECT u2.username FROM member_messages mm2 
                     INNER JOIN users u2 ON mm2.sender_id = u2.id 
                     WHERE mm2.id = mm.reply_to_id) as reply_sender
                FROM member_messages mm
                INNER JOIN users u ON mm.sender_id = u.id
                WHERE mm.conversation_id = :conv_id
                AND mm.is_deleted = FALSE
                {$beforeClause}
                ORDER BY mm.created_at DESC
                LIMIT :limit",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
            
            // Reverse to show oldest first
            $messages = array_reverse($messages);
            
            // Mark messages as read
            $this->db->update('member_conversation_participants', [
                'last_read_at' => date('Y-m-d H:i:s')
            ], [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (\Throwable $e) {
            error_log('Messenger getMessages error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load messages']);
        }
    }

    /**
     * Send a new message
     */
    public function sendMessage(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        if (!$this->validateCsrf()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        // Get JSON body
        $input = $this->getJsonInput();
        if (!$input) {
            $input = $_POST;
        }
        
        $conversationId = (int)($input['conversation_id'] ?? 0);
        $content = trim($input['content'] ?? '');
        $messageType = $input['message_type'] ?? 'text';
        $replyToId = !empty($input['reply_to_id']) ? (int)$input['reply_to_id'] : null;
        
        // Sanitize content
        $content = strip_tags($content);
        
        if (empty($content) && $messageType === 'text') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            return;
        }
        
        // Verify user is participant
        $isParticipant = $this->db->has('member_conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $userId
        ]);
        
        if (!$isParticipant) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        try {
            // Insert message
            $this->db->insert('member_messages', [
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'content' => $content,
                'message_type' => $messageType,
                'reply_to_id' => $replyToId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $messageId = $this->db->id();
            
            // Update conversation last_message_at
            $this->db->update('member_conversations', [
                'last_message_at' => date('Y-m-d H:i:s')
            ], ['id' => $conversationId]);
            
            // Get sender info for response
            $sender = $this->db->get('users', ['username', 'firstname', 'lastname'], ['id' => $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => [
                    'id' => $messageId,
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'content' => $content,
                    'message_type' => $messageType,
                    'reply_to_id' => $replyToId,
                    'is_edited' => false,
                    'created_at' => date('Y-m-d H:i:s'),
                    'sender_username' => $sender['username'],
                    'sender_firstname' => $sender['firstname'],
                    'sender_lastname' => $sender['lastname']
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('Messenger sendMessage error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
        }
    }

    /**
     * Start a new conversation with a user
     */
    public function startConversation(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        if (!$this->validateCsrf()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        $input = $this->getJsonInput();
        if (!$input) {
            $input = $_POST;
        }
        
        $otherUserId = (int)($input['user_id'] ?? 0);
        $initialMessage = trim(strip_tags($input['message'] ?? ''));
        
        if ($otherUserId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            return;
        }
        
        if ($otherUserId === $userId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot message yourself']);
            return;
        }
        
        // Verify other user exists
        $otherUser = $this->db->get('users', ['id', 'username', 'fullname', 'firstname', 'lastname'], ['id' => $otherUserId]);
        if (!$otherUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        try {
            // Check if conversation already exists
            $existingConv = $this->db->query(
                "SELECT mc.id FROM member_conversations mc
                 INNER JOIN member_conversation_participants mcp1 ON mc.id = mcp1.conversation_id AND mcp1.user_id = :user1
                 INNER JOIN member_conversation_participants mcp2 ON mc.id = mcp2.conversation_id AND mcp2.user_id = :user2
                 WHERE mc.type = 'direct'
                 LIMIT 1",
                [':user1' => $userId, ':user2' => $otherUserId]
            )->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingConv) {
                // Un-archive if archived
                $this->db->update('member_conversation_participants', [
                    'is_archived' => false
                ], [
                    'conversation_id' => $existingConv['id'],
                    'user_id' => $userId
                ]);
                
                echo json_encode([
                    'success' => true,
                    'conversation_id' => $existingConv['id'],
                    'existing' => true
                ]);
                return;
            }
            
            // Create new conversation
            $this->db->insert('member_conversations', [
                'type' => 'direct',
                'created_at' => date('Y-m-d H:i:s'),
                'last_message_at' => $initialMessage ? date('Y-m-d H:i:s') : null
            ]);
            
            $conversationId = $this->db->id();
            
            // Add participants
            $this->db->insert('member_conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'joined_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->db->insert('member_conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => $otherUserId,
                'joined_at' => date('Y-m-d H:i:s')
            ]);
            
            // Send initial message if provided
            if ($initialMessage) {
                $this->db->insert('member_messages', [
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'content' => $initialMessage,
                    'message_type' => 'text',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'conversation_id' => $conversationId,
                'existing' => false,
                'other_user' => [
                    'id' => $otherUser['id'],
                    'username' => $otherUser['username'],
                    'display_name' => !empty($otherUser['fullname']) 
                        ? $otherUser['fullname'] 
                        : (trim(($otherUser['firstname'] ?? '') . ' ' . ($otherUser['lastname'] ?? '')) ?: $otherUser['username'])
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('Messenger startConversation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to start conversation']);
        }
    }

    /**
     * Create a group conversation
     */
    public function createGroupConversation(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        if (!$this->validateCsrf()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        $input = $this->getJsonInput();
        if (!$input) {
            $input = $_POST;
        }
        
        $userIds = $input['user_ids'] ?? [];
        $initialMessage = trim(strip_tags($input['message'] ?? ''));
        $groupName = trim(strip_tags($input['name'] ?? ''));
        
        // Validate user IDs
        if (!is_array($userIds) || count($userIds) < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'At least one user is required']);
            return;
        }
        
        // Sanitize user IDs
        $userIds = array_map('intval', $userIds);
        $userIds = array_filter($userIds, fn($id) => $id > 0 && $id !== $userId);
        $userIds = array_unique($userIds);
        $userIds = array_values($userIds); // Re-index
        
        if (count($userIds) < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user IDs']);
            return;
        }
        
        try {
            // Verify all users exist using Medoo's select
            $users = $this->db->select('users', ['id', 'username', 'fullname', 'firstname', 'lastname'], [
                'id' => $userIds
            ]);
            
            if (!$users || count($users) !== count($userIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'One or more users not found']);
                return;
            }
            
            // Generate group name if not provided
            if (empty($groupName)) {
                $names = array_map(function($u) {
                    return !empty($u['fullname']) ? $u['fullname'] : 
                           (trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? '')) ?: $u['username']);
                }, $users);
                
                // Get current user's name
                $currentUser = $this->db->get('users', ['username', 'fullname', 'firstname', 'lastname'], ['id' => $userId]);
                $currentName = !empty($currentUser['fullname']) ? $currentUser['fullname'] : 
                               (trim(($currentUser['firstname'] ?? '') . ' ' . ($currentUser['lastname'] ?? '')) ?: $currentUser['username']);
                
                // Group name is first names of participants (max 3)
                array_unshift($names, $currentName);
                $groupName = implode(', ', array_slice($names, 0, 3));
                if (count($names) > 3) {
                    $groupName .= ' +' . (count($names) - 3);
                }
            }
            
            // Create group conversation
            $this->db->insert('member_conversations', [
                'type' => 'group',
                'name' => $groupName,
                'created_at' => date('Y-m-d H:i:s'),
                'last_message_at' => $initialMessage ? date('Y-m-d H:i:s') : null
            ]);
            
            $conversationId = $this->db->id();
            
            // Add current user as participant
            $this->db->insert('member_conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'joined_at' => date('Y-m-d H:i:s')
            ]);
            
            // Add other participants
            foreach ($userIds as $participantId) {
                $this->db->insert('member_conversation_participants', [
                    'conversation_id' => $conversationId,
                    'user_id' => $participantId,
                    'joined_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Send initial message if provided
            if ($initialMessage) {
                $this->db->insert('member_messages', [
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'content' => $initialMessage,
                    'message_type' => 'text',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'conversation_id' => $conversationId,
                'group_name' => $groupName
            ]);
        } catch (\Throwable $e) {
            error_log('Messenger createGroupConversation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create group']);
        }
    }

    /**
     * Get members of a group conversation
     */
    public function getGroupMembers(int $conversationId): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Verify conversation exists and is a group
            $conversation = $this->db->get('member_conversations', ['id', 'type', 'name'], [
                'id' => $conversationId,
                'type' => 'group'
            ]);
            
            if (!$conversation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Group not found']);
                return;
            }
            
            // Verify user is a participant
            $isParticipant = $this->db->has('member_conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]);
            
            if (!$isParticipant) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'You are not a member of this group']);
                return;
            }
            
            // Get all participants with user info
            $participants = $this->db->select('member_conversation_participants', [
                '[>]users' => ['user_id' => 'id']
            ], [
                'users.id',
                'users.username',
                'users.fullname',
                'users.firstname',
                'users.lastname',
                'member_conversation_participants.joined_at'
            ], [
                'member_conversation_participants.conversation_id' => $conversationId,
                'ORDER' => ['member_conversation_participants.joined_at' => 'ASC']
            ]);
            
            // Format members
            $members = array_map(function($p) use ($userId) {
                $displayName = !empty($p['fullname']) ? $p['fullname'] : 
                               (trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? '')) ?: $p['username']);
                return [
                    'id' => (int)$p['id'],
                    'username' => $p['username'],
                    'display_name' => $displayName,
                    'joined_at' => $p['joined_at'],
                    'is_current_user' => ((int)$p['id'] === $userId)
                ];
            }, $participants ?: []);
            
            echo json_encode([
                'success' => true,
                'group_name' => $conversation['name'],
                'members' => $members
            ]);
            
        } catch (\Throwable $e) {
            error_log('Messenger getGroupMembers error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load group members']);
        }
    }

    /**
     * Search users to start a conversation with
     * Only searches registered (non-pending) users by their names
     */
    public function searchUsers(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        $query = trim($_GET['q'] ?? '');
        
        if (strlen($query) < 1) {
            echo json_encode(['success' => true, 'users' => []]);
            return;
        }
        
        // Check if searching by ID (for pre-populating group composer)
        if (preg_match('/^id:(\d+)$/', $query, $matches)) {
            $targetUserId = (int)$matches[1];
            if ($targetUserId === $userId) {
                echo json_encode(['success' => true, 'users' => []]);
                return;
            }
            
            $user = $this->db->query(
                "SELECT id, username, fullname, firstname, lastname,
                        COALESCE(
                            NULLIF(fullname, ''),
                            NULLIF(TRIM(CONCAT(COALESCE(firstname,''), ' ', COALESCE(lastname,''))), ''),
                            username
                        ) as display_name
                 FROM users 
                 WHERE id = :target_id
                   AND (status = 'active' OR status IS NULL)
                 LIMIT 1",
                [':target_id' => $targetUserId]
            )->fetch(\PDO::FETCH_ASSOC);
            
            if ($user) {
                echo json_encode(['success' => true, 'users' => [[
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'display_name' => $user['display_name']
                ]]]);
            } else {
                echo json_encode(['success' => true, 'users' => []]);
            }
            return;
        }
        
        // Sanitize query - allow letters, numbers, spaces for name search
        $query = preg_replace('/[^a-zA-Z0-9 ]/', '', $query);
        $searchTerm = '%' . $query . '%';
        
        try {
            // Search by fullname, firstname, lastname, or username
            // Only return active users (not pending)
            $users = $this->db->query(
                "SELECT id, username, fullname, firstname, lastname,
                        COALESCE(
                            NULLIF(fullname, ''),
                            NULLIF(TRIM(CONCAT(COALESCE(firstname,''), ' ', COALESCE(lastname,''))), ''),
                            username
                        ) as display_name
                 FROM users 
                 WHERE id != :user_id
                   AND (status = 'active' OR status IS NULL)
                   AND (
                       fullname LIKE :q1
                       OR firstname LIKE :q2
                       OR lastname LIKE :q3
                       OR username LIKE :q4
                   )
                 ORDER BY 
                   CASE 
                     WHEN fullname LIKE :q5 THEN 1
                     WHEN firstname LIKE :q6 THEN 2
                     WHEN username LIKE :q7 THEN 3
                     ELSE 4
                   END,
                   fullname, username
                 LIMIT 20",
                [
                    ':user_id' => $userId,
                    ':q1' => $searchTerm,
                    ':q2' => $searchTerm,
                    ':q3' => $searchTerm,
                    ':q4' => $searchTerm,
                    ':q5' => $searchTerm,
                    ':q6' => $searchTerm,
                    ':q7' => $searchTerm
                ]
            )->fetchAll(\PDO::FETCH_ASSOC);
            
            // Format for response
            $results = array_map(function($u) {
                return [
                    'id' => (int)$u['id'],
                    'username' => $u['username'],
                    'display_name' => $u['display_name']
                ];
            }, $users);
            
            echo json_encode(['success' => true, 'users' => $results]);
        } catch (\Throwable $e) {
            error_log('Messenger searchUsers error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Search failed']);
        }
    }

    /**
     * Get suggested users for typeahead (recent conversation partners)
     */
    public function getSuggestedUsers(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        $excludeIds = $_GET['exclude'] ?? '';
        
        // Parse exclude IDs
        $excludeArray = [$userId];
        if (!empty($excludeIds)) {
            $ids = array_map('intval', explode(',', $excludeIds));
            $excludeArray = array_merge($excludeArray, array_filter($ids, fn($id) => $id > 0));
        }
        
        try {
            // Build named placeholders for exclude list to ensure proper binding
            $excludePlaceholders = [];
            $params = [':user_id' => $userId];
            foreach ($excludeArray as $i => $exId) {
                $key = ':ex' . $i;
                $excludePlaceholders[] = $key;
                $params[$key] = (int)$exId;
            }
            $placeholders = implode(',', $excludePlaceholders);

            $sql = "SELECT DISTINCT u.id, u.username, u.fullname, u.firstname, u.lastname,
                        COALESCE(
                            NULLIF(u.fullname, ''),
                            NULLIF(TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,''))), ''),
                            u.username
                        ) as display_name,
                        MAX(mc.last_message_at) as last_contact
                 FROM member_conversation_participants mcp
                 INNER JOIN member_conversations mc ON mcp.conversation_id = mc.id
                 INNER JOIN member_conversation_participants mcp2 ON mc.id = mcp2.conversation_id AND mcp2.user_id != mcp.user_id
                 INNER JOIN users u ON mcp2.user_id = u.id
                 WHERE mcp.user_id = :user_id
                   AND u.id NOT IN ($placeholders)
                   AND (u.status = 'active' OR u.status IS NULL)
                 GROUP BY u.id, u.username, u.fullname, u.firstname, u.lastname
                 ORDER BY last_contact DESC
                 LIMIT 15";

            $users = $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            
            // Format for response
            $results = array_map(function($u) {
                return [
                    'id' => (int)$u['id'],
                    'username' => $u['username'],
                    'display_name' => $u['display_name']
                ];
            }, $users);
            
            echo json_encode(['success' => true, 'users' => $results]);
        } catch (\Throwable $e) {
            error_log('Messenger getSuggestedUsers error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load suggestions']);
        }
    }

    /**
     * Mark conversation as read
     */
    public function markRead(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        $input = $this->getJsonInput() ?? [];
        $conversationId = (int)($input['conversation_id'] ?? 0);
        
        if ($conversationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
            return;
        }
        
        try {
            $this->db->update('member_conversation_participants', [
                'last_read_at' => date('Y-m-d H:i:s')
            ], [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]);
            
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('Messenger markRead error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to mark as read']);
        }
    }

    /**
     * Update user online status
     */
    public function updateOnlineStatus(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        $input = $this->getJsonInput() ?? [];
        $isOnline = (bool)($input['is_online'] ?? true);
        $typingIn = isset($input['typing_in']) ? (int)$input['typing_in'] : null;
        
        try {
            // Upsert online status
            $exists = $this->db->has('member_online_status', ['user_id' => $userId]);
            
            $data = [
                'is_online' => $isOnline,
                'last_seen_at' => date('Y-m-d H:i:s'),
                'currently_typing_in' => $typingIn
            ];
            
            if ($exists) {
                $this->db->update('member_online_status', $data, ['user_id' => $userId]);
            } else {
                $data['user_id'] = $userId;
                $this->db->insert('member_online_status', $data);
            }
            
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('Messenger updateOnlineStatus error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update status']);
        }
    }

    /**
     * Get unread message count for notification badge
     */
    public function getUnreadCount(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAuth()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $result = $this->db->query(
                "SELECT COUNT(*) as total_unread
                 FROM member_messages mm
                 INNER JOIN member_conversation_participants mcp ON mm.conversation_id = mcp.conversation_id
                 WHERE mcp.user_id = :user_id
                 AND mm.sender_id != :user_id2
                 AND mm.created_at > COALESCE(mcp.last_read_at, '1970-01-01')
                 AND mm.is_deleted = FALSE
                 AND mcp.is_archived = FALSE",
                [':user_id' => $userId, ':user_id2' => $userId]
            )->fetch(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'unread_count' => (int)($result['total_unread'] ?? 0)
            ]);
        } catch (\Throwable $e) {
            error_log('Messenger getUnreadCount error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get unread count']);
        }
    }

    /**
     * Delete/archive a conversation
     */
    public function archiveConversation(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        if (!$this->validateCsrf()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        $input = $this->getJsonInput() ?? [];
        $conversationId = (int)($input['conversation_id'] ?? 0);
        
        if ($conversationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
            return;
        }
        
        try {
            $this->db->update('member_conversation_participants', [
                'is_archived' => true
            ], [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]);
            
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('Messenger archiveConversation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to archive conversation']);
        }
    }
    
    /**
     * Delete a conversation (removes user from conversation)
     */
    public function deleteConversation(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!$this->requireAuth()) return;
        if (!$this->validateCsrf()) return;
        
        $userId = (int)$_SESSION['user_id'];
        
        $input = $this->getJsonInput() ?? [];
        $conversationId = (int)($input['conversation_id'] ?? 0);
        
        if ($conversationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
            return;
        }
        
        try {
            // Remove user from conversation (soft delete - they can still see if they're re-added)
            $this->db->delete('member_conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]);
            
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('Messenger deleteConversation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete conversation']);
        }
    }
}
