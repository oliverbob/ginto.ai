<?php
// app/WebSocket/WSController.php

namespace App\Controllers;

use Core\Controller; // Assuming your Core Controller is here
// You might include Ratchet or ReactPHP 'use' statements here IF you are only
// testing class instantiation or some utility functions FROM those libraries,
// but not to RUN a server.
// For example:
// use Ratchet\RFC6455\Messaging\Frame; // If you wanted to test frame creation/parsing

class WSController extends Controller
{
    public function __construct()
    {
        parent::__construct(); // Call parent constructor to initialize $this->db, etc.
        error_log("WSController initialized (within HTTP request context).");
    }

    /**
     * Example test action: Simulate preparing data that *would* be sent over WebSocket.
     * This would be called via an HTTP route, e.g., /ws-test/prepare-message
     */
    public function testPrepareMessageAction()
    {
        $this->ensureDbReady(); // If you need DB access from Core\Controller

        $messageContent = $_GET['message'] ?? 'Default test message from WSController';
        $recipientUserId = $_GET['recipient'] ?? 'user123'; // Simulated

        // Simulate fetching sender details (normally from session or auth)
        $senderDetails = [
            'sender_id' => $this->currentUserId ?: 'user_sender_test', // Assuming currentUserId is from Core\Controller
            'sender_full_name' => $this->currentUserFullName ?: 'Test Sender',
            'sender_profile_picture' => null
        ];

        // Construct the payload similar to what a real WebSocket push would carry
        $payloadForWebSocket = [
            'message_id' => uniqid('msg_'), // Simulated message ID
            'conversation_id' => 'conv_test_' . ($this->currentUserId ?: 'unknown'),
            'sender_id' => $senderDetails['sender_id'],
            'sender_full_name' => $senderDetails['sender_full_name'],
            'sender_profile_picture' => $senderDetails['sender_profile_picture'],
            'content' => $this->sanitizeHtml($messageContent), // Sanitize if it came from user input
            'message_type' => 'text',
            'sent_at' => date('Y-m-d H:i:s'),
            'metadata' => null,
        ];

        $webSocketNotification = [
            'event' => 'incoming_chat_message', // Event name for client-side JS
            'recipientUserId_simulated' => (string)$recipientUserId,
            'data' => $payloadForWebSocket
        ];

        // Log what would be sent
        error_log("WSController Test: SIMULATED WebSocket Push for User {$recipientUserId}: " . json_encode($webSocketNotification));

        // Send it back as an HTTP JSON response for testing
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Simulated WebSocket payload prepared.',
            'simulated_push_payload' => $webSocketNotification
        ]);
        exit;
    }

    /**
     * Example action: Simulate receiving a command via an HTTP endpoint that
     * might have otherwise come through a WebSocket message from a client.
     * e.g., /ws-test/client-action?action=identify&userId=...
     */
    public function testClientAction()
    {
        $clientAction = $_GET['action'] ?? null;
        $userId = $_GET['userId'] ?? null; // In real WS, this would be validated.

        if ($clientAction === 'identify' && $userId) {
            error_log("WSController Test: Received simulated 'identify' action for user {$userId} via HTTP.");
            // In a real WSController (MessageComponentInterface), you'd map $userId to a ConnectionInterface.
            // Here, we just log it.
            $response = ['event' => 'user_identified_simulated', 'status' => 'success', 'userId' => $userId];
        } else {
            error_log("WSController Test: Received unknown or incomplete client action via HTTP: " . $clientAction);
            $response = ['event' => 'action_error_simulated', 'message' => 'Unknown action or missing parameters.'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // You could add other test methods here.
    // For example, if you wanted to test a utility function from Ratchet without
    // running the server:
    public function testRatchetUtility()
    {
        // Make sure you have 'use Ratchet\RFC6455\Messaging\Frame;' at the top.
        // if (class_exists('Ratchet\RFC6455\Messaging\Frame')) {
        //     $frame = new \Ratchet\RFC6455\Messaging\Frame('Test payload');
        //     error_log("WSController Test: Ratchet Frame test - Masked: " . ($frame->isMasked() ? 'Yes' : 'No'));
        //     echo "Ratchet Frame test executed. Check logs.";
        // } else {
        //     echo "Ratchet\RFC6455\Messaging\Frame class not found. Ensure Ratchet is autoloaded.";
        // }
        // exit;
        echo "This utility test needs Ratchet classes to be available and is just an example.";
        exit;
    }
}