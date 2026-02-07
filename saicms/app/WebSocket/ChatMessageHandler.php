<?php

namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ZMQContext;
use Medoo\Medoo;
use Dotenv\Dotenv;

class ChatMessageHandler implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    /** @var \SplObjectStorage[] Array mapping string userId to SplObjectStorage of ConnectionInterface */
    protected array $userConnections;
    protected LoopInterface $loop;
    protected ?string $zmqPullAddress;
    protected ?Medoo $db = null;

    public function __construct(
        LoopInterface $loop,
        ?string $zmqListenAddress = null
    ) {
        $this->clients = new \SplObjectStorage();
        $this->userConnections = [];
        $this->loop = $loop;
        $this->log("ChatMessageHandler Initialized.");
        $this->initializeDb();

        if (
            $zmqListenAddress &&
            extension_loaded("zmq") &&
            class_exists(\React\ZMQ\Context::class)
        ) {
            $this->zmqPullAddress = $zmqListenAddress;
            $this->setupZMQListener();
        } elseif ($zmqListenAddress && !extension_loaded("zmq")) {
            $this->log(
                "WARNING: ZMQ DSN provided but ZMQ PHP extension not loaded. ZMQ listener NOT started."
            );
        } elseif (
            $zmqListenAddress &&
            !class_exists(\React\ZMQ\Context::class)
        ) {
            $this->log(
                "WARNING: react/zmq library likely not installed or autoloadable. ZMQ PULL socket cannot be initialized."
            );
        } else {
            $this->log(
                "Info: No ZMQ DSN provided, or ZMQ extension not loaded/library missing. ZMQ listener skipped."
            );
        }
    }

    private function initializeDb()
    {
        $this->log(
            "Attempting to initialize database connection for WebSocket server..."
        );
        try {
            $projectRoot = dirname(__DIR__, 2);
            // $this->log("Project root for .env: " . $projectRoot); // Verbose, can be removed

            if (file_exists($projectRoot . "/.env")) {
                // $this->log(".env file found at {$projectRoot}/.env. Loading environment variables..."); // Verbose
                Dotenv::createImmutable($projectRoot)->load();

                foreach ($_ENV as $key => $value) {
                    // Generally not needed with modern phpdotenv
                    putenv("$key=$value");
                }
            } else {
                $this->log(
                    "ERROR: .env file not found at {$projectRoot}/.env. Database credentials cannot be loaded. DB will not be available."
                );
                $this->db = null;
                return;
            }

            $dbHost = getenv("DB_HOST");
            $dbName = getenv("DB_NAME");
            $dbUser = getenv("DB_USER");
            $dbPass = getenv("DB_PASS");
            $dbPort = getenv("DB_PORT") ?: 3306;
            $dbCharset = getenv("DB_CHARSET") ?: "utf8mb4";

            // $this->log("DB Config from getenv(): Host={$dbHost}, Name={$dbName}, User={$dbUser}, Port={$dbPort}, Charset={$dbCharset}"); // Verbose
            if ($dbPass === false || $dbPass === null || $dbPass === "") {
                $this->log(
                    "WARNING: DB_PASS is not set or is empty in environment variables."
                );
            }

            if (!$dbHost || !$dbName || !$dbUser) {
                $this->log(
                    "ERROR: Missing one or more required DB credentials from environment (DB_HOST, DB_NAME, DB_USER). DB will not be available."
                );
                $this->db = null;
                return;
            }

            // $this->log("Attempting new Medoo instance..."); // Verbose
            $this->db = new Medoo([
                "type" => "mysql",
                "host" => $dbHost,
                "database" => $dbName,
                "username" => $dbUser,
                "password" => $dbPass,
                "port" => (int) $dbPort,
                "charset" => $dbCharset,
                "collation" => "utf8mb4_unicode_ci",
                "option" => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
                "error" => \PDO::ERRMODE_EXCEPTION,
            ]);
            // $this->log("Medoo instance created. Testing database connection..."); // Verbose

            $testResult = $this->db
                ->query("SELECT 1 AS test_column")
                ->fetchAll();
            if (
                $testResult &&
                isset($testResult[0]["test_column"]) &&
                $testResult[0]["test_column"] == 1
            ) {
                $this->log(
                    "Database connection test successful (SELECT 1 worked)."
                );
            } else {
                $this->log(
                    "WARNING: Database connection test query did not return expected result. DB might be partially available or query failed silently. Last query: " .
                        $this->db->last()
                );
            }
        } catch (\PDOException $e) {
            $this->log(
                "FATAL PDOException during DB initialization: " .
                    $e->getMessage() .
                    " (Code: " .
                    $e->getCode() .
                    ")"
            );
            $this->db = null;
        } catch (\Throwable $e) {
            $this->log(
                "FATAL Throwable (" .
                    get_class($e) .
                    ") during DB initialization: " .
                    $e->getMessage()
            );
            $this->db = null;
        }

        if ($this->db) {
            $this->log(
                "initializeDb finished successfully. Database connection is available."
            );
        } else {
            $this->log(
                "initializeDb finished. Database connection FAILED or was not established. \$this->db is null."
            );
        }
    }

    protected function setupZMQListener()
    {
        $context = new ZMQContext($this->loop);
        $pullSocket = $context->getSocket(\ZMQ::SOCKET_PULL);

        try {
            $pullSocket->bind($this->zmqPullAddress);
            $this->log("ZMQ PULL socket listening on {$this->zmqPullAddress}");

            $pullSocket->on("message", function ($jsonMessage) {
                $this->log("[ZMQ Received]: " . $jsonMessage);
                $messageData = json_decode($jsonMessage, true);

                if ($messageData && isset($messageData["type"])) {
                    $this->log("[ZMQ Decoded]: Type='{$messageData["type"]}', RecipientTargeted='{$messageData["recipientUserId"]}', PayloadMessageType='" . ($messageData["payload"]["message_type"] ?? ($messageData["payload"]["action"] ?? 'N/A')) . "'");
                    switch ($messageData["type"]) {
                        case "broadcast_new_message":
                            if (
                                isset(
                                    $messageData["recipientUserId"],
                                    $messageData["payload"]
                                )
                            ) {
                                $this->_sendMessageToUser(
                                    (string) $messageData["recipientUserId"],
                                    // The client-side ChatUIManager expects an 'event' and 'data' structure for new messages
                                    // The $messageData['payload'] from ZMQ *is* the full message object.
                                    [
                                        "event" => "incoming_chat_message",
                                        "data" => $messageData["payload"],
                                    ]
                                );
                            } else {
                                $this->log(
                                    "[ZMQ Error] Missing recipientUserId or payload for broadcast_new_message"
                                );
                            }
                            break;

                        // MODIFIED: Added handler for system events like group creation
                        case "broadcast_system_event":
                            if (
                                isset(
                                    $messageData["recipientUserId"],
                                    $messageData["payload"]
                                ) &&
                                isset($messageData["payload"]["action"])
                            ) {
                                // 'action' key from ChatController

                                $clientEventName =
                                    $messageData["payload"]["action"]; // e.g., 'system_group_created'
                                $clientEventData = $messageData["payload"]; // The full payload {action: ..., conversation: ...}

                                // We'll wrap this in the standard 'event'/'data' structure for the client
                                $this->_sendMessageToUser(
                                    (string) $messageData["recipientUserId"],
                                    [
                                        "event" => $clientEventName,
                                        "data" => $clientEventData,
                                    ]
                                );
                                $this->log(
                                    "[ZMQ Handling] Relayed system event '{$clientEventName}' to user " .
                                        $messageData["recipientUserId"]
                                );
                            } else {
                                $this->log(
                                    "[ZMQ Error] Missing fields for broadcast_system_event. Data: " .
                                        json_encode($messageData)
                                );
                            }
                            break;

                        default:
                            $this->log(
                                "[ZMQ Error] Unknown message type from ZMQ: {$messageData["type"]}"
                            );
                    }
                } else {
                    $this->log(
                        "[ZMQ Error] Invalid JSON or message type missing in ZMQ message: {$jsonMessage}"
                    );
                }
            });

            $pullSocket->on("error", function (\Exception $e) {
                $this->log(
                    "[ZMQ Error] PULL socket error: " . $e->getMessage()
                );
            });
        } catch (\ZMQSocketException $e) {
            $this->log(
                "[ZMQ FATAL] Could not bind PULL socket to {$this->zmqPullAddress}: " .
                    $e->getMessage()
            );
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn, null);
        $this->log("New connection opened: ID {$conn->resourceId}");
        $conn->send(
            json_encode([
                "event" => "connection_established",
                "data" => [
                    "message" => "Welcome! Please send your identification.",
                ],
            ])
        );
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->log("RAW MESSAGE RECEIVED from {$from->resourceId}: " . $msg);
        $data = json_decode($msg, true);

        if (!$data || !isset($data["action"])) {
            $this->log(
                "Invalid JSON or missing action from {$from->resourceId}. Error: " .
                    json_last_error_msg() .
                    ". Message: " .
                    $msg
            );
            $from->send(
                json_encode([
                    "event" => "message_format_error",
                    "data" => ["message" => "Invalid JSON or action missing."],
                ])
            );
            return;
        }

        if ($data["action"] === "client_identify") {
            if (
                isset($data["authUserId"]) &&
                !empty(trim((string) $data["authUserId"]))
            ) {
                $authUserId = (string) $data["authUserId"];
                $oldUserId = $this->clients[$from] ?? null;
                if ($oldUserId && isset($this->userConnections[$oldUserId])) {
                    $this->userConnections[$oldUserId]->detach($from);
                    if (count($this->userConnections[$oldUserId]) === 0) {
                        unset($this->userConnections[$oldUserId]);
                    }
                }
                $this->clients->offsetSet($from, $authUserId);
                if (!isset($this->userConnections[$authUserId])) {
                    $this->userConnections[
                        $authUserId
                    ] = new \SplObjectStorage();
                }
                $this->userConnections[$authUserId]->attach($from);
                $this->log(
                    "Client {$from->resourceId} identified as User ID: {$authUserId}"
                );
                $from->send(
                    json_encode([
                        "event" => "user_identified",
                        "data" => [
                            "status" => "success",
                            "userId" => $authUserId,
                        ],
                    ])
                );
            } else {
                $this->log(
                    "Client {$from->resourceId} identification failed: authUserId missing or empty."
                );
                $from->send(
                    json_encode([
                        "event" => "identification_error",
                        "data" => [
                            "status" => "failed",
                            "message" => "authUserId missing or invalid.",
                        ],
                    ])
                );
            }
            return;
        }

        $currentUserId = $this->clients[$from] ?? null;
        if (!$currentUserId) {
            $this->log(
                "Action '{$data["action"]}' from unidentified client {$from->resourceId}. Ignoring."
            );
            $from->send(
                json_encode([
                    "event" => "identification_error",
                    "data" => [
                        "message" =>
                            "Client not identified. Please send client_identify first.",
                    ],
                ])
            );
            return;
        }

        $conversationId = isset($data["conversationId"])
            ? (int) $data["conversationId"]
            : null;

        switch ($data["action"]) {
            case "typing_status":
                if (
                    $conversationId &&
                    isset($data["userId"]) &&
                    isset($data["isTyping"])
                ) {
                    $typingUserId = (string) $data["userId"];
                    $isTyping = (bool) $data["isTyping"];
                    if ($typingUserId !== $currentUserId) {
                        $this->log(
                            "SECURITY: User {$currentUserId} attempted to send typing status for {$typingUserId}. Ignoring."
                        );
                        return;
                    }
                    $userInfo = $this->_getUserInfo($typingUserId);
                    if (!$userInfo) {
                        $this->log(
                            "Typing status from unknown user ID: {$typingUserId}"
                        );
                        return;
                    }
                    $isGroup = isset($data["isGroup"])
                        ? (bool) $data["isGroup"]
                        : false; // Get isGroup from client payload
                    $payloadToBroadcast = [
                        "event" => "user_typing_status",
                        "data" => [
                            "conversationId" => $conversationId,
                            "userId" => $typingUserId,
                            "userName" =>
                                trim($userInfo["full_name"] ?? "") ?:
                                $userInfo["username"] ?? "Unknown User",
                            "userAvatar" =>
                                $userInfo["profile_picture"] ?? null,
                            "isTyping" => $isTyping,
                            "isGroup" => $isGroup, // Forward isGroup info
                        ],
                    ];
                    $this->_broadcastToConversation(
                        $conversationId,
                        $payloadToBroadcast,
                        $from
                    );
                } else {
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" =>
                                    "typing_status missing required fields.",
                            ],
                        ])
                    );
                }
                break;

            case "webrtc_offer":
                if ($conversationId && isset($data["targetUserId"])) {
                    $targetUserId = (string) $data["targetUserId"];
                    $relayPayload = [
                        "event" => $data["action"],
                        "data" => $data,
                    ];
                    $callerInfo = $this->_getUserInfo($currentUserId);
                    $finalCallerName = "User " . $currentUserId;
                    $finalCallerAvatar = null;
                    if ($callerInfo) {
                        $dbFullName = trim($callerInfo["full_name"] ?? "");
                        $dbUsername = trim($callerInfo["username"] ?? "");
                        if (!empty($dbFullName)) {
                            $finalCallerName = $dbFullName;
                        } elseif (!empty($dbUsername)) {
                            $finalCallerName = $dbUsername;
                        }
                        $finalCallerAvatar =
                            $callerInfo["profile_picture"] ?? null;
                    }
                    $relayPayload["data"]["callerUserId"] = $currentUserId;
                    $relayPayload["data"]["callerUserName"] = $finalCallerName;
                    $relayPayload["data"][
                        "callerUserAvatar"
                    ] = $finalCallerAvatar;
                    $this->log(
                        "WebRTC offer from user {$currentUserId} to {$targetUserId}. Name: '{$finalCallerName}'."
                    );
                    $this->_saveAndBroadcastSystemMessage(
                        $conversationId,
                        $currentUserId,
                        "{$finalCallerName} started a call.",
                        [
                            "eventType" => "call_initiated",
                            "callerId" => $currentUserId,
                            "callerName" => $finalCallerName,
                            "targetId" => $targetUserId,
                            "timestamp_event" => date("Y-m-d H:i:s"),
                        ]
                    );
                    $this->_sendMessageToUser($targetUserId, $relayPayload);
                } else {
                    $this->log(
                        "WebRTC action '{$data["action"]}' missing conversationId or targetUserId. Data: " .
                            json_encode($data)
                    );
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" => "{$data["action"]} requires conversationId and targetUserId.",
                            ],
                        ])
                    );
                }
                break;

            case "webrtc_answer":
                if ($conversationId && isset($data["targetUserId"])) {
                    $targetUserId = (string) $data["targetUserId"];
                    $answererInfo = $this->_getUserInfo($currentUserId);
                    $answererName =
                        trim($answererInfo["full_name"] ?? "") ?:
                        $answererInfo["username"] ?? "User " . $currentUserId;
                    $this->_sendMessageToUser($targetUserId, [
                        "event" => $data["action"],
                        "data" => $data,
                    ]);
                    $this->_saveAndBroadcastSystemMessage(
                        $conversationId,
                        $currentUserId,
                        "Call answered. Call is now active.",
                        [
                            "eventType" => "call_accepted_active",
                            "answererId" => $currentUserId,
                            "answererName" => $answererName,
                            "callerId" => $targetUserId,
                            "timestamp_event" => date("Y-m-d H:i:s"),
                        ]
                    );
                } else {
                    $this->log(
                        "WebRTC action '{$data["action"]}' missing conversationId or targetUserId. Data: " .
                            json_encode($data)
                    );
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" => "{$data["action"]} requires conversationId and targetUserId.",
                            ],
                        ])
                    );
                }
                break;

            case "webrtc_ice_candidate":
                if (isset($data["targetUserId"])) {
                    $this->_sendMessageToUser((string) $data["targetUserId"], [
                        "event" => $data["action"],
                        "data" => $data,
                    ]);
                } else {
                    $this->log(
                        "WebRTC action '{$data["action"]}' missing targetUserId. Data: " .
                            json_encode($data)
                    );
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" => "{$data["action"]} requires targetUserId.",
                            ],
                        ])
                    );
                }
                break;

            case "webrtc_call_rejected":
                if ($conversationId && isset($data["targetUserId"])) {
                    $targetUserId = (string) $data["targetUserId"];
                    $rejectingUserInfo = $this->_getUserInfo($currentUserId);
                    $rejectingUserName =
                        trim($rejectingUserInfo["full_name"] ?? "") ?:
                        $rejectingUserInfo["username"] ??
                            "User " . $currentUserId;
                    $reason = $data["reason"] ?? "Call declined";
                    $this->_sendMessageToUser($targetUserId, [
                        "event" => $data["action"],
                        "data" => $data,
                    ]);
                    $this->_saveAndBroadcastSystemMessage(
                        $conversationId,
                        $currentUserId,
                        "{$rejectingUserName} declined the call.",
                        [
                            "eventType" => "call_declined",
                            "reason" => $reason,
                            "rejecterId" => $currentUserId,
                            "rejecterName" => $rejectingUserName,
                            "callerId" => $targetUserId,
                            "timestamp_event" => date("Y-m-d H:i:s"),
                        ]
                    );
                } else {
                    $this->log(
                        "WebRTC action '{$data["action"]}' missing conversationId or targetUserId. Data: " .
                            json_encode($data)
                    );
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" => "{$data["action"]} requires conversationId and targetUserId.",
                            ],
                        ])
                    );
                }
                break;

            case "webrtc_call_hangup":
                if ($conversationId && isset($data["targetUserId"])) {
                    $targetUserId = (string) $data["targetUserId"];
                    $hangingUpUserInfo = $this->_getUserInfo($currentUserId);
                    $hangingUpUserName =
                        trim($hangingUpUserInfo["full_name"] ?? "") ?:
                        $hangingUpUserInfo["username"] ??
                            "User " . $currentUserId;
                    $reason = $data["reason"] ?? "Call ended";
                    $this->_sendMessageToUser($targetUserId, [
                        "event" => $data["action"],
                        "data" => $data,
                    ]);
                    $callDuration = isset($data["duration"])
                        ? $data["duration"]
                        : null;
                    $durationText = $callDuration
                        ? " (Duration: {$callDuration})"
                        : "";
                    $this->_saveAndBroadcastSystemMessage(
                        $conversationId,
                        $currentUserId,
                        "Call ended{$durationText}.",
                        [
                            "eventType" => "call_ended",
                            "reason" => $reason,
                            "endedById" => $currentUserId,
                            "endedByName" => $hangingUpUserName,
                            "duration" => $callDuration,
                            "timestamp_event" => date("Y-m-d H:i:s"),
                        ]
                    );
                } else {
                    $this->log(
                        "WebRTC action '{$data["action"]}' missing conversationId or targetUserId. Data: " .
                            json_encode($data)
                    );
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" => "{$data["action"]} requires conversationId and targetUserId.",
                            ],
                        ])
                    );
                }
                break;

            case "webrtc_call_timeout":
                if ($conversationId && isset($data["targetUserId"])) {
                    $targetUserIdString = (string) $data["targetUserId"];
                    $callerInfo = $this->_getUserInfo($currentUserId);
                    $callerName =
                        trim($callerInfo["full_name"] ?? "") ?:
                        $callerInfo["username"] ?? "User " . $currentUserId;
                    $this->_saveAndBroadcastSystemMessage(
                        $conversationId,
                        $currentUserId,
                        "Call missed - no answer from called party.",
                        [
                            "eventType" => "call_missed_no_answer",
                            "callerId" => $currentUserId,
                            "callerName" => $callerName,
                            "targetId" => $targetUserIdString,
                            "timestamp_event" => date("Y-m-d H:i:s"),
                        ]
                    );
                } else {
                    $this->log(
                        "WebRTC action '{$data["action"]}' missing conversationId or targetUserId. Data: " .
                            json_encode($data)
                    );
                    $from->send(
                        json_encode([
                            "event" => "action_error",
                            "data" => [
                                "message" => "{$data["action"]} requires conversationId and targetUserId.",
                            ],
                        ])
                    );
                }
                break;

            default:
                $actionReceived = $data["action"] ?? "ACTION_NOT_SET_OR_NULL";
                $this->log(
                    "UNKNOWN ACTION: " .
                        $actionReceived .
                        " from user {$currentUserId}"
                );
                $from->send(
                    json_encode([
                        "event" => "action_unknown_error",
                        "data" => [
                            "message" => "Unknown action: " . $actionReceived,
                        ],
                    ])
                );
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $userId = $this->clients[$conn] ?? null;
        if ($userId) {
            $this->_handleUnexpectedDisconnection($userId);
            if (isset($this->userConnections[$userId])) {
                $this->userConnections[$userId]->detach($conn);
                if (count($this->userConnections[$userId]) === 0) {
                    unset($this->userConnections[$userId]);
                    $this->log(
                        "User {$userId} fully disconnected (last connection closed)."
                    );
                } else {
                    $this->log(
                        "User {$userId} (Conn ID {$conn->resourceId}) disconnected. Still has " .
                            count($this->userConnections[$userId]) .
                            " active connections."
                    );
                }
            }
        }
        $this->clients->detach($conn);
        $this->log(
            "Connection {$conn->resourceId}" .
                ($userId ? " (User {$userId})" : "") .
                " has disconnected from clients list."
        );
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $userId = $this->clients[$conn] ?? "unidentified";
        $this->log(
            "An error has occurred on connection {$conn->resourceId} (User: {$userId}): {$e->getMessage()} \nIn file: {$e->getFile()}:{$e->getLine()} \nTrace: {$e->getTraceAsString()}"
        );
        if ($userId !== "unidentified") {
            $this->_handleUnexpectedDisconnection($userId);
            if (isset($this->userConnections[$userId])) {
                $this->userConnections[$userId]->detach($conn);
                if (count($this->userConnections[$userId]) === 0) {
                    unset($this->userConnections[$userId]);
                }
            }
        }
        $this->clients->detach($conn);
        try {
            if ($conn->httpRequest !== null) {
                $conn->close();
            }
        } catch (\Throwable $th) {
            $this->log(
                "Error trying to close errored connection {$conn->resourceId}: " .
                    $th->getMessage()
            );
        }
    }

    protected function _handleUnexpectedDisconnection(string $userId): void
    {
        $this->log(
            "User {$userId} disconnected unexpectedly. Placeholder for active call termination logic."
        );
    }
    protected function _getUserInfo(string $userId): ?array
    {
        if (!$this->db) {
            $this->log(
                "DB not available for _getUserInfo (User ID: '{$userId}')."
            );
            return null;
        }
        if (!ctype_digit($userId) || (int) $userId <= 0) {
            $this->log(
                "Invalid userId format '{$userId}' for _getUserInfo. Expected positive numeric string."
            );
            return null;
        }
        try {
            $userData = $this->db->get(
                "users",
                ["id", "username", "full_name", "profile_picture"],
                ["id" => (int) $userId]
            );
            if ($userData) {
                return $userData;
            } else {
                $this->log(
                    "_getUserInfo: No data found for User ID: {$userId}. Last query: " .
                        $this->db->last()
                );
                return null;
            }
        } catch (\PDOException $e) {
            $this->log(
                "PDOException in _getUserInfo for User ID {$userId}: " .
                    $e->getMessage()
            );
            return null;
        } catch (\Throwable $e) {
            $this->log(
                "General Throwable in _getUserInfo for User ID {$userId}: " .
                    $e->getMessage()
            );
            return null;
        }
    }
    protected function _getConversationParticipants(int $conversationId): array
    {
        if (!$this->db) {
            $this->log(
                "DB not available for _getConversationParticipants (Conv ID: {$conversationId})."
            );
            return [];
        }
        if ($conversationId <= 0) {
            $this->log(
                "Invalid conversationId {$conversationId} for _getConversationParticipants."
            );
            return [];
        }
        try {
            $participantIds = $this->db->select(
                "conversation_participants",
                "user_id",
                ["conversation_id" => $conversationId]
            );
            return $participantIds ? array_map("strval", $participantIds) : [];
        } catch (\PDOException $e) {
            $this->log(
                "DB Error in _getConversationParticipants for conv {$conversationId}: " .
                    $e->getMessage()
            );
            return [];
        } catch (\Throwable $e) {
            $this->log(
                "General Error in _getConversationParticipants for conv {$conversationId}: " .
                    $e->getMessage()
            );
            return [];
        }
    }
    protected function _sendMessageToUser(string $userId, array $payload)
    {
        if (
            isset($this->userConnections[$userId]) &&
            count($this->userConnections[$userId]) > 0
        ) {
            $jsonPayload = json_encode($payload);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log(
                    "JSON encode error for payload to user {$userId}: " .
                        json_last_error_msg() .
                        ". Payload event: " . ($payload['event'] ?? 'N/A')
                );
                return;
            }
            $this->log( // Log successful preparation to send
                "Attempting to send '{$payload['event']}' to User ID {$userId} (Connections: " . count($this->userConnections[$userId]) . ")"
            );
            foreach ($this->userConnections[$userId] as $connection) {
                try {
                    $connection->send($jsonPayload);
                } catch (\Throwable $e) {
                    $this->log(
                        "Error sending '{$payload['event']}' to User ID {$userId} (Conn {$connection->resourceId}): " .
                            $e->getMessage()
                    );
                    // Optionally, consider removing this specific dead connection here
                }
            }
        } else { // ADD THIS LOGGING BLOCK
            $this->log(
                "Info: _sendMessageToUser - No active/identified WebSocket connection found for User ID {$userId} to send event '{$payload['event']}'. Message not delivered to this user via WebSocket."
            );
        }
    }
    protected function _broadcastToConversation(
        int $conversationId,
        array $payload,
        ?ConnectionInterface $excludeConn = null
    ) {
        $participants = $this->_getConversationParticipants($conversationId);
        if (empty($participants)) {
            return;
        }
        $jsonPayload = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log(
                "JSON encode error for broadcast to conv {$conversationId}: " .
                    json_last_error_msg() .
                    ". Payload: " .
                    print_r($payload, true)
            );
            return;
        }
        foreach ($participants as $participantUserId) {
            if (isset($this->userConnections[$participantUserId])) {
                foreach (
                    $this->userConnections[$participantUserId]
                    as $clientConn
                ) {
                    if ($excludeConn !== null && $clientConn === $excludeConn) {
                        continue;
                    }
                    try {
                        $clientConn->send($jsonPayload);
                    } catch (\Throwable $e) {
                        $this->log(
                            "Error broadcasting to User {$participantUserId} (Conn {$clientConn->resourceId}): " .
                                $e->getMessage()
                        );
                    }
                }
            }
        }
    }
    protected function _saveAndBroadcastSystemMessage(
        int $conversationId,
        string $currentUserId,
        string $content,
        ?array $metadata = null
    ): void {
        if (!$this->db) {
            $this->log(
                "CRITICAL: DB not available, cannot save system message for conv {$conversationId}: '{$content}'"
            );
            return;
        }
        $messageData = [
            "conversation_id" => $conversationId,
            "sender_id" => (int) $currentUserId,
            "content" => $content,
            "message_type" => "call_event",
            "metadata" => $metadata ? json_encode($metadata) : null,
            "is_unsent" => false,
        ];
        $messageId = null;
        try {
            $this->db->action(function (Medoo $database) use (
                $messageData,
                $conversationId,
                &$messageId
            ) {
                $stmt = $database->insert("messages", $messageData);
                if (!$stmt || $stmt->rowCount() === 0) {
                    $this->log(
                        "DB ERROR: Failed to insert system message for conv {$conversationId}. Error: " .
                            json_encode($database->error()) .
                            " Data: " .
                            json_encode($messageData)
                    );
                    return false;
                }
                $messageId = $database->id();
                if (
                    !$messageId ||
                    !is_numeric($messageId) ||
                    (int) $messageId <= 0
                ) {
                    $this->log(
                        "DB ERROR: Invalid message ID after system message insert for conv {$conversationId}. ID: " .
                            var_export($messageId, true)
                    );
                    return false;
                }
                $updateResult = $database->update(
                    "conversations",
                    [
                        "last_message_id" => (int) $messageId,
                        "updated_at" => Medoo::raw("NOW()"),
                    ],
                    ["id" => $conversationId]
                );
                if ($updateResult === false) {
                    $this->log(
                        "DB ERROR: Failed to update conversation {$conversationId} after system message. Error: " .
                            json_encode($database->error())
                    );
                    return false;
                }
                return true;
            });
        } catch (\PDOException $e) {
            $this->log(
                "PDOException saving system message for conv {$conversationId}. Content: '{$content}'. Error: " .
                    $e->getMessage()
            );
            return;
        } catch (\Throwable $e) {
            $this->log(
                "Throwable saving system message for conv {$conversationId}. Content: '{$content}'. Error: " .
                    $e->getMessage()
            );
            return;
        }
        if ($messageId) {
            $this->log(
                "System message (ID: {$messageId}) saved for conv {$conversationId}: '{$content}'"
            );
            $fullMessageObject = $this->db->get(
                "messages",
                ["[>]users(sender_user)" => ["sender_id" => "id"]],
                [
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
                ],
                ["messages.id" => $messageId]
            );
            if ($fullMessageObject) {
                if (
                    isset($fullMessageObject["metadata_json"]) &&
                    is_string($fullMessageObject["metadata_json"])
                ) {
                    $decodedMeta = json_decode(
                        $fullMessageObject["metadata_json"],
                        true
                    );
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $fullMessageObject["metadata"] = $decodedMeta;
                    } else {
                        $this->log(
                            "Warning: Could not decode metadata for system message ID {$messageId}. Error: " .
                                json_last_error_msg() .
                                ". Raw: " .
                                $fullMessageObject["metadata_json"]
                        );
                        $fullMessageObject["metadata"] = null;
                    }
                    unset($fullMessageObject["metadata_json"]);
                } else {
                    $fullMessageObject["metadata"] =
                        $fullMessageObject["metadata"] ?? null;
                }
                $payloadToBroadcast = [
                    "event" => "incoming_chat_message",
                    "data" => $fullMessageObject,
                ];
                $this->_broadcastToConversation(
                    $conversationId,
                    $payloadToBroadcast
                );
            } else {
                $this->log(
                    "ERROR: Could not fetch system message ID {$messageId} after saving for broadcast. Last query: " .
                        $this->db->last()
                );
            }
        } else {
            $this->log(
                "ERROR: System message was not saved to DB for conv {$conversationId} (messageId is null after transaction attempt), not broadcasting. Content: '{$content}'"
            );
        }
    }
    protected function log(string $message)
    {
        echo "[" . date("Y-m-d H:i:s") . "] [WSS] " . $message . "\n";
    }

    
}
