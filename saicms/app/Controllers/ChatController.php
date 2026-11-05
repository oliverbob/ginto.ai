<?php

namespace App\Controllers;

use Core\Controller;
use Medoo\Medoo;

class ChatController extends Controller
{
    private ?int $currentUserId = null;
    private ?string $currentUserFullName = null;

    public function __construct()
    {
        parent::__construct();
        if (isset($_SESSION["user_id"])) {
            $this->currentUserId = (int) $_SESSION["user_id"];
            $this->currentUserFullName = $_SESSION["user_full_name"] ?? "User";
        }
    }

    private function ensureDbReady(): void
    {
        if ($this->db === null) {
            header("Content-Type: application/json");
            http_response_code(503);
            echo json_encode([
                "success" => false,
                "error" => "Database service is currently unavailable.",
            ]);
            exit();
        }
    }

    public function direct()
    {
        $this->ensureDbReady();
        header("Content-Type: application/json");

        error_log(
            "CHAT_DIRECT: CALLED. METHOD=" .
                ($_SERVER["REQUEST_METHOD"] ?? "N/A") .
                ". CurrentUserID=" .
                ($this->currentUserId ?? "NULL")
        );

        if (strtoupper($_SERVER["REQUEST_METHOD"]) !== "POST") {
            http_response_code(405);
            echo json_encode([
                "success" => false,
                "error" => "Method Not Allowed. Use POST.",
            ]);
            exit();
        }

        $input = json_decode(file_get_contents("php://input"), true);
        $otherUserId = filter_var(
            $input["otherUserId"] ?? null,
            FILTER_VALIDATE_INT
        );
        error_log(
            "CHAT_DIRECT: Input otherUserId=" . var_export($otherUserId, true)
        );

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized. Please log in.",
            ]);
            exit();
        }
        if (!$otherUserId) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Invalid otherUserId provided.",
            ]);
            exit();
        }
        if ($this->currentUserId === $otherUserId) {
            error_log(
                "CHAT_DIRECT: User {$this->currentUserId} attempted to chat with self."
            );
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Cannot start a conversation with yourself.",
            ]);
            exit();
        }

        $conversation = $this->getOrCreateDirectConversation($otherUserId);

        if ($conversation) {
            echo json_encode([
                "success" => true,
                "conversation" => $conversation,
            ]);
        } else {
            $errorMessage =
                "Could not get or create conversation. Check server logs for details in getOrCreateDirectConversation.";
            $lastDbErrorArr = $this->db->error;
            if ($lastDbErrorArr && isset($lastDbErrorArr[2])) {
                $errorMessage .= " DB Error: " . $lastDbErrorArr[2];
            }
            error_log(
                "Direct method: Conversation fetch/create failed. " .
                    ($lastDbErrorArr
                        ? "Last DB error: " . json_encode($lastDbErrorArr)
                        : "No specific DB error reported.")
            );
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $errorMessage]);
        }
        exit();
    }

    private function getOrCreateDirectConversation(int $otherUserId): ?array
    {
        if (!$this->db || !$this->db->pdo instanceof \PDO) {
            error_log(
                "GETORCREATE FATAL: Database or PDO connection not available."
            );
            return null;
        }
        $db = $this->db;
        $pdo = $db->pdo;

        if (!$this->currentUserId) {
            error_log("GETORCREATE FATAL: currentUserId is not set.");
            return null;
        }

        $existingConversationId = $db->get(
            "conversations (c)",
            [
                "[>]conversation_participants (cp1)" => [
                    "c.id" => "conversation_id",
                ],
                "[>]conversation_participants (cp2)" => [
                    "c.id" => "conversation_id",
                ],
            ],
            "c.id",
            [
                "AND" => [
                    "c.type" => "direct",
                    "cp1.user_id" => $this->currentUserId,
                    "cp2.user_id" => $otherUserId,
                ],
                "LIMIT" => 1,
            ]
        );

        $medooErrorOnGet = $db->error;
        if (
            $existingConversationId === false &&
            $medooErrorOnGet &&
            $medooErrorOnGet[0] !== "00000" &&
            $medooErrorOnGet[0] !== null
        ) {
            error_log(
                "GETORCREATE DB Error during existing conversation check: " .
                    json_encode($medooErrorOnGet) .
                    " Last Query: " .
                    $db->last()
            );
            return null;
        }

        if ($existingConversationId) {
            error_log(
                "GETORCREATE: Found existing direct conv ID: {$existingConversationId} for users {$this->currentUserId}/{$otherUserId}."
            );
            return $db->get("conversations", "*", [
                "id" => $existingConversationId,
            ]);
        }

        error_log(
            "GETORCREATE (MANUAL TXN): No existing direct conv. Creating new for users {$this->currentUserId}/{$otherUserId}."
        );

        $conversationId = null;
        $manualTxnSucceeded = false;

        try {
            if (!$pdo->beginTransaction()) {
                error_log(
                    "GETORCREATE (MANUAL TXN) ERROR: pdo->beginTransaction() returned false. PDO Error: " .
                        json_encode($pdo->errorInfo())
                );
                return null;
            }
            error_log(
                "GETORCREATE (MANUAL TXN): pdo->beginTransaction() successful."
            );

            $convInsertStatement = $db->insert("conversations", [
                "type" => "direct",
                "created_by_user_id" => $this->currentUserId,
            ]);

            if (
                !$convInsertStatement ||
                $convInsertStatement->rowCount() === 0
            ) {
                error_log(
                    "GETORCREATE (MANUAL TXN) Error: Insert into 'conversations' failed. RowCount: " .
                        ($convInsertStatement
                            ? $convInsertStatement->rowCount()
                            : "stmt_false") .
                        ". Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                error_log(
                    "GETORCREATE (MANUAL TXN): Rolled back after conversation insert failure."
                );
                return null;
            }

            $newlyInsertedConversationId = $db->id();
            if (
                !$newlyInsertedConversationId ||
                !is_numeric($newlyInsertedConversationId) ||
                (int) $newlyInsertedConversationId <= 0
            ) {
                error_log(
                    "GETORCREATE (MANUAL TXN) Error: Medoo->id() returned invalid ID after conversation insert. Returned: " .
                        var_export($newlyInsertedConversationId, true) .
                        ". Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                error_log(
                    "GETORCREATE (MANUAL TXN): Rolled back after invalid conversation ID."
                );
                return null;
            }
            $conversationId = (int) $newlyInsertedConversationId;
            error_log(
                "GETORCREATE (MANUAL TXN): New conversation inserted. ID: {$conversationId}."
            );

            $participant1Data = [
                "conversation_id" => $conversationId,
                "user_id" => (int) $this->currentUserId,
            ];
            $p1InsertStatement = $db->insert(
                "conversation_participants",
                $participant1Data
            );
            if (!$p1InsertStatement || $p1InsertStatement->rowCount() === 0) {
                error_log(
                    "GETORCREATE (MANUAL TXN) Error: Insert participant 1 (user:{$this->currentUserId}) failed for conv ID {$conversationId}. Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                error_log(
                    "GETORCREATE (MANUAL TXN): Rolled back after participant 1 insert failure."
                );
                return null;
            }
            error_log(
                "GETORCREATE (MANUAL TXN): Participant 1 (user:{$this->currentUserId}) inserted for conv ID {$conversationId}."
            );

            $participant2Data = [
                "conversation_id" => $conversationId,
                "user_id" => (int) $otherUserId,
            ];
            $p2InsertStatement = $db->insert(
                "conversation_participants",
                $participant2Data
            );
            if (!$p2InsertStatement || $p2InsertStatement->rowCount() === 0) {
                error_log(
                    "GETORCREATE (MANUAL TXN) Error: Insert participant 2 (user:{$otherUserId}) failed for conv ID {$conversationId}. Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                error_log(
                    "GETORCREATE (MANUAL TXN): Rolled back after participant 2 insert failure."
                );
                return null;
            }
            error_log(
                "GETORCREATE (MANUAL TXN): Participant 2 (user:{$otherUserId}) inserted for conv ID {$conversationId}."
            );

            if ($pdo->commit()) {
                error_log(
                    "GETORCREATE (MANUAL TXN): pdo->commit() successful."
                );
                $manualTxnSucceeded = true;
            } else {
                error_log(
                    "GETORCREATE (MANUAL TXN) ERROR: pdo->commit() returned false. PDO Error: " .
                        json_encode($pdo->errorInfo())
                );
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    error_log(
                        "GETORCREATE (MANUAL TXN): Attempted rollback after commit() failed."
                    );
                }
            }
        } catch (\PDOException $e) {
            error_log(
                "GETORCREATE (MANUAL TXN) PDO EXCEPTION: " .
                    $e->getMessage() .
                    ". Code: " .
                    $e->getCode() .
                    ". Trace: " .
                    $e->getTraceAsString()
            );
            error_log(
                "GETORCREATE (MANUAL TXN): Last Medoo Query: " . $db->last()
            );
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                    error_log(
                        "GETORCREATE (MANUAL TXN): Rolled back due to PDOException."
                    );
                } catch (\PDOException $rollBackEx) {
                    error_log(
                        "GETORCREATE (MANUAL TXN) Error during rollback: " .
                            $rollBackEx->getMessage()
                    );
                }
            }
            return null;
        } catch (\Throwable $e) {
            error_log(
                "GETORCREATE (MANUAL TXN) GENERAL EXCEPTION: " .
                    $e->getMessage() .
                    ". Trace: " .
                    $e->getTraceAsString()
            );
            error_log(
                "GETORCREATE (MANUAL TXN): Last Medoo Query: " . $db->last()
            );
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                    error_log(
                        "GETORCREATE (MANUAL TXN): Rolled back due to General Exception."
                    );
                } catch (\PDOException $rollBackEx) {
                    error_log(
                        "GETORCREATE (MANUAL TXN) Error during rollback: " .
                            $rollBackEx->getMessage()
                    );
                }
            }
            return null;
        }

        error_log(
            "GETORCREATE (MANUAL TXN): Finished. Manual TXN Succeeded: " .
                ($manualTxnSucceeded ? "True" : "False") .
                ". Conversation ID: " .
                var_export($conversationId, true)
        );

        if ($manualTxnSucceeded && $conversationId > 0) {
            error_log(
                "GETORCREATE (MANUAL TXN): Success! Fetching newly created conversation for ID: {$conversationId}."
            );
            $newConversationData = $db->get("conversations", "*", [
                "id" => $conversationId,
            ]);
            $medooErrorOnFinalGet = $db->error;
            if (
                $newConversationData === false &&
                $medooErrorOnFinalGet &&
                $medooErrorOnFinalGet[0] !== "00000" &&
                $medooErrorOnFinalGet[0] !== null
            ) {
                error_log(
                    "GETORCREATE (MANUAL TXN) DB Error fetching newly created conversation (ID: {$conversationId}): " .
                        json_encode($medooErrorOnFinalGet) .
                        " Last Query: " .
                        $db->last()
                );
            }
            return $newConversationData;
        } else {
            error_log(
                "GETORCREATE (MANUAL TXN): FAILED to create conversation or obtain valid ID. Returning null. manualTxnSucceeded: " .
                    var_export($manualTxnSucceeded, true) .
                    ". conversationId: " .
                    var_export($conversationId, true)
            );
            return null;
        }
    }

    /**
     * Placeholder for a method to get conversation details.
     * You MUST implement this method to fetch type, name, and icon_url from your 'conversations' table.
     */
    private function getConversationDetails(int $conversationId): ?array
    {
        // Example implementation using Medoo (this->db)
        if (!$this->db) return null;
        $details = $this->db->get("conversations",
            ["id", "type", "name", "icon_url"],
            ["id" => $conversationId]
        );
        return $details ?: null;
    }

    /**
     * Placeholder for getting the other participant in a direct chat.
     * You might need this if getConversationDetails doesn't fully resolve direct chat names/icons.
     */
    private function getOtherParticipantInDirectChat(int $conversationId, int $currentUserIdToExclude): ?array
    {
        if (!$this->db) return null;
        $participant = $this->db->get("conversation_participants (cp)",
            ["[>]users (u)" => ["cp.user_id" => "id"]],
            ["u.id(user_id)", "u.fullname(user_full_name)", "u.username", "u.profile_picture"],
            [
                "cp.conversation_id" => $conversationId,
                "cp.user_id[!]" => $currentUserIdToExclude, // User ID is not the current user
                "LIMIT" => 1
            ]
        );
        return $participant ?: null;
    }

    public function sendMessageApi()
    {
        $this->ensureDbReady();
        header("Content-Type: application/json");

        if (strtoupper($_SERVER["REQUEST_METHOD"]) !== "POST") {
            http_response_code(405);
            echo json_encode(["success" => false, "error" => "Method Not Allowed. Use POST."]);
            exit();
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid JSON payload. " . json_last_error_msg()]);
            exit();
        }

        $conversationIdInput = $input["conversationId"] ?? null;
        $conversationId = null;
        if ($conversationIdInput !== null) {
            // Try to filter as integer first
            $filteredConvId = filter_var($conversationIdInput, FILTER_VALIDATE_INT);
            if ($filteredConvId !== false && $filteredConvId > 0) {
                $conversationId = $filteredConvId;
            } elseif (is_string($conversationIdInput) && preg_match('/^\d+$/', $conversationIdInput)) {
                // If it's a string but purely numeric, cast to int
                $conversationId = (int)$conversationIdInput;
            } else if (is_string($conversationIdInput) && !empty(trim($conversationIdInput))){
                // If it's a non-numeric string (e.g., "group_123" if your sendMessage handles it)
                // For now, we are expecting $conversationId to be the numeric ID for DB lookups later
                // Your $this->sendMessage method might need to handle prefixes if it gets them.
                // We will proceed assuming $conversationId (if set) will be numeric by the time we call getConversationDetails
                error_log("sendMessageApi: Received non-integer, non-numeric-string conversationId: " . $conversationIdInput . ". Ensure downstream methods can handle or convert if necessary.");
                // Attempt to extract numeric part if it's like "group_123"
                if (preg_match('/_(\d+)$/', $conversationIdInput, $matches)) {
                    $conversationId = (int)$matches[1];
                } else if (ctype_digit((string)$conversationIdInput)){ // Check again if it's all digits
                    $conversationId = (int)$conversationIdInput;
                }
                // If it's still not a valid number, it will fail the !$conversationId check later
            }
        }


        $content = trim($input["content"] ?? "");
        $messageType = $input["messageType"] ?? "text";
        $metadata = isset($input["metadata"]) && is_array($input["metadata"]) ? $input["metadata"] : null;
        $parentMessageIdInput = $input["parentMessageId"] ?? null;
        $parentMessageId = null;
        if ($parentMessageIdInput !== null) {
            $filteredParentId = filter_var($parentMessageIdInput, FILTER_VALIDATE_INT);
            if ($filteredParentId !== false && $filteredParentId > 0) {
                $parentMessageId = $filteredParentId;
            }
        }

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized. Please log in."]);
            exit();
        }
        if (!$conversationId || !is_numeric($conversationId) || (int)$conversationId <=0 ) { // Stricter check for a valid numeric ID
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing or invalid numeric conversationId.", "received_id" => $conversationIdInput]);
            exit();
        }
        if (empty($content) && $messageType === "text" && !str_starts_with($messageType, "system_")) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Content is required for text messages."]);
            exit();
        }
        if (mb_strlen($content) > 5000) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Message content is too long (max 5000 chars)."]);
            exit();
        }

        // Call your existing sendMessage method
        // $conversationId passed here should be the clean, numeric ID
        $baseSentMessage = $this->sendMessage(
            (int)$conversationId,
            $content,
            $messageType,
            $metadata,
            $parentMessageId
        );

        if ($baseSentMessage && isset($baseSentMessage["id"])) {
            // ---- ENRICHMENT FOR WEBSOCKET PAYLOAD ----
            $enrichedMessageForWebSocket = $baseSentMessage; // Start with base details from sendMessage (which calls getMessageById)

            // Ensure critical sender fields are correctly sourced, primarily from $baseSentMessage.
            // $baseSentMessage from $this->getMessageById should already have joined user details.
            $enrichedMessageForWebSocket['sender_id'] = (string)($baseSentMessage['sender_id'] ?? $this->currentUserId);
            $enrichedMessageForWebSocket['sender_full_name'] = $baseSentMessage['sender_full_name'] ?? $this->currentUserFullName ?? 'User';
            $enrichedMessageForWebSocket['sender_profile_picture'] = $baseSentMessage['sender_profile_picture'] ?? null;

            // Fetch conversation entity details (name, icon, type) using the numeric $conversationId
            $conversationDetails = $this->getConversationDetails((int)$conversationId);

            if ($conversationDetails) {
                $enrichedMessageForWebSocket['conversation_type'] = $conversationDetails['type'] ?? 'unknown';
                $finalConvName = $conversationDetails['name'];
                $finalConvIcon = $conversationDetails['icon_url']; // This might be null or an actual URL

                if ($enrichedMessageForWebSocket['conversation_type'] === 'direct') {
                    if (empty($finalConvName)) { // If conversation table has no specific name for this direct chat
                        $otherParticipant = $this->getOtherParticipantInDirectChat((int)$conversationId, $this->currentUserId);
                        if ($otherParticipant) {
                            $finalConvName = $otherParticipant['full_name'] ?? $otherParticipant['username'] ?? 'Chat User';
                            // For direct chats, the conversation_icon often defaults to the other user's avatar
                            // if no specific conversation_icon is set.
                            if (empty($finalConvIcon)) { // Only use other participant's pic if conversation_icon is not already set
                            $finalConvIcon = $otherParticipant['profile_picture'] ?? null;
                            }
                        } else {
                            $finalConvName = $finalConvName ?: 'Chat'; // Fallback if other participant not found
                        }
                    }
                } elseif ($enrichedMessageForWebSocket['conversation_type'] === 'group') {
                    $finalConvName = $finalConvName ?: 'Group Chat'; // Ensure group has a name
                    // For groups, $finalConvIcon will be used if it's a URL. If it's null, client will generate SVG.
                } else {
                    $finalConvName = $finalConvName ?: 'Conversation'; // Fallback for unknown types
                }
                $enrichedMessageForWebSocket['conversation_name'] = $finalConvName;
                // Only pass icon if it's a valid HTTP/HTTPS URL, otherwise pass null to let client generate SVG.
                $enrichedMessageForWebSocket['conversation_icon'] = ($finalConvIcon && preg_match('/^https?:\/\//i', $finalConvIcon)) ? $finalConvIcon : null;

            } else {
                // Fallback if conversation details couldn't be fetched
                $enrichedMessageForWebSocket['conversation_type'] = 'unknown';
                $enrichedMessageForWebSocket['conversation_name'] = 'Conversation';
                $enrichedMessageForWebSocket['conversation_icon'] = null;
                error_log("sendMessageApi: Could not fetch conversation details for ID {$conversationId} during enrichment.");
            }
            // ---- END ENRICHMENT ----

            $this->notifyWebSocketServerOfNewMessage($enrichedMessageForWebSocket);

            // Respond to the original HTTP request with the message details fetched from DB
            echo json_encode(["success" => true, "message" => $baseSentMessage]);

        } else {
            http_response_code(500);
            $errorMessage = "Failed to send message.";
            if (is_string($baseSentMessage) && !empty($baseSentMessage)) {
                $errorMessage .= " Error: " . $baseSentMessage;
            } elseif (isset($baseSentMessage['error'])) {
                $errorMessage .= " Error: " . $baseSentMessage['error'];
            }
            error_log("sendMessageApi: Error from this->sendMessage: " . var_export($baseSentMessage, true));
            echo json_encode(["success" => false, "error" => $errorMessage]);
        }
        exit();
    }

    protected function notifyWebSocketServerOfNewMessage(array $messageObject)
    {
        if (!extension_loaded("zmq")) {
            error_log(
                "ChatController ZMQ Push Error: ZMQ PHP extension is NOT loaded. Update cannot be sent."
            );
            return;
        }

        $conversationId = $messageObject["conversation_id"] ?? null;
        $senderId = $messageObject["sender_id"] ?? null;

        if (!$conversationId || !$senderId) {
            error_log(
                "ChatController ZMQ Push Error: Missing conversation_id or sender_id. Data: " . json_encode($messageObject)
            );
            return;
        }

        $recipientUserIds = [];
        try {
            $participantsData = $this->db->select(
                "conversation_participants",
                "user_id",
                [
                    "conversation_id" => $conversationId,
                    "user_id[!]" => $senderId,
                ]
            );

            if ($this->db->error && $this->db->error[0] !== "00000" && $this->db->error[0] !== null) {
                error_log(
                    "ChatController ZMQ Push Error: DB error fetching participants for conv {$conversationId} (sender {$senderId}): " .
                        json_encode($this->db->error) . " Query: " . $this->db->last()
                );
                return;
            }

            if ($participantsData) {
                $recipientUserIds = array_map('strval', $participantsData);
            }
            
            $recipientUserIds = array_values(
                array_filter(array_unique($recipientUserIds), function($id) {
                    return is_numeric($id) && (int)$id > 0;
                })
            );

        } catch (\Exception $e) {
            error_log(
                "ChatController ZMQ Push Error: Exception fetching participants for conv {$conversationId} (sender {$senderId}): " . $e->getMessage()
            );
            return;
        }

        if (empty($recipientUserIds)) {
            error_log(
                "ChatController ZMQ Push Info: No other recipients for conv {$conversationId} (Sender: {$senderId})."
            );
            return;
        }

        error_log(
            "ChatController ZMQ Push: For ConvID {$conversationId}, SenderID {$senderId}. Determined recipients: " . json_encode($recipientUserIds)
        );

        $pusherSocket = null;
        $allSendsAttempted = true; // Flag to track if all sends were even tried

        try {
            $zmqDsn = getenv("ZMQ_WEBSOCKET_PUSH_DSN") ?: "tcp://127.0.0.1:5555";
            $zmqContext = new \ZMQContext();
            $pusherSocket = $zmqContext->getSocket(\ZMQ::SOCKET_PUSH, "chatPusherToWs_" . uniqid());

            if (!$pusherSocket) {
                error_log("ChatController ZMQ Push Error: Failed to create ZMQ PUSH socket.");
                return;
            }
            $pusherSocket->setSockOpt(\ZMQ::SOCKOPT_LINGER, 250);
            $pusherSocket->connect($zmqDsn);

            $payloadForWebSocket = $messageObject;
            $processedRecipientCount = 0;

            foreach ($recipientUserIds as $recipientUserIdString) {
                $processedRecipientCount++;
                error_log(
                    "ChatController ZMQ Push: Loop iteration {$processedRecipientCount}/" . count($recipientUserIds) . " for recipient {$recipientUserIdString} (ConvID {$conversationId})."
                );

                $messageToPushToZMQ = [
                    "type" => "broadcast_new_message",
                    "recipientUserId" => $recipientUserIdString,
                    "payload" => $payloadForWebSocket,
                ];
                $jsonData = json_encode($messageToPushToZMQ);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("ChatController ZMQ Push Error: JSON encode failed for user {$recipientUserIdString}. Error: " . json_last_error_msg() . ". Skipping this recipient.");
                    $allSendsAttempted = false; // Mark that not all were fully processed
                    continue; // Skip to next recipient
                }
                
                error_log(
                    "ChatController ZMQ Push: PREPARING to send to user {$recipientUserIdString} for conv {$conversationId} (Sender: {$senderId}). Type: {$payloadForWebSocket['message_type']}"
                );
                
                $bytesSent = $pusherSocket->send($jsonData);

                if ($bytesSent === false) {
                    $zmqErrNo = $pusherSocket->geterrcode();
                    $zmqErrMsg = method_exists($pusherSocket, 'geterrormsg') ? $pusherSocket->geterrormsg() : (\ZMQ::errno() !== 0 ? \zmq_strerror(\ZMQ::errno()) : 'ZMQ send fail, no ext err msg');
                    error_log(
                        "ChatController ZMQ Push Error: send() FAILED for user {$recipientUserIdString}. ZMQ Err: [{$zmqErrNo}] {$zmqErrMsg}. Loop continues."
                    );
                    $allSendsAttempted = false; // Mark that at least one send failed
                } else {
                    error_log(
                        "ChatController ZMQ Push: send() for user {$recipientUserIdString} returned non-false. Bytes: " . var_export($bytesSent, true) . ". Loop continues."
                    );
                }
                error_log("ChatController ZMQ Push: End of current iteration for user {$recipientUserIdString}.");
            } // End of foreach loop

            if ($processedRecipientCount < count($recipientUserIds)) {
                error_log("ChatController ZMQ Push WARNING: Loop processed only {$processedRecipientCount} out of " . count($recipientUserIds) . " recipients. This indicates an early exit from the loop that was not a 'continue'.");
            } else {
                error_log("ChatController ZMQ Push: Loop completed. Processed {$processedRecipientCount} recipients.");
            }

        } catch (\ZMQException $e) {
            error_log("ChatController ZMQException during push: " . $e->getMessage() . " (Code: {$e->getCode()}).");
            $allSendsAttempted = false;
        } catch (\Throwable $e) {
            error_log("ChatController General Throwable during ZMQ push: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $allSendsAttempted = false;
        } finally {
            if ($pusherSocket instanceof \ZMQSocket) {
                // error_log("ChatController ZMQ Push: Reached finally. Pusher socket object exists.");
            }
            error_log("ChatController ZMQ Push: Exiting notifyWebSocketServerOfNewMessage. All sends attempted flag: " . ($allSendsAttempted ? 'true' : 'false'));
        }
    }

    private function sendMessage(
        int $conversationId,
        string $content,
        string $messageType = "text",
        ?array $metadata = null,
        ?int $parentMessageId = null
    ): ?array {
        if (!$this->db || !$this->db->pdo instanceof \PDO) {
            error_log(
                "sendMessage FATAL: Database or PDO connection not available."
            );
            return null;
        }
        $db = $this->db;
        $pdo = $db->pdo;

        if (!$this->currentUserId) {
            error_log("sendMessage FATAL: currentUserId is null.");
            return null;
        }

        if (
            !$db->has("conversation_participants", [
                "AND" => [
                    "conversation_id" => $conversationId,
                    "user_id" => $this->currentUserId,
                ],
            ])
        ) {
            error_log(
                "sendMessage Error: User {$this->currentUserId} not participant in conv {$conversationId}."
            );
            return null;
        }
        if ($parentMessageId !== null) {
            if (
                !$db->has("messages", [
                    "AND" => [
                        "id" => $parentMessageId,
                        "conversation_id" => $conversationId,
                        "is_unsent" => false,
                    ],
                ])
            ) {
                error_log(
                    "sendMessage Warning: Invalid parent_message_id {$parentMessageId} for conv {$conversationId}. Treating as non-reply."
                );
                $parentMessageId = null;
            }
        }

        $messageData = [
            "conversation_id" => $conversationId,
            "sender_id" => $this->currentUserId,
            "content" =>
                $messageType === "text" ||
                $messageType === "link_preview" ||
                str_starts_with($messageType, "system_")
                    ? $content
                    : null,
            "message_type" => $messageType,
            "metadata" => $metadata ? json_encode($metadata) : null,
            "parent_message_id" => $parentMessageId,
        ];

        $messageId = null;
        $manualTxnSucceeded = false;
        error_log(
            "sendMessage (MANUAL TXN): Starting for conv {$conversationId}."
        );

        try {
            if (!$pdo->beginTransaction()) {
                error_log(
                    "sendMessage (MANUAL TXN) ERROR: pdo->beginTransaction() failed. PDO Error: " .
                        json_encode($pdo->errorInfo())
                );
                return null;
            }
            error_log(
                "sendMessage (MANUAL TXN): pdo->beginTransaction() successful."
            );

            $insertStmt = $db->insert("messages", $messageData);
            if (!$insertStmt || $insertStmt->rowCount() === 0) {
                error_log(
                    "sendMessage (MANUAL TXN) Error: Message insert failed. Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                return null;
            }
            $newMessageId = $db->id();
            if (
                !$newMessageId ||
                !is_numeric($newMessageId) ||
                (int) $newMessageId <= 0
            ) {
                error_log(
                    "sendMessage (MANUAL TXN) Error: Invalid ID from Medoo->id(). ID: " .
                        var_export($newMessageId, true) .
                        ". Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                return null;
            }
            $messageId = (int) $newMessageId;
            error_log(
                "sendMessage (MANUAL TXN): Message inserted. ID: {$messageId}"
            );

            $updateResult = $db->update(
                "conversations",
                [
                    "last_message_id" => $messageId,
                    "updated_at" => Medoo::raw("NOW()"),
                ],
                ["id" => $conversationId]
            );
            if ($updateResult === false) {
                error_log(
                    "sendMessage (MANUAL TXN) Error: Conversation update failed. Medoo Error: " .
                        json_encode($db->error)
                );
                $pdo->rollBack();
                return null;
            }
            if (
                $updateResult->rowCount() === 0 &&
                !$db->has("conversations", ["id" => $conversationId])
            ) {
                error_log(
                    "sendMessage (MANUAL TXN) CRITICAL Error: Conversation ID {$conversationId} NOT FOUND during update."
                );
                $pdo->rollBack();
                return null;
            }
            error_log(
                "sendMessage (MANUAL TXN): Conversation updated or check passed for ID {$conversationId}."
            );

            if ($pdo->commit()) {
                error_log(
                    "sendMessage (MANUAL TXN): pdo->commit() successful."
                );
                $manualTxnSucceeded = true;
            } else {
                error_log(
                    "sendMessage (MANUAL TXN) ERROR: pdo->commit() failed. PDO Error: " .
                        json_encode($pdo->errorInfo())
                );
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        } catch (\PDOException $e) {
            error_log(
                "sendMessage (MANUAL TXN) PDO EXCEPTION: " . $e->getMessage()
            );
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\PDOException $re) {
                };
            }
            return null;
        } catch (\Throwable $e) {
            error_log(
                "sendMessage (MANUAL TXN) GENERAL EXCEPTION: " .
                    $e->getMessage()
            );
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\PDOException $re) {
                };
            }
            return null;
        }

        if ($manualTxnSucceeded && $messageId > 0) {
            error_log(
                "sendMessage (MANUAL TXN): Success! Fetching message for ID: {$messageId}."
            );
            return $this->getMessageById($messageId);
        }
        error_log("sendMessage (MANUAL TXN): FAILED. Returning null.");
        return null;
    }

    public function getMessageById(int $messageId): ?array
    {
        if (!$this->db) {
            error_log("getMessageById: DB not ready.");
            return null;
        }
        $db = $this->db;
        $columns = [
            "messages.id",
            "messages.conversation_id",
            "messages.sender_id",
            "messages.content",
            "messages.message_type",
            "messages.metadata(metadata_json)",
            "messages.parent_message_id",
            "messages.sent_at",
            "messages.edited_at",
            "messages.is_unsent",
            "sender_user.username(sender_username)",
            "sender_user.full_name(sender_full_name)",
            "sender_user.profile_picture(sender_profile_picture)",
        ];
        $join = ["[>]users(sender_user)" => ["sender_id" => "id"]];
        $where = ["messages.id" => $messageId, "messages.is_unsent" => false];
        $message = $db->get("messages", $join, $columns, $where);
        $medooError = $db->error;
        if (
            $message === false &&
            $medooError &&
            $medooError[0] !== "00000" &&
            $medooError[0] !== null
        ) {
            error_log(
                "getMessageById DB Error: " .
                    json_encode($medooError) .
                    " Last Query: " .
                    $db->last()
            );
        }
        if ($message) {
            if (isset($message["metadata_json"])) {
                $decodedMeta = json_decode($message["metadata_json"], true);
                $message["metadata"] =
                    json_last_error() === JSON_ERROR_NONE ? $decodedMeta : null;
                unset($message["metadata_json"]);
            } else {
                $message["metadata"] = null;
            }
        } else {
            error_log(
                "getMessageById: Message with ID {$messageId} not found or is unsent. Last Query: " .
                    $db->last()
            );
        }
        return $message;
    }

    public function getConversationMessagesApi()
    {
        $this->ensureDbReady();
        header("Content-Type: application/json");
        $conversationId = filter_input(
            INPUT_GET,
            "conversationId",
            FILTER_VALIDATE_INT
        );
        $limit = filter_input(INPUT_GET, "limit", FILTER_VALIDATE_INT) ?: 30;
        $beforeMessageIdInput = filter_input(
            INPUT_GET,
            "beforeMessageId",
            FILTER_VALIDATE_INT
        );
        $beforeMessageId =
            $beforeMessageIdInput === false || $beforeMessageIdInput === null
                ? null
                : $beforeMessageIdInput;
        $afterMessageIdInput = filter_input(
            INPUT_GET,
            "afterMessageId",
            FILTER_VALIDATE_INT
        );
        $afterMessageId =
            $afterMessageIdInput === false || $afterMessageIdInput === null
                ? null
                : $afterMessageIdInput;

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit();
        }
        if (!$conversationId) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Missing conversationId.",
            ]);
            exit();
        }

        error_log(
            "getConversationMessagesApi: Calling getConversationMessages with convId={$conversationId}, limit={$limit}, beforeMsgId=" .
                var_export($beforeMessageId, true) .
                ", afterMsgId=" .
                var_export($afterMessageId, true)
        );
        $messages = $this->getConversationMessages(
            $conversationId,
            $limit,
            $beforeMessageId,
            $afterMessageId
        );

        if ($messages === null) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Failed to retrieve messages.",
            ]);
        } else {
            echo json_encode(["success" => true, "messages" => $messages]);
        }
        exit();
    }

    private function getConversationMessages(
        int $conversationId,
        int $limit = 30,
        ?int $beforeMessageId = null,
        ?int $afterMessageId = null
    ): ?array {
        $db = $this->db;
        if (!$this->currentUserId) {
            error_log("getConversationMessages: currentUserId is null.");
            return null;
        }
        error_log(
            "GET_CONV_MESSAGES: ENTERED. ConvID: {$conversationId}, UserID: {$this->currentUserId}, Limit: {$limit}, BeforeMsgID: " .
                var_export($beforeMessageId, true) .
                ", AfterMsgID: " .
                var_export($afterMessageId, true)
        );
        if (
            !$db->has("conversation_participants", [
                "AND" => [
                    "conversation_id" => $conversationId,
                    "user_id" => $this->currentUserId,
                ],
            ])
        ) {
            error_log(
                "GET_CONV_MESSAGES: User {$this->currentUserId} not in conv {$conversationId}. Returning empty array."
            );
            return [];
        }
        error_log(
            "GET_CONV_MESSAGES: User IS participant. Proceeding to select messages."
        );
        $columns = [
            "messages.id",
            "messages.conversation_id",
            "messages.sender_id",
            "messages.content",
            "messages.message_type",
            "messages.metadata(metadata_json)",
            "messages.parent_message_id",
            "messages.sent_at",
            "messages.edited_at",
            "messages.is_unsent",
            "sender_table.username(sender_username)",
            "sender_table.full_name(sender_full_name)",
            "sender_table.profile_picture(sender_profile_picture)",
        ];
        $join_definition = ["[>]users(sender_table)" => ["sender_id" => "id"]];
        $where = [
            "messages.conversation_id" => $conversationId,
            "messages.is_unsent" => false,
            "LIMIT" => $limit,
        ];
        if ($afterMessageId) {
            $where["messages.id[>]"] = $afterMessageId;
            $where["ORDER"] = ["messages.id" => "ASC"];
        } elseif ($beforeMessageId) {
            $where["messages.id[<]"] = $beforeMessageId;
            $where["ORDER"] = ["messages.id" => "DESC"];
        } else {
            $where["ORDER"] = ["messages.id" => "DESC"];
        }
        error_log(
            "GET_CONV_MESSAGES: Attempting Medoo Select -> Table: 'messages', Joins: " .
                json_encode($join_definition) .
                ", Columns: " .
                json_encode($columns) .
                ", Where: " .
                json_encode($where)
        );
        $messagesData = [];
        try {
            $messagesData = $db->select(
                "messages",
                $join_definition,
                $columns,
                $where
            );
        } catch (\Throwable $e) {
            error_log(
                "GET_CONV_MESSAGES: EXCEPTION during select: " .
                    $e->getMessage() .
                    " Stack: " .
                    $e->getTraceAsString()
            );
            error_log(
                "GET_CONV_MESSAGES: Last query (if available from Medoo): " .
                    $db->last()
            );
            return null;
        }
        if ($messagesData === false) {
            $dbErrorArr = $db->error;
            $dbErrorMsg = "Unknown DB error during select execution.";
            if (is_array($dbErrorArr) && isset($dbErrorArr[2])) {
                $dbErrorMsg = $dbErrorArr[2];
            } elseif (is_array($dbErrorArr)) {
                $dbErrorMsg = "Raw error: " . json_encode($dbErrorArr);
            }
            error_log(
                "GET_CONV_MESSAGES: Select query returned false. DB Error: " .
                    $dbErrorMsg .
                    " Last query: " .
                    $db->last()
            );
            return null;
        }
        error_log(
            "GET_CONV_MESSAGES: Select successful. Row count: " .
                count($messagesData)
        );
        $processedMessages = [];
        foreach ($messagesData as $msg) {
            if (isset($msg["metadata_json"])) {
                $decodedMeta = json_decode($msg["metadata_json"], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $msg["metadata"] = $decodedMeta;
                } else {
                    error_log(
                        "GET_CONV_MESSAGES: JSON decode error for metadata of message ID {$msg["id"]}. Raw: " .
                            $msg["metadata_json"] .
                            " Error: " .
                            json_last_error_msg()
                    );
                    $msg["metadata"] = null;
                }
                unset($msg["metadata_json"]);
            } else {
                $msg["metadata"] = null;
            }
            $processedMessages[] = $msg;
        }
        if (!$afterMessageId) {
            return array_reverse($processedMessages);
        }
        return $processedMessages;
    }
    public function getUserConversationsApi()
    {
        $this->ensureDbReady();
        header("Content-Type: application/json");
        $limit = filter_input(INPUT_GET, "limit", FILTER_VALIDATE_INT) ?: 20;
        $offset = filter_input(INPUT_GET, "offset", FILTER_VALIDATE_INT) ?: 0;
        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit();
        }
        $conversations = $this->getUserConversations($limit, $offset);
        echo json_encode([
            "success" => true,
            "conversations" => $conversations,
        ]);
        exit();
    }
    private function getUserConversations(
        int $limit = 20,
        int $offset = 0
        ): array {
        $db = $this->db;
        if (!$this->currentUserId) {
            error_log("getUserConversations: currentUserId is null.");
            return [];
        }

        $columns = [
            "c.id(conversation_id)",
            "c.type(conversation_type)",
            "c.name(conversation_name)",
            "c.icon_url(raw_conversation_icon)",
            "c.updated_at(conversation_updated_at)",
            "lm.id(last_message_id)",
            "lm.content(last_message_content)",
            "lm.message_type(last_message_type)",
            "lm.metadata(last_message_metadata_json)",
            "lm.sent_at(last_message_sent_at)",
            "lm.sender_id(last_message_raw_sender_id)",
            "lms.full_name(last_message_raw_sender_full_name)",
            "lm.is_unsent(last_message_is_unsent)",
        ];

        $conversationsData = $db->select(
            "conversation_participants (cp)",
            [
                // THIS IS THE KEY CHANGE: Use INNER JOIN for conversations
                "[><]conversations (c)" => ["cp.conversation_id" => "id"],
                // These can remain LEFT JOINs
                "[>]messages (lm)" => ["c.last_message_id" => "id"],
                "[>]users (lms)" => ["lm.sender_id" => "id"],
            ],
            $columns,
            [
                "cp.user_id" => $this->currentUserId,
                "ORDER" => ["c.updated_at" => "DESC"],
                "LIMIT" => [$offset, $limit],
            ]
        );

        if ($conversationsData === false) {
            $dbErrorArr = $db->error;
            $dbErrorMsg = $dbErrorArr ? ($dbErrorArr[2] ?? json_encode($dbErrorArr)) : "Unknown DB error";
            error_log(
                "DB Error fetching user conversations for user {$this->currentUserId}: " .
                    $dbErrorMsg .
                    " Last Query: " .
                    $db->last()
            );
            return [];
        }

        foreach ($conversationsData as $index => $conv) {
            // --- Process Last Message Sender ---
            if (isset($conv["last_message_raw_sender_id"])) {
                if ((int) $conv["last_message_raw_sender_id"] === (int) $this->currentUserId) {
                    $conversationsData[$index]["last_message_sender_name"] = "You";
                } else {
                    $conversationsData[$index]["last_message_sender_name"] =
                        $conv["last_message_raw_sender_full_name"] ?: "User";
                }
            } else {
                $conversationsData[$index]["last_message_sender_name"] = null;
            }
            unset(
                $conversationsData[$index]["last_message_raw_sender_id"],
                $conversationsData[$index]["last_message_raw_sender_full_name"]
            );

            // --- Process Last Message Metadata ---
            if (!empty($conv["last_message_metadata_json"])) {
                $decodedMeta = json_decode($conv["last_message_metadata_json"], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $conversationsData[$index]["last_message_metadata"] = $decodedMeta;
                } else {
                    error_log(
                        "getUserConversations: JSON decode error for conv ID {$conv["conversation_id"]}. Raw: " .
                            $conv["last_message_metadata_json"]
                    );
                    $conversationsData[$index]["last_message_metadata"] = null;
                }
            } else {
                $conversationsData[$index]["last_message_metadata"] = null;
            }
            unset($conversationsData[$index]["last_message_metadata_json"]);

            // --- Initialize final conversation_icon and ensure conversation_name is set ---
            $finalConversationIcon = null;
            $currentConversationName = $conv["conversation_name"]; // Original name from DB

            // --- Process Direct Chats ---
            if ($conv["conversation_type"] === "direct") {
                $otherParticipant = $db->get(
                    "conversation_participants (op)",
                    ["[>]users (u)" => ["op.user_id" => "id"]],
                    ["op.user_id", "u.fullname AS full_name", "u.username", "u.profile_picture"],
                    [
                        "op.conversation_id" => $conv["conversation_id"],
                        "op.user_id[!]" => $this->currentUserId,
                    ]
                );

                if ($otherParticipant && is_array($otherParticipant)) {
                    $conversationsData[$index]["interlocutor"] = $otherParticipant;

                    // Set conversation_name if not already set for the direct chat
                    if (empty($currentConversationName)) {
                        $currentConversationName = $otherParticipant["full_name"] ??
                                                ($otherParticipant["username"] ?? "Chat User");
                    }

                    // Set icon: prioritize raw_conversation_icon if it's a real URL,
                    // then interlocutor's profile_picture, then let JS generate SVG.
                    if (!empty($conv["raw_conversation_icon"]) && preg_match('/^https?:\/\//i', $conv["raw_conversation_icon"])) {
                        $finalConversationIcon = $conv["raw_conversation_icon"];
                    } elseif (!empty($otherParticipant["profile_picture"]) && preg_match('/^https?:\/\//i', $otherParticipant["profile_picture"])) {
                        $finalConversationIcon = $otherParticipant["profile_picture"];
                    } else {
                        // If no real URLs, set to null so JS generates SVG based on interlocutor's name.
                        // We could also generate PHP SVG here if preferred for direct chats lacking pic.
                        // For now, aligning with "JS generates if no real URL"
                        $finalConversationIcon = null; // Let JS handle it for consistency
                        // OR if you want PHP to make an SVG for direct users without pics:
                        // $displayNameForAvatar = $otherParticipant["full_name"] ?? ($otherParticipant["username"] ?? "U");
                        // $finalConversationIcon = self::generateFallbackAvatar($displayNameForAvatar, 40);
                    }
                } else {
                    // Interlocutor not found for direct chat
                    if (empty($currentConversationName)) {
                        $currentConversationName = "Conversation";
                    }
                    $finalConversationIcon = null; // Let JS handle it
                    error_log(
                        "getUserConversations: Could not find valid interlocutor for direct conversation ID: " .
                            ($conv["conversation_id"] ?? "N/A")
                    );
                }
            } elseif ($conv["conversation_type"] === "group") {
                // For groups, prioritize raw_conversation_icon if it's a real URL.
                // Otherwise, set to null so JS generates SVG based on group name.
                if (!empty($conv["raw_conversation_icon"]) && preg_match('/^https?:\/\//i', $conv["raw_conversation_icon"])) {
                    $finalConversationIcon = $conv["raw_conversation_icon"];
                } else {
                    $finalConversationIcon = null; // Let JS generate SVG based on group name
                }
                // Ensure group has a name
                if (empty($currentConversationName)) {
                    $currentConversationName = "Group Chat";
                }
            }

            $conversationsData[$index]["conversation_name"] = $currentConversationName;
            $conversationsData[$index]["conversation_icon"] = $finalConversationIcon;
            unset($conversationsData[$index]["raw_conversation_icon"]); // Remove the raw unprocessed icon

            // --- Get Unread Count ---
            $conversationsData[$index]["unread_count"] =
                $this->getUnreadMessageCount((int) $conv["conversation_id"]);
        }
        return $conversationsData;
    }
    private function getUnreadMessageCount(int $conversationId): int
    {
        $db = $this->db;
        if (!$this->currentUserId) {
            error_log("getUnreadMessageCount: currentUserId is null.");
            return 0;
        }
        $participantData = $db->get(
            "conversation_participants",
            ["last_read_message_id"],
            [
                "AND" => [
                    "user_id" => $this->currentUserId,
                    "conversation_id" => $conversationId,
                ],
            ]
        );
        if ($db->error && $db->error[0] !== "00000") {
            error_log(
                "getUnreadMessageCount DB Error fetching participant data for conv {$conversationId}: " .
                    json_encode($db->error) .
                    " Last Query: " .
                    $db->last()
            );
        }
        if (
            !$participantData ||
            !isset($participantData["last_read_message_id"]) ||
            $participantData["last_read_message_id"] === null
        ) {
            $count = $db->count("messages", [
                "AND" => [
                    "conversation_id" => $conversationId,
                    "sender_id[!]" => $this->currentUserId,
                    "is_unsent" => false,
                ],
            ]);
            if ($db->error && $db->error[0] !== "00000") {
                error_log(
                    "getUnreadMessageCount DB Error counting all messages for conv {$conversationId}: " .
                        json_encode($db->error) .
                        " Last Query: " .
                        $db->last()
                );
            }
            return is_numeric($count) ? (int) $count : 0;
        }
        $lastReadMessageId = (int) $participantData["last_read_message_id"];
        $count = $db->count("messages", [
            "AND" => [
                "conversation_id" => $conversationId,
                "id[>]" => $lastReadMessageId,
                "sender_id[!]" => $this->currentUserId,
                "is_unsent" => false,
            ],
        ]);
        if ($db->error && $db->error[0] !== "00000") {
            error_log(
                "getUnreadMessageCount DB Error counting new messages for conv {$conversationId}: " .
                    json_encode($db->error) .
                    " Last Query: " .
                    $db->last()
            );
        }
        return is_numeric($count) ? (int) $count : 0;
    }

    public function markConversationAsReadApi()
    {
        $this->ensureDbReady();
        header("Content-Type: application/json");
        if (strtoupper($_SERVER["REQUEST_METHOD"]) !== "POST") {
            http_response_code(405);
            echo json_encode([
                "success" => false,
                "error" => "Method Not Allowed",
            ]);
            exit();
        }
        $input = json_decode(file_get_contents("php://input"), true);
        $conversationId = filter_var(
            $input["conversationId"] ?? null,
            FILTER_VALIDATE_INT
        );
        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit();
        }
        if (!$conversationId) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Missing conversationId.",
            ]);
            exit();
        }
        $success = $this->markConversationAsRead($conversationId);
        if ($success) {
            echo json_encode([
                "success" => true,
                "message" => "Conversation marked as read.",
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" =>
                    "Failed to mark conversation as read or no unread messages.",
            ]);
        }
        exit();
    }
    
    private function markConversationAsRead(int $conversationId): bool
    {
        $db = $this->db;
        if (!$this->currentUserId) {
            error_log("markConversationAsRead: currentUserId is null.");
            return false;
        }
        if (
            !$db->has("conversation_participants", [
                "AND" => [
                    "conversation_id" => $conversationId,
                    "user_id" => $this->currentUserId,
                ],
            ])
        ) {
            error_log(
                "markConversationAsRead: User {$this->currentUserId} not in conv {$conversationId}."
            );
            return false;
        }
        $latestMessageId = $db->max("messages", "id", [
            "AND" => [
                "conversation_id" => $conversationId,
                "is_unsent" => false,
            ],
        ]);
        if ($db->error && $db->error[0] !== "00000") {
            error_log(
                "markConversationAsRead DB Error fetching latest message ID for conv {$conversationId}: " .
                    json_encode($db->error) .
                    " Last Query: " .
                    $db->last()
            );
        }
        $updateData = ["last_read_at" => Medoo::raw("NOW()")];
        if (
            $latestMessageId !== null &&
            is_numeric($latestMessageId) &&
            $latestMessageId > 0
        ) {
            $updateData["last_read_message_id"] = (int) $latestMessageId;
        } else {
            error_log(
                "markConversationAsRead: No valid messages in conversation {$conversationId} to mark as read up to. Only updating last_read_at. LatestMsgId: " .
                    var_export($latestMessageId, true)
            );
        }
        $updateResult = $db->update("conversation_participants", $updateData, [
            "AND" => [
                "conversation_id" => $conversationId,
                "user_id" => $this->currentUserId,
            ],
        ]);
        if ($updateResult === false) {
            $dbErrorArr = $db->error;
            $dbErrorMsg = $dbErrorArr
                ? $dbErrorArr[2] ?? json_encode($dbErrorArr)
                : "Unknown DB error";
            error_log(
                "markConversationAsRead DB Error for conv {$conversationId}, user {$this->currentUserId}: " .
                    $dbErrorMsg .
                    " Last Query: " .
                    $db->last()
            );
            return false;
        }
        return true;
    }

    public function createGroupConversationApi() {
        $this->ensureDbReady();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $groupName = trim($input['groupName'] ?? '');
        $participantUserIds = isset($input['participantUserIds']) && is_array($input['participantUserIds'])
                            ? array_filter(array_map('intval', $input['participantUserIds']))
                            : [];
        $groupIconUrl = filter_var($input['groupIconUrl'] ?? null, FILTER_VALIDATE_URL) ?: null;

        if (!$this->currentUserId) {
             http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized.']); exit;
        }
        if (empty($groupName)) {
             http_response_code(400); echo json_encode(['success'=>false, 'error'=>'Group name is required.']); exit;
        }
        if (mb_strlen($groupName) > 100) {
            http_response_code(400); echo json_encode(['success'=>false, 'error'=>'Group name is too long (max 100 chars).']); exit;
        }
        if (empty($participantUserIds)) {
            // This means the creator is trying to create a group with no *other* participants initially.
            // If the intention is to allow groups with only the creator (who can add others later),
            // this check needs adjustment or to be removed if the system supports it.
            // For now, keeping it to require at least one *other* participant to align with typical UX.
            http_response_code(400); echo json_encode(['success'=>false, 'error'=>'At least one other participant is required for the group.']); exit;
        }

        // Remove the current user from the input $participantUserIds if they somehow included themselves
        // This ensures $participantUserIds only contains *other* members selected by the creator.
        $otherParticipantUserIds = array_values(array_diff($participantUserIds, [$this->currentUserId]));

        if (empty($otherParticipantUserIds)) {
             // This catches if the original $participantUserIds only contained the creator, or became empty after diff.
             http_response_code(400); echo json_encode(['success'=>false, 'error'=>'Cannot create a group with only yourself. Please add other participants.']); exit;
        }
        if (count($otherParticipantUserIds) > 49) { // Total participants = other + creator (1) = 50
            http_response_code(400); echo json_encode(['success'=>false, 'error'=>'Group has too many participants (max 50 allowed, including creator).']); exit;
        }

        // Pass only the *other* participants to createGroupConversation.
        // The method createGroupConversation itself will add $this->currentUserId.
        $creationResult = $this->createGroupConversation($groupName, $otherParticipantUserIds, $groupIconUrl);

        if (is_array($creationResult) && isset($creationResult['conversation_id'])) {
            // Success case - Notify all participants including the creator via WebSocket
            $allParticipantUserIdsForNotification = array_unique(array_merge([$this->currentUserId], $otherParticipantUserIds));
            $this->notifyWebSocketOfGroupCreation($creationResult, $allParticipantUserIdsForNotification);

            echo json_encode(['success' => true, 'conversation' => $creationResult]);
        } else if (is_string($creationResult)) { // Check if a string error message was returned
            http_response_code(500);
            error_log("CreateGroupAPI Error Detail (from createGroupConversation): " . $creationResult); // Log the specific error
            echo json_encode(['success' => false, 'error' => 'Failed to create group. Server error detail: ' . $creationResult]);
        } else {
            error_log("CreateGroupAPI Unknown Error: createGroupConversation returned non-array/non-string.");
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create group. An unknown error occurred.']);
        }
        exit;
    }

    /**
     * Creates a group conversation.
     * MODIFIED: Now returns the conversation array on success, or a string error message on failure.
     * Takes an array of *other* participant IDs (creator is added internally).
     */
    private function createGroupConversation(string $groupName, array $otherParticipantUserIds, ?string $groupIconUrl = null): array|string|null {
        $db = $this->db;

        if (!$this->currentUserId || !is_numeric($this->currentUserId) || $this->currentUserId <= 0) {
            error_log("CreateGroupConversation PRE-TXN ERROR: currentUserId is invalid. Value: " . var_export($this->currentUserId ?? 'NULL', true));
            return "Error: Creator's user ID is not properly set.";
        }

        $sanitizedOtherParticipantIds = array_values(array_unique(array_filter(array_map('intval', $otherParticipantUserIds))));
        $sanitizedOtherParticipantIds = array_diff($sanitizedOtherParticipantIds, [$this->currentUserId]);
        $allParticipantIdsIncludingCreator = array_unique(array_merge([$this->currentUserId], $sanitizedOtherParticipantIds));

        if (count($allParticipantIdsIncludingCreator) < 2) {
            return "Error: A group requires at least two distinct participants.";
        }
        if (count($allParticipantIdsIncludingCreator) > 50) {
            return "Error: Group participant limit exceeded.";
        }

        $finalGroupName = trim(mb_substr($groupName, 0, 100));
        $systemMessageCreatorName = $this->currentUserFullName ?: 'A user';
        $systemMessageContent = "{$systemMessageCreatorName} created the group \"{$this->sanitizeHtml($finalGroupName)}\".";
        $finalGroupIconUrl = ($groupIconUrl && filter_var($groupIconUrl, FILTER_VALIDATE_URL)) ? trim(mb_substr($groupIconUrl, 0, 2048)) : null;

        $finalInsertedConversationId = null; // To be set by reference

        $transactionOutcome = $db->action(function(Medoo $database) use (
            $finalGroupName, $finalGroupIconUrl, $allParticipantIdsIncludingCreator, $systemMessageContent,
            &$finalInsertedConversationId // Pass by reference
        ){
            if (!isset($this->currentUserId) || !is_numeric($this->currentUserId) || $this->currentUserId <= 0) {
                error_log("TXN CRITICAL FAILURE: currentUserId invalid INSIDE Medoo closure. Value: " . var_export($this->currentUserId ?? 'NULL', true));
                return false; 
            }

            $currentOpConversationId = null; 

            try {
                // 1. Insert Conversation
                $convData = [
                    "type" => "group", "name" => $finalGroupName, "created_by_user_id" => $this->currentUserId,
                    "updated_at" => Medoo::raw('NOW()'),
                    "icon_url" => $finalGroupIconUrl ?: static::generateFallbackAvatar($finalGroupName ?: 'G', 64)
                ];
                $convInsertStmt = $database->insert("conversations", $convData);
                if(!$convInsertStmt || $convInsertStmt->rowCount() === 0) {
                    error_log("TXN_FAILURE: Insert 'conversations' failed. Medoo Error: " . json_encode($database->errorInfo) . ". Last Query: " . $database->last());
                    return false;
                }
                $currentOpConversationId = (int)$database->id();
                if(!$currentOpConversationId || $currentOpConversationId <= 0) {
                    error_log("TXN_FAILURE: Invalid ID from 'conversations' insert. ID: " . var_export($currentOpConversationId, true) . ". Medoo Error: " . json_encode($database->errorInfo));
                    return false;
                }
                error_log("TXN_DEBUG: Conversation inserted. ID: {$currentOpConversationId}.");

                // 2. Insert Participants
                $participantInsertsData = [];
                foreach ($allParticipantIdsIncludingCreator as $uid) {
                    $participantInsertsData[] = [
                        "conversation_id" => $currentOpConversationId, "user_id" => (int)$uid,
                        "role" => ((int)$uid === (int)$this->currentUserId) ? 'admin' : 'member'
                    ];
                }
                $participantInsertStmt = $database->insert("conversation_participants", $participantInsertsData);
                if(!$participantInsertStmt || $participantInsertStmt->rowCount() !== count($allParticipantIdsIncludingCreator)) {
                    error_log("TXN_FAILURE: Insert 'participants' failed/partial. Expected: " . count($allParticipantIdsIncludingCreator) . ", Inserted: " . ($participantInsertStmt ? $participantInsertStmt->rowCount() : 'stmt_false') . ". Medoo Error: " . json_encode($database->errorInfo) . ". Last Query: " . $database->last());
                    return false;
                }
                error_log("TXN_DEBUG: Participants inserted for conv {$currentOpConversationId}. Count: " . $participantInsertStmt->rowCount());

                // 3. Insert System Message
                $systemMessageData = [
                    "conversation_id" => $currentOpConversationId, "sender_id" => $this->currentUserId,
                    "content" => $systemMessageContent, "message_type" => "system_group_created",
                    "sent_at" => Medoo::raw('NOW()')
                ];
                $sysMsgInsertStmt = $database->insert("messages", $systemMessageData);
                $systemMessageId = null;
                if(!$sysMsgInsertStmt || $sysMsgInsertStmt->rowCount() === 0) {
                    error_log("TXN_WARNING (non-fatal): Failed to insert system_group_created message for conv {$currentOpConversationId}. Medoo Error: " . json_encode($database->errorInfo) . ". Last Query: " . $database->last());
                } else {
                    $systemMessageId = (int)$database->id();
                    if (!$systemMessageId || $systemMessageId <= 0) {
                        error_log("TXN_WARNING (non-fatal): Failed to get valid ID for system message. ID: " . var_export($systemMessageId, true));
                        $systemMessageId = null; 
                    } else {
                         error_log("TXN_DEBUG: System message inserted for conv {$currentOpConversationId}. ID: {$systemMessageId}.");
                    }
                }

                // 4. Update Last Message ID
                if ($systemMessageId && $systemMessageId > 0) {
                    $updateLastMsgStmt = $database->update("conversations",
                        ["last_message_id" => $systemMessageId],
                        ["id" => $currentOpConversationId]
                    );
                    if ($updateLastMsgStmt === false || $updateLastMsgStmt->rowCount() === 0) {
                         error_log("TXN_WARNING (non-fatal): Failed to update or no rows affected for conversations.last_message_id for conv {$currentOpConversationId}. Medoo Error: " . json_encode($database->errorInfo) . ". Last Query: " . $database->last());
                    } else {
                        error_log("TXN_DEBUG: Updated last_message_id for conv {$currentOpConversationId} successfully.");
                    }
                }
                
                // Set the referenced variable
                $finalInsertedConversationId = $currentOpConversationId;
                // Log before returning true
                error_log("TXN_DEBUG_PRE_RETURN: Success in closure. Setting finalInsertedConversationId to: {$finalInsertedConversationId}. Returning true.");
                return true; // Signal Medoo::action to COMMIT

            } catch (\PDOException $e) {
                error_log("TXN_PDO_EXCEPTION: " . $e->getMessage() . " Query: " . ($database->last() ?? 'N/A') . " DB Error: " . json_encode($database->errorInfo));
                return false; // Signal Medoo::action to ROLLBACK
            } catch (\Throwable $e) {
                error_log("TXN_GENERAL_EXCEPTION (" . get_class($e) . "): " . $e->getMessage() . " Query: " . ($database->last() ?? 'N/A') . " DB Error: " . json_encode($database->errorInfo));
                return false; // Signal Medoo::action to ROLLBACK
            }
        });

        error_log("TRANSACTION_OUTCOME_FROM_ACTION: " . var_export($transactionOutcome, true));
        error_log("FINAL_INSERTED_CONVERSATION_ID_AFTER_ACTION: " . var_export($finalInsertedConversationId, true));

        if ($transactionOutcome !== false && is_numeric($finalInsertedConversationId) && $finalInsertedConversationId > 0) {
            $conversationId = (int) $finalInsertedConversationId;
            if ($transactionOutcome === null) {
                error_log("CreateGroupConversation WARNING: Medoo::action() returned NULL but a valid conversation ID ({$conversationId}) was obtained via reference. Proceeding as success. This might indicate an unusual behavior in Medoo::action() return values.");
            } else { // $transactionOutcome was true
                 error_log("CreateGroupConversation SUCCESS_TXN (action returned true): Group created with ID {$conversationId}. Fetching details...");
            }
            
            $newGroupConversation = $this->db->get("conversations", [
                "id(conversation_id)", "type(conversation_type)", "name(conversation_name)",
                "icon_url(conversation_icon)", "created_by_user_id", "created_at",
                "updated_at", "last_message_id"
            ], ["id" => $conversationId]);

            if ($newGroupConversation) {
                return $newGroupConversation;
            } else {
                 $fetchErrorArray = $this->db->errorInfo;
                 $fetchError = $fetchErrorArray ? ($fetchErrorArray[2] ?? json_encode($fetchErrorArray)) : "Unknown fetch error";
                 error_log("CreateGroupConversation ERROR POST-TXN: Group transaction appears successful (ID: {$conversationId}) BUT FAILED TO FETCH details. DB: {$fetchError}");
                 return "Error: Group transaction successful (ID: {$conversationId}) but failed to fetch details. DB error: " . $fetchError;
            }
        } else {
            $finalErrorDetail = "Group creation transaction failed.";
             if ($transactionOutcome === false) { 
                $finalErrorDetail .= " (The transaction was rolled back. Check logs for errors like TXN_FAILURE, TXN_PDO_EXCEPTION, or TXN_GENERAL_EXCEPTION inside the transaction block).";
             } else { 
                $finalErrorDetail .= " (Transaction outcome was not an explicit rollback, but failed to retrieve a valid conversation ID. Outcome from action(): " . var_export($transactionOutcome, true) . ", Captured ID by reference: " . var_export($finalInsertedConversationId, true) . ").";
             }
            $lastDbErrorArray = $this->db->errorInfo;
            if($lastDbErrorArray && ($lastDbErrorArray[0] !== "00000" || (isset($lastDbErrorArray[2]) && $lastDbErrorArray[2] !== null))) {
                $finalErrorDetail .= " Last DB Error on main connection: " . ($lastDbErrorArray[2] ?? json_encode($lastDbErrorArray));
            }
            error_log("CreateGroupConversation OVERALL_FAILURE_DETAIL: " . $finalErrorDetail);
            return $finalErrorDetail;
        }
    }

    public static function generateFallbackAvatar(string $name, int $size = 32): string {
        $initial = "?";
        $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $nameParts = explode(" ", $trimmedName);
            if (count($nameParts) >= 2) {
                $firstInitial = strtoupper(mb_substr($nameParts[0], 0, 1));
                $lastInitial = strtoupper(mb_substr(end($nameParts), 0, 1));
                $initial = $firstInitial . $lastInitial;
            } elseif (count($nameParts) === 1 && mb_strlen($nameParts[0]) > 0) {
                $initial = strtoupper(mb_substr($nameParts[0], 0, min(2, mb_strlen($nameParts[0]))));
            }
            if (empty(trim($initial)) || $initial === "??") {
                $initial = strtoupper(mb_substr($trimmedName, 0, 1)) ?: "?";
            }
            if (empty(trim($initial))) $initial = "?";
        }
        $hueSeed = crc32(strtolower($trimmedName ?: "fallback"));
        $hue = $hueSeed % 360;
        $bgColor = "hsl({$hue}, 70%, 85%)";
        $textColor = "hsl({$hue}, 50%, 35%)";
        $fontSize = mb_strlen($initial) > 1 ? "40" : "50";
        $safeInitial = htmlspecialchars($initial, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="%s%%" fill="%s" font-weight="bold">%s</text></svg>',
            $size, $size, htmlspecialchars($bgColor, ENT_QUOTES, "UTF-8"), $fontSize, htmlspecialchars($textColor, ENT_QUOTES, "UTF-8"), $safeInitial
        );
        return "data:image/svg+xml;charset=utf-8;base64," . base64_encode($svg);
    }

    // Ensure you have these helper methods in your ChatController or a base class
    private function sanitizeHtml(string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    protected function notifyWebSocketOfGroupCreation(
        array $groupConversationData,
        array $allParticipantUserIds
        ) {
        if (!extension_loaded("zmq")) {
            error_log(
                "ChatController ZMQ Push (GroupCreate) Error: ZMQ PHP extension is NOT loaded."
            );
            return;
        }
        error_log(
            "ChatController ZMQ Push (GroupCreate): ZMQ extension IS loaded."
        );

        if (empty($allParticipantUserIds)) {
            error_log(
                "ChatController ZMQ Push (GroupCreate): No participants to notify."
            );
            return;
        }
        // Ensure all IDs are stringified for consistency and potential future uses where IDs might not be purely numeric
        $allParticipantUserIds = array_map('strval', array_unique(array_filter($allParticipantUserIds, 'is_numeric')));

        error_log(
            "ChatController ZMQ Push (GroupCreate): Preparing to notify participants: " .
                json_encode($allParticipantUserIds) .
                " for conversation ID: " . ($groupConversationData['conversation_id'] ?? 'N/A')
        );

        $pusherSocket = null;
        try {
            $zmqDsn =
                getenv("ZMQ_WEBSOCKET_PUSH_DSN") ?: "tcp://127.0.0.1:5555";
            $zmqContext = new \ZMQContext();
            // Use a unique PUSH identity if running multiple workers, or keep simple if single pusher.
            $pusherSocket = $zmqContext->getSocket(
                \ZMQ::SOCKET_PUSH,
                "chatPusher_GroupCreate_" . uniqid() // More specific identity
            );
            if (!$pusherSocket) {
                error_log(
                    "ChatController ZMQ Push (GroupCreate) Error: Failed to create ZMQ PUSH socket object."
                );
                return;
            }
            // Set LINGER to 0 (don't wait on close) or a small timeout like 250ms
            $pusherSocket->setSockOpt(\ZMQ::SOCKOPT_LINGER, 250); // Try to send for up to 250ms

            // Connect call for PUSH is often non-blocking and "succeeds" immediately,
            // actual send issues might appear on send() if the PULL endpoint isn't ready.
            $connectSuccess = $pusherSocket->connect($zmqDsn);
            error_log(
                "ChatController ZMQ Push (GroupCreate): PUSH socket attempting to connect to DSN: {$zmqDsn}. Connect call result: " . ($connectSuccess ? 'true' : 'false')
            );

            // Payload for the WebSocket server, which it will then distribute.
            // This payload IS what the *client* will receive from the WebSocket server,
            // wrapped in the 'event'/'data' structure by ChatMessageHandler.
            $payloadForWebSocketClient = [
                'action' => 'system_group_created', // This is the client-side expected 'event' name.
                'conversation' => $groupConversationData, // The data of the newly created group.
            ];

            foreach ($allParticipantUserIds as $recipientUserId) {
                $messageToPushToZMQ = [
                    "type" => "broadcast_system_event",     // This tells ChatMessageHandler what kind of ZMQ message this is
                    "recipientUserId" => (string) $recipientUserId, // Tells WSS which user connection(s) to target
                    "payload" => $payloadForWebSocketClient, // This is the data for the client
                ];
                $jsonData = json_encode($messageToPushToZMQ);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("ChatController ZMQ Push (GroupCreate) Error: JSON encode failed for user {$recipientUserId}. Error: " . json_last_error_msg() . " Data: " . print_r($messageToPushToZMQ, true));
                    continue; // Skip this recipient
                }

                error_log(
                    "ChatController ZMQ Push (GroupCreate): Sending to user {$recipientUserId} (ZMQ): {$jsonData}"
                );
                // The send call for PUSH might be non-blocking.
                $bytesSent = $pusherSocket->send($jsonData); // No flags usually needed for PUSH

                if ($bytesSent === false) {
                    // This can happen if ZMQ internal buffers are full, or PULL is not connected/accepting.
                    $zmqErrNo = $pusherSocket->geterrcode(); // ZMQ constant might be needed
                    $zmqErrMsg = $pusherSocket->geterrormsg(); // Actual zmq_strerror() result
                    error_log(
                        "ChatController ZMQ Push (GroupCreate) Error: pusherSocket->send() returned false for user {$recipientUserId}. ZMQ Error [{$zmqErrNo}]: {$zmqErrMsg}"
                    );
                } else {
                    error_log(
                        "ChatController ZMQ Push (GroupCreate): Successfully queued data to ZMQ for user {$recipientUserId}. Bytes potentially sent/queued: {$bytesSent}."
                    );
                }
            }
        } catch (\ZMQException $e) {
            $zmqErrorCode = $e->getCode();
            error_log(
                "ChatController ZMQException (GroupCreate): " .
                    $e->getMessage() .
                    " (Code: {$zmqErrorCode})."
            );
        } catch (\Throwable $e) {
            error_log(
                "ChatController General Exception during ZMQ push (GroupCreate): " .
                    $e->getMessage() . "\nTrace: " . $e->getTraceAsString()
            );
        } finally {
            // If you want to explicitly attempt to disconnect:
            if ($pusherSocket instanceof \ZMQSocket) {
                try {
                    // Optional: Attempt to disconnect the PUSH socket
                    // $pusherSocket->disconnect($zmqDsn); // $zmqDsn needs to be in scope or passed
                    // error_log("ChatController ZMQ Push: Disconnected pusher socket.");
                } catch (\ZMQException $e) {
                    // error_log("ChatController ZMQ Push: ZMQException during explicit disconnect: " . $e->getMessage());
                } catch (\Throwable $e) {
                    // error_log("ChatController ZMQ Push: Throwable during explicit disconnect: " . $e->getMessage());
                }
            }
            // Otherwise, just an empty finally block is fine, or a log message
            // error_log("ChatController ZMQ Push: Reached finally block.");
        }
    }

    public function apiSearchUsers()
    {
        $this->ensureDbReady();
        header("Content-Type: application/json");
        $query = trim($_GET["q"] ?? "");

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit();
        }
        if (empty($query) || mb_strlen($query) < 1) {
            echo json_encode(["success" => true, "users" => []]);
            exit();
        }

        $db = $this->db;
        $searchPattern = "%" . (string) $query . "%";
        $currentUserIdToExclude = $this->currentUserId;

        $where = [
            "status" => "active",
            "OR" => [
                "full_name[~]" => $searchPattern,
                "username[~]" => $searchPattern,
            ],
            "LIMIT" => 10,
        ];
        // For group creation, you might want to exclude the current user from results.
        // If this apiSearchUsers is *only* for group creation, add the exclusion.
        // If it's a general search, this exclusion might not be desired.
        // Based on the JavaScript, UserTypeahead has a different endpoint for group search,
        // so this global search might not need the exclusion.
        // For now, let's assume this is a general search. If you want to exclude self:
        // if ($currentUserIdToExclude !== null) {
        //     $where["id[!]"] = $currentUserIdToExclude;
        // }

        $usersData = $db->select(
            "users",
            ["id", "username", "full_name", "profile_picture"],
            $where
        );

        if ($usersData === false) {
            $dbErrorArr = $db->error;
            $dbErrorMsg = $dbErrorArr
                ? $dbErrorArr[2] ?? json_encode($dbErrorArr)
                : "Database error during user search.";
            error_log(
                "apiSearchUsers DB Error: " .
                    $dbErrorMsg .
                    " Last Query: " .
                    $db->last()
            );
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $dbErrorMsg]);
            exit();
        }
        $results = [];
        foreach ($usersData as $user) {
            $displayName = !empty($user["full_name"])
                ? $user["full_name"]
                : $user["username"];
            $results[] = [
                "id" => (int) $user["id"],
                "name" => $this->sanitizeHtml($displayName), // 'name' key as expected by UserTypeahead
                "username" => $this->sanitizeHtml($user["username"]),
                "avatar" =>
                    $user["profile_picture"] ?:
                    $this->generateFallbackAvatar($displayName, 32), // 'avatar' key
            ];
        }
        echo json_encode(["success" => true, "users" => $results]); // 'users' key as expected
        exit();
    }

    public function addUsersToGroupApi()
    {
        $this->ensureDbReady();
        http_response_code(501);
        echo json_encode(["success" => false, "error" => "Not Implemented"]);
        exit();
    }
    public function removeUserFromGroupApi()
    {
        $this->ensureDbReady();
        http_response_code(501);
        echo json_encode(["success" => false, "error" => "Not Implemented"]);
        exit();
    }
    public function promoteUserToAdminApi()
    {
        $this->ensureDbReady();
        http_response_code(501);
        echo json_encode(["success" => false, "error" => "Not Implemented"]);
        exit();
    }
    public function leaveGroupApi()
    {
        $this->ensureDbReady();
        http_response_code(501);
        echo json_encode(["success" => false, "error" => "Not Implemented"]);
        exit();
    }
}