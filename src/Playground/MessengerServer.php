<?php
// src/Playground/MessengerServer.php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * WebSocket server for real-time member messaging
 * Handles message broadcasting, typing indicators, and online status
 */
class MessengerServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage Connection storage */
    protected $clients;
    
    /** @var array Map of user_id => ConnectionInterface[] */
    protected $userConnections = [];
    
    /** @var array Map of conversation_id => user_id[] (online users in conversation) */
    protected $conversationUsers = [];
    
    /** @var array Track active calls: [fromUserId-toUserId] => ['start_time' => timestamp, 'call_type' => 'audio'|'video'] */
    protected $activeCalls = [];
    
    /** @var \PDO Database connection */
    protected $db;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->initDatabase();
    }
    
    /**
     * Initialize database connection
     */
    protected function initDatabase(): void
    {
        try {
            $dotenvPath = dirname(__DIR__, 2) . '/.env';
            if (file_exists($dotenvPath)) {
                $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        $value = trim($value, '"\'');
                        putenv(trim($key) . '=' . $value);
                    }
                }
            }
            
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'ginto';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            
            $this->db = new \PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            error_log('MessengerServer DB connection failed: ' . $e->getMessage());
            $this->db = null;
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn, [
            'user_id' => null,
            'authenticated' => false,
            'conversations' => []
        ]);
        
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            if (!$data || !isset($data['type'])) {
                return;
            }
            
            // Log all call-related messages
            if (strpos($data['type'], 'call') === 0) {
                $this->logCall("MESSAGE_RECEIVED: type={$data['type']}, targetUserId=" . ($data['targetUserId'] ?? 'N/A'));
            }
            
            $clientData = $this->clients[$from];
            
            switch ($data['type']) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                    
                case 'message':
                    if ($clientData['authenticated']) {
                        $this->handleMessage($from, $data, $clientData);
                    }
                    break;
                    
                case 'typing':
                    if ($clientData['authenticated']) {
                        $this->handleTyping($from, $data, $clientData);
                    }
                    break;
                    
                case 'read':
                    if ($clientData['authenticated']) {
                        $this->handleRead($from, $data, $clientData);
                    }
                    break;
                    
                case 'subscribe':
                    if ($clientData['authenticated']) {
                        $this->handleSubscribe($from, $data, $clientData);
                    }
                    break;
                    
                // WebRTC Call Signaling
                case 'call_offer':
                    if ($clientData['authenticated']) {
                        $this->handleCallOffer($from, $data, $clientData);
                    }
                    break;
                    
                case 'call_answer':
                    if ($clientData['authenticated']) {
                        $this->handleCallAnswer($from, $data, $clientData);
                    }
                    break;
                    
                case 'call_ice':
                    if ($clientData['authenticated']) {
                        $this->handleCallIce($from, $data, $clientData);
                    }
                    break;
                    
                case 'call_join':
                    if ($clientData['authenticated']) {
                        $this->handleCallJoin($from, $data, $clientData);
                    }
                    break;
                    
                case 'call_end':
                    if ($clientData['authenticated']) {
                        $this->handleCallEnd($from, $data, $clientData);
                    }
                    break;
            }
        } catch (\Exception $e) {
            error_log('MessengerServer onMessage error: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle authentication
     */
    protected function handleAuth(ConnectionInterface $conn, array $data): void
    {
        $userId = (int)($data['userId'] ?? 0);
        $token = $data['token'] ?? '';
        
        if ($userId <= 0) {
            $conn->send(json_encode(['type' => 'auth_error', 'error' => 'Invalid user']));
            return;
        }
        
        // In production, validate token properly
        // For now, trust the userId from client (since they already have a session)
        
        // Update client data
        $clientData = $this->clients[$conn] ?? [];
        $clientData['user_id'] = $userId;
        $clientData['authenticated'] = true;
        $this->clients[$conn] = $clientData;
        
        // Track user connection
        if (!isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }
        $this->userConnections[$userId][$conn->resourceId] = $conn;
        
        // Update online status in DB
        $this->updateOnlineStatus($userId, true);
        
        // Broadcast online status to relevant users
        $this->broadcastOnlineStatus($userId, true);
        
        // Load user's conversations and subscribe to them
        $this->subscribeToUserConversations($conn, $userId);
        
        $conn->send(json_encode(['type' => 'auth_success', 'user_id' => $userId]));
        
        echo "User {$userId} authenticated on connection {$conn->resourceId}\n";
    }
    
    /**
     * Handle incoming message broadcast
     */
    protected function handleMessage(ConnectionInterface $from, array $data, array $clientData): void
    {
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $message = $data['message'] ?? null;
        
        if (!$conversationId || !$message) return;
        
        // Get all participants of this conversation
        $participants = $this->getConversationParticipants($conversationId);
        
        // Broadcast to all participants except sender
        foreach ($participants as $participantId) {
            if ($participantId === $clientData['user_id']) continue;
            
            if (isset($this->userConnections[$participantId])) {
                foreach ($this->userConnections[$participantId] as $conn) {
                    try {
                        $conn->send(json_encode([
                            'type' => 'message',
                            'conversation_id' => $conversationId,
                            'message' => $message
                        ]));
                    } catch (\Exception $e) {
                        error_log('Broadcast error: ' . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Handle typing indicator
     */
    protected function handleTyping(ConnectionInterface $from, array $data, array $clientData): void
    {
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $isTyping = (bool)($data['is_typing'] ?? false);
        
        if (!$conversationId) return;
        
        $participants = $this->getConversationParticipants($conversationId);
        
        foreach ($participants as $participantId) {
            if ($participantId === $clientData['user_id']) continue;
            
            if (isset($this->userConnections[$participantId])) {
                foreach ($this->userConnections[$participantId] as $conn) {
                    try {
                        $conn->send(json_encode([
                            'type' => 'typing',
                            'conversation_id' => $conversationId,
                            'user_id' => $clientData['user_id'],
                            'is_typing' => $isTyping
                        ]));
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            }
        }
    }
    
    /**
     * Handle read receipt
     */
    protected function handleRead(ConnectionInterface $from, array $data, array $clientData): void
    {
        $conversationId = (int)($data['conversation_id'] ?? 0);
        
        if (!$conversationId) return;
        
        $participants = $this->getConversationParticipants($conversationId);
        
        foreach ($participants as $participantId) {
            if ($participantId === $clientData['user_id']) continue;
            
            if (isset($this->userConnections[$participantId])) {
                foreach ($this->userConnections[$participantId] as $conn) {
                    try {
                        $conn->send(json_encode([
                            'type' => 'read',
                            'conversation_id' => $conversationId,
                            'user_id' => $clientData['user_id']
                        ]));
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            }
        }
    }
    
    /**
     * Handle conversation subscription
     */
    protected function handleSubscribe(ConnectionInterface $conn, array $data, array $clientData): void
    {
        $conversationId = (int)($data['conversation_id'] ?? 0);
        
        if (!$conversationId) return;
        
        // Verify user is participant
        if ($this->db) {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM member_conversation_participants WHERE conversation_id = ? AND user_id = ?"
            );
            $stmt->execute([$conversationId, $clientData['user_id']]);
            
            if (!$stmt->fetch()) return;
        }
        
        // Track subscription
        if (!isset($this->conversationUsers[$conversationId])) {
            $this->conversationUsers[$conversationId] = [];
        }
        $this->conversationUsers[$conversationId][$clientData['user_id']] = true;
        
        // Update client data
        $clientData['conversations'][] = $conversationId;
        $this->clients[$conn] = $clientData;
    }
    
    /**
     * Subscribe user to their conversations
     */
    protected function subscribeToUserConversations(ConnectionInterface $conn, int $userId): void
    {
        if (!$this->db) return;
        
        try {
            $stmt = $this->db->prepare(
                "SELECT conversation_id FROM member_conversation_participants WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
            
            $conversations = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $clientData = $this->clients[$conn];
            $clientData['conversations'] = $conversations;
            $this->clients[$conn] = $clientData;
            
            foreach ($conversations as $convId) {
                if (!isset($this->conversationUsers[$convId])) {
                    $this->conversationUsers[$convId] = [];
                }
                $this->conversationUsers[$convId][$userId] = true;
            }
        } catch (\Exception $e) {
            error_log('subscribeToUserConversations error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get conversation participants
     */
    protected function getConversationParticipants(int $conversationId): array
    {
        if (!$this->db) return [];
        
        try {
            $stmt = $this->db->prepare(
                "SELECT user_id FROM member_conversation_participants WHERE conversation_id = ?"
            );
            $stmt->execute([$conversationId]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            error_log('getConversationParticipants error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update user online status in database
     */
    protected function updateOnlineStatus(int $userId, bool $isOnline): void
    {
        if (!$this->db) return;
        
        try {
            // Upsert
            $stmt = $this->db->prepare(
                "INSERT INTO member_online_status (user_id, is_online, last_seen_at) 
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE is_online = ?, last_seen_at = NOW()"
            );
            $stmt->execute([$userId, $isOnline, $isOnline]);
        } catch (\Exception $e) {
            error_log('updateOnlineStatus error: ' . $e->getMessage());
        }
    }
    
    /**
     * Broadcast online status to users who have conversations with this user
     */
    protected function broadcastOnlineStatus(int $userId, bool $isOnline): void
    {
        if (!$this->db) return;
        
        try {
            // Find all users who share a conversation with this user
            $stmt = $this->db->prepare(
                "SELECT DISTINCT mcp2.user_id 
                 FROM member_conversation_participants mcp1
                 INNER JOIN member_conversation_participants mcp2 ON mcp1.conversation_id = mcp2.conversation_id
                 WHERE mcp1.user_id = ? AND mcp2.user_id != ?"
            );
            $stmt->execute([$userId, $userId]);
            $relatedUsers = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $message = json_encode([
                'type' => 'online',
                'user_id' => $userId,
                'is_online' => $isOnline
            ]);
            
            foreach ($relatedUsers as $relatedUserId) {
                if (isset($this->userConnections[$relatedUserId])) {
                    foreach ($this->userConnections[$relatedUserId] as $conn) {
                        try {
                            $conn->send($message);
                        } catch (\Exception $e) {
                            // Ignore
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('broadcastOnlineStatus error: ' . $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $clientData = $this->clients[$conn] ?? [];
        $userId = $clientData['user_id'] ?? null;
        
        if ($userId) {
            // Remove from user connections
            if (isset($this->userConnections[$userId][$conn->resourceId])) {
                unset($this->userConnections[$userId][$conn->resourceId]);
            }
            
            // If user has no more connections, mark offline
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
                $this->updateOnlineStatus($userId, false);
                $this->broadcastOnlineStatus($userId, false);
            }
            
            // Remove from conversation tracking
            foreach ($clientData['conversations'] ?? [] as $convId) {
                if (isset($this->conversationUsers[$convId][$userId])) {
                    unset($this->conversationUsers[$convId][$userId]);
                }
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        error_log('MessengerServer error: ' . $e->getMessage());
        try {
            $conn->close();
        } catch (\Exception $ex) {
            // ignore
        }
    }
    
    /**
     * Log call events to file
     */
    protected function logCall(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/../storage/logs/call.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
    
    /**
     * Save call event to database conversation history
     */
    protected function saveCallEvent(int $fromUserId, int $toUserId, string $eventType, string $callType = 'audio', ?string $reason = null, ?int $durationSeconds = null): void
    {
        if (!$this->db) {
            $this->logCall("SAVE_EVENT: Database connection unavailable");
            return;
        }
        
        try {
            // Find conversation where both users are participants
            $stmt = $this->db->prepare(
                "SELECT mcp1.conversation_id 
                 FROM member_conversation_participants mcp1
                 INNER JOIN member_conversation_participants mcp2 
                    ON mcp1.conversation_id = mcp2.conversation_id
                 INNER JOIN member_conversations mc 
                    ON mc.id = mcp1.conversation_id
                 WHERE mcp1.user_id = ? AND mcp2.user_id = ? AND mc.type = 'direct'
                 LIMIT 1"
            );
            $stmt->execute([$fromUserId, $toUserId]);
            $conv = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conv) {
                $this->logCall("SAVE_EVENT: No conversation found between users {$fromUserId} and {$toUserId}");
                return;
            }
            
            $conversationId = $conv['conversation_id'];
            
            $durationText = '';
            if ($durationSeconds !== null && $durationSeconds > 0) {
                $minutes = floor($durationSeconds / 60);
                $seconds = $durationSeconds % 60;
                if ($minutes > 0) {
                    $durationText = "{$minutes} min" . ($seconds > 0 ? " {$seconds} sec" : "");
                } else {
                    $durationText = "{$seconds} sec";
                }
            }
            
            $messageText = match($eventType) {
                'call_started' => "Started {$callType} call",
                'call_ended' => "Ended {$callType} call" . ($durationText ? " ({$durationText})" : ($reason ? " ({$reason})" : "")),
                default => "Call event: {$eventType}"
            };
            
            // Save call event as a message in member_messages table
            $payload = json_encode([
                'type' => $callType,
                'event' => $eventType,
                'reason' => $reason,
                'duration_seconds' => $durationSeconds
            ]);
            
            $stmt = $this->db->prepare(
                "INSERT INTO member_messages (conversation_id, sender_id, content, message_type, payload, created_at) 
                 VALUES (?, ?, ?, 'call', ?, NOW())"
            );
            
            if (!$stmt->execute([$conversationId, $fromUserId, $messageText, $payload])) {
                $this->logCall("SAVE_EVENT: Failed to insert member_messages: " . json_encode($stmt->errorInfo()));
                return;
            }
            
            $messageId = $this->db->lastInsertId();
            
            // Broadcast call event to both users via WebSocket
            $callMessage = [
                'type' => 'message',
                'conversation_id' => $conversationId,
                'message' => [
                    'id' => $messageId,
                    'conversation_id' => $conversationId,
                    'sender_id' => $fromUserId,
                    'content' => $messageText,
                    'message_type' => 'call',
                    'payload' => $payload,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            $broadcastJson = json_encode($callMessage);
            
            // Send to both participants
            foreach ([$fromUserId, $toUserId] as $userId) {
                if (isset($this->userConnections[$userId])) {
                    foreach ($this->userConnections[$userId] as $conn) {
                        try {
                            $conn->send($broadcastJson);
                        } catch (\Exception $e) {
                            // Ignore broadcast errors
                        }
                    }
                }
            }
            
            $this->logCall("SAVE_EVENT: {$eventType} for users {$fromUserId}<->{$toUserId}, reason={$reason}, convId={$conversationId}, broadcast sent");
        } catch (\Exception $e) {
            $this->logCall("SAVE_EVENT: EXCEPTION - " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }
    
    /**
     * Handle WebRTC call offer - relay to target user
     */
    protected function handleCallOffer(ConnectionInterface $from, array $data, array $clientData): void
    {
        $targetUserId = (int)($data['targetUserId'] ?? 0);
        $offer = $data['offer'] ?? null;
        $callType = $data['callType'] ?? 'audio';
        $callerName = $data['callerName'] ?? 'Someone';
        $conversationId = (int)($data['conversationId'] ?? 0);
        $isGroup = !empty($data['isGroup']) || ($conversationId > 0 && count($this->getConversationParticipants($conversationId)) > 1);
        $participantsList = [];
        if ($isGroup && $conversationId) {
            $parts = $this->getConversationParticipants($conversationId);
            foreach ($parts as $p) {
                $participantsList[] = ['id' => (int)$p];
            }
        }
        $fromUserId = $clientData['user_id'];
        
        // Log immediately
        echo "handleCallOffer called: from={$fromUserId}, target={$targetUserId}, type={$callType}\n";
        $this->logCall("CALL_OFFER: target={$targetUserId}, type={$callType}, caller={$callerName}");
        
        if (!$offer) {
            $this->logCall("CALL_OFFER: FAILED - Missing offer payload");
            return;
        }

        // If specific target provided, behave as before (direct offer)
        if ($targetUserId) {
            // Save call event to history and track start time (direct calls only)
            echo "Saving call event...\n";
            try {
                $this->saveCallEvent($fromUserId, $targetUserId, 'call_started', $callType);
            } catch (\Exception $e) {
                $this->logCall("CALL_OFFER: SAVE_EVENT_FAILED - " . $e->getMessage());
            }

            // Track call start time
            $callKey = min($fromUserId, $targetUserId) . '-' . max($fromUserId, $targetUserId);
            $this->activeCalls[$callKey] = [
                'start_time' => time(),
                'call_type' => $callType,
                'initiator' => $fromUserId
            ];
            $this->logCall("CALL_TRACKING: Started tracking call {$callKey}");

            $onlineUsers = implode(',', array_keys($this->userConnections));
            $this->logCall("CALL_OFFER: from={$fromUserId}, online_users=[{$onlineUsers}]");

            // Send offer to all connections of target user
            if (isset($this->userConnections[$targetUserId])) {
                $message = json_encode([
                    'type' => 'call_offer',
                    'fromUserId' => $fromUserId,
                    'callerName' => $callerName,
                    'offer' => $offer,
                    'callType' => $callType,
                    'conversationId' => $conversationId,
                    'isGroup' => $isGroup,
                    'participants' => $participantsList
                ]);

                $connCount = 0;
                foreach ($this->userConnections[$targetUserId] as $conn) {
                    try {
                        $conn->send($message);
                        $connCount++;
                    } catch (\Exception $e) {
                        $this->logCall("CALL_OFFER: Send error - " . $e->getMessage());
                    }
                }

                $this->logCall("CALL_OFFER: SUCCESS - Sent to {$connCount} connections of user {$targetUserId}");
            } else {
                $this->logCall("CALL_OFFER: FAILED - Target user {$targetUserId} not online");
                // Target user not online
                $from->send(json_encode([
                    'type' => 'call_unavailable',
                    'targetUserId' => $targetUserId,
                    'reason' => 'User is offline'
                ]));
            }

            return;
        }

        // If no specific targetUserId but a conversationId exists, broadcast the offer to conversation participants
        if ($conversationId) {
            $this->logCall("CALL_OFFER: Broadcasting to conversation {$conversationId}");

            $participants = $this->getConversationParticipants($conversationId);
            // Extra debug: log connection counts and conversation-subscription tracking for troubleshooting
            try {
                $connCounts = [];
                foreach ($participants as $pdbg) {
                    $pid = (int)$pdbg;
                    $connCounts[$pid] = isset($this->userConnections[$pid]) ? count($this->userConnections[$pid]) : 0;
                }
                $tracked = isset($this->conversationUsers[$conversationId]) ? array_keys($this->conversationUsers[$conversationId]) : [];
                $this->logCall("CALL_OFFER: Debug participants_conn_counts=" . json_encode($connCounts));
                $this->logCall("CALL_OFFER: Debug conversation_users_tracked=" . json_encode($tracked));
            } catch (\Exception $e) {
                $this->logCall("CALL_OFFER: Debug logging failed: " . $e->getMessage());
            }

            if (empty($participants)) {
                $this->logCall("CALL_OFFER: FAILED - No participants found for conversation {$conversationId}");
                $from->send(json_encode([
                    'type' => 'call_unavailable',
                    'conversationId' => $conversationId,
                    'reason' => 'No participants'
                ]));
                return;
            }

            $sent = 0;
            $offline = [];

            foreach ($participants as $participantId) {
                $participantId = (int)$participantId;
                if ($participantId === $fromUserId) continue;

                if (isset($this->userConnections[$participantId])) {
                    $message = json_encode([
                        'type' => 'call_offer',
                        'fromUserId' => $fromUserId,
                        'callerName' => $callerName,
                        'offer' => $offer,
                        'callType' => $callType,
                        'conversationId' => $conversationId,
                        'isGroup' => true,
                        'participants' => $participantsList,
                        'targetUserId' => $participantId
                    ]);

                    foreach ($this->userConnections[$participantId] as $conn) {
                        try {
                            $conn->send($message);
                            $sent++;
                        } catch (\Exception $e) {
                            $this->logCall("CALL_OFFER: Broadcast send error to {$participantId} - " . $e->getMessage());
                        }
                    }
                } else {
                    $offline[] = $participantId;
                }
            }

            $this->logCall("CALL_OFFER: Broadcast complete, sent={$sent}, offline_count=" . count($offline));

            if ($sent === 0) {
                // Nobody online
                $from->send(json_encode([
                    'type' => 'call_unavailable',
                    'conversationId' => $conversationId,
                    'reason' => 'No participants online'
                ]));
            }

            return;
        }
    }
    
    /**
     * Handle WebRTC call answer - relay to caller
     */
    protected function handleCallAnswer(ConnectionInterface $from, array $data, array $clientData): void
    {
        $targetUserId = (int)($data['targetUserId'] ?? 0);
        $answer = $data['answer'] ?? null;
        $conversationId = (int)($data['conversationId'] ?? 0);
        $isGroup = !empty($data['isGroup']);
        
        $fromUserId = $clientData['user_id'];
        $this->logCall("CALL_ANSWER: from={$fromUserId}, target={$targetUserId}");
        
        if (!$targetUserId || !$answer) {
            $this->logCall("CALL_ANSWER: FAILED - Missing targetUserId or answer");
            return;
        }
        
        // Send answer to caller
        if (isset($this->userConnections[$targetUserId])) {
            $message = json_encode([
                'type' => 'call_answer',
                'fromUserId' => $fromUserId,
                'answer' => $answer,
                'conversationId' => $conversationId,
                'isGroup' => $isGroup
            ]);
            
            $connCount = 0;
            foreach ($this->userConnections[$targetUserId] as $conn) {
                try {
                    $conn->send($message);
                    $connCount++;
                } catch (\Exception $e) {
                    $this->logCall("CALL_ANSWER: Send error - " . $e->getMessage());
                }
            }
            
            $this->logCall("CALL_ANSWER: SUCCESS - Sent to {$connCount} connections of user {$targetUserId}");
        } else {
            $this->logCall("CALL_ANSWER: FAILED - Target user {$targetUserId} not online");
        }
    }

    /**
     * Handle notification that a participant joined the call - broadcast to other participants
     */
    protected function handleCallJoin(ConnectionInterface $from, array $data, array $clientData): void
    {
        $conversationId = (int)($data['conversationId'] ?? $data['conversation_id'] ?? 0);
        $joiningUserId = (int)($data['joiningUserId'] ?? $data['userId'] ?? $data['user_id'] ?? 0);

        if (!$conversationId || !$joiningUserId) {
            $this->logCall("CALL_JOIN: Missing conversationId or joiningUserId");
            return;
        }

        $fromUserId = $clientData['user_id'];
        $this->logCall("CALL_JOIN: from={$fromUserId}, joining={$joiningUserId}, conv={$conversationId}");

        // Get participants and notify each (except the joiner)
        $participants = $this->getConversationParticipants($conversationId);
        foreach ($participants as $participantId) {
            $participantId = (int)$participantId;
            if ($participantId === $joiningUserId) continue;

            if (isset($this->userConnections[$participantId])) {
                $message = json_encode([
                    'type' => 'call_join',
                    'joiningUserId' => $joiningUserId,
                    'conversationId' => $conversationId
                ]);

                foreach ($this->userConnections[$participantId] as $conn) {
                    try {
                        $conn->send($message);
                    } catch (\Exception $e) {
                        $this->logCall("CALL_JOIN: Send error - " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Handle ICE candidate exchange
     */
    protected function handleCallIce(ConnectionInterface $from, array $data, array $clientData): void
    {
        $targetUserId = (int)($data['targetUserId'] ?? 0);
        $candidate = $data['candidate'] ?? null;
        $conversationId = (int)($data['conversationId'] ?? 0);
        $isGroup = !empty($data['isGroup']);
        
        if (!$targetUserId || !$candidate) {
            return;
        }
        
        $fromUserId = $clientData['user_id'];
        $this->logCall("CALL_ICE: from={$fromUserId}, target={$targetUserId}");
        
        // Relay ICE candidate to target user
        if (isset($this->userConnections[$targetUserId])) {
            $message = json_encode([
                'type' => 'call_ice',
                'fromUserId' => $fromUserId,
                'candidate' => $candidate,
                'conversationId' => $conversationId,
                'isGroup' => $isGroup
            ]);
            
            foreach ($this->userConnections[$targetUserId] as $conn) {
                try {
                    $conn->send($message);
                } catch (\Exception $e) {
                    $this->logCall("CALL_ICE: Send error - " . $e->getMessage());
                }
            }
        } else {
            $this->logCall("CALL_ICE: FAILED - Target user {$targetUserId} not online");
        }
    }
    
    /**
     * Handle call end - notify the other party
     */
    protected function handleCallEnd(ConnectionInterface $from, array $data, array $clientData): void
    {
        $targetUserId = (int)($data['targetUserId'] ?? 0);
        $reason = $data['reason'] ?? 'ended';
        $conversationId = (int)($data['conversationId'] ?? 0);
        
        if (!$targetUserId) {
            return;
        }
        
        $fromUserId = $clientData['user_id'];
        $this->logCall("CALL_END: from={$fromUserId}, target={$targetUserId}, reason={$reason}");
        
        // Calculate call duration if call was tracked
        $callKey = min($fromUserId, $targetUserId) . '-' . max($fromUserId, $targetUserId);
        $durationSeconds = null;
        $callType = 'audio'; // default
        
        if (isset($this->activeCalls[$callKey])) {
            $durationSeconds = time() - $this->activeCalls[$callKey]['start_time'];
            $callType = $this->activeCalls[$callKey]['call_type'];
            unset($this->activeCalls[$callKey]);
            $this->logCall("CALL_TRACKING: Call {$callKey} lasted {$durationSeconds} seconds");
        } else {
            $this->logCall("CALL_TRACKING: No active call found for {$callKey}");
        }
        
        // Save call end event to history with duration
        $this->saveCallEvent($fromUserId, $targetUserId, 'call_ended', $callType, $reason, $durationSeconds);
        
        // Notify target user that call ended
        if (isset($this->userConnections[$targetUserId])) {
            $message = json_encode([
                'type' => 'call_end',
                'fromUserId' => $fromUserId,
                'reason' => $reason,
                'conversationId' => $conversationId
            ]);
            
            foreach ($this->userConnections[$targetUserId] as $conn) {
                try {
                    $conn->send($message);
                } catch (\Exception $e) {
                    // Ignore
                }
            }
            
            $this->logCall("CALL_END: SUCCESS - Notified user {$targetUserId}");
        }
    }
}
