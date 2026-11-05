<?php
namespace App\Controllers;

use Core\Controller;

class CodeController extends Controller { // CORRECTED: Ensure it extends Controller

    public function code() {
        // Calculate the expiry time
        $expiryTimeValue = $this->codeExpiringAt(); // Returns 'YYYY-MM-DD HH:MM:SS' or null
        
        // Prepare data for the view
        $viewData = [
            'expiryTime' => $expiryTimeValue,
            // 'username' is handled by the PHP at the top of the view file itself
        ];
        
        // Use the view() method inherited from the base Core\Controller
        $this->view('code', $viewData); 
        // exit; // The exit() here might be part of your original design, keep it if needed.
                 // Often, the view method itself or the framework handles ending the script.
    }

    /**
     * Calculates and returns the subscription expiry time for the logged-in user.
     * Expiry is 24 hours from the 'created_at' timestamp of their 'ACTIVE' subscription.
     * 
     * @return string|null The expiry timestamp (YYYY-MM-DD HH:MM:SS) or null if not applicable.
     */
    public function codeExpiringAt() {

        if (!isset($_SESSION['user'])) {
            return null; 
        }
        $userEmail = $_SESSION['user'];

        // --- Database Connection (remains the same) ---
        if (!defined('CONFV_PATH')) {
            define('CONFV_PATH', realpath(__DIR__ . '/../../config')); 
        }
        $dbConnectPath = CONFV_PATH . '/medoo/DBConnect.php';
        $mdCredsPath = CONFV_PATH . '/medoo/mdcreds.php';
        if (!file_exists($dbConnectPath) || !file_exists($mdCredsPath)) {
            error_log("codeExpiringAt: Missing DB config or connector.");
            return null;
        }
        require_once $dbConnectPath;
        $db_creds = require $mdCredsPath;
        try {
            $conn = new \DBConnect($db_creds); 
        } catch (\Exception $e) {
            error_log("codeExpiringAt: DB Connection failed: " . $e->getMessage());
            return null;
        }
        // --- End Database Connection ---

        // --- START: CORRECTED LOGIC ---

        // 1. Get the numeric user ID from the 'users' table using the email
        $user = $conn->db->get("users", "id", [
            "email" => $userEmail
        ]);

        if (!$user) {
            // User with this email doesn't exist in the users table.
            error_log("codeExpiringAt: No user found in 'users' table for email: " . $userEmail);
            return null;
        }
        $numericUserId = $user; // Medoo's get() with a single column returns the value directly

        // 2. Use the numeric user ID to query the 'subscriptions' table
        $subscriptionCreatedAt = $conn->db->get("subscriptions", "created_at", [
            "user_id" => $numericUserId, // Correct: Using the numeric ID
            "status"  => "ACTIVE",
            "ORDER"   => ["created_at" => "DESC"]
        ]);

        // --- END: CORRECTED LOGIC ---

        if ($subscriptionCreatedAt) {
            $createdAtTimestamp = strtotime($subscriptionCreatedAt);
            
            if ($createdAtTimestamp === false) {
                error_log("codeExpiringAt: Failed to parse created_at string: " . $subscriptionCreatedAt);
                return null;
            }

            $expiryTimestamp = $createdAtTimestamp + (24 * 60 * 60);
            return date('Y-m-d H:i:s', $expiryTimestamp);
        } else {
            // This log is now more accurate. It means no ACTIVE subscription was found for that user ID.
            error_log("codeExpiringAt: No ACTIVE subscription found for user_id: " . $numericUserId);
            return null;
        }
    }

    /**
     * Saves AI-generated code as a new post in the database.
     * Endpoint for handling the 'Save to Feed' action for AI code.
     */
    public function codesave() { // This is the method your router calls
        // --- START: Standard Setup & Authentication ---
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user']) || empty(trim($_SESSION['user']))) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not logged in or session invalid.']);
            exit;
        }
        $emailFromSession = trim($_SESSION['user']);

        if (!filter_var($emailFromSession, FILTER_VALIDATE_EMAIL)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session identifier is not a valid email format.']);
            exit;
        }
        // --- END: Standard Setup & Authentication ---

        // --- START: Input Processing ---
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['code_content']) || !isset($input['language'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required parameters: code_content or language.']);
            exit;
        }

        $rawCodeContent = $input['code_content'];
        $language = trim(strtolower($input['language']));
        $originalPrompt = isset($input['prompt']) ? trim($input['prompt']) : null;
        $visibility = 'public'; // Default or make configurable

        if (empty(trim($rawCodeContent))) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Code content cannot be empty.']);
            exit;
        }
        // --- END: Input Processing ---

        // --- START: Database Connection ---
        // This is the crucial block that was likely incomplete in your version
        if (!defined('CONFV_PATH')) {
            // Assuming CodeController.php is in app/Controllers/
            // The path to config should be two levels up then into config/
            define('CONFV_PATH', realpath(__DIR__ . '/../../config'));
        }
        $dbConnectPath = CONFV_PATH . '/medoo/DBConnect.php'; // Adjusted path based on your screenshot
        $mdCredsPath = CONFV_PATH . '/medoo/mdcreds.php';   // Adjusted path based on your screenshot

        if (!file_exists($dbConnectPath)) {
            error_log("DBConnect.php not found at: " . $dbConnectPath);
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error: DB connector missing.']);
            exit;
        }
        if (!file_exists($mdCredsPath)) {
            error_log("mdcreds.php not found at: " . $mdCredsPath);
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error: DB credentials missing.']);
            exit;
        }

        require_once $dbConnectPath; // Ensures DBConnect class is loaded
        $db_creds = require $mdCredsPath;   // <<< THIS DEFINES $db_creds

        if (!$db_creds) { // Basic check for the credentials file
            error_log("mdcreds.php did not return valid credentials.");
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error: Failed to load DB credentials.']);
            exit;
        }

        try {
            $conn = new \DBConnect($db_creds); // $db_creds is now defined and passed
        } catch (\Exception $e) {
            error_log("DB Connection failed in CodeController@codesave: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            exit;
        }
        // --- END: Database Connection ---

        // --- START: Fetch User Data ---
        $userData = $conn->db->get("users", ["id", "username", "full_name", "profile_picture"], ["email" => $emailFromSession]);
        if (!$userData || !isset($userData['id'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "User (email: {$emailFromSession}) not found. Please re-login."]);
            exit;
        }
        $numericUserId = (int) $userData['id'];
        // --- END: Fetch User Data ---

        // --- START: Database Insert ---
        $dataToInsert = [
            "user_id"       => $numericUserId,
            "content"       => $rawCodeContent,
            "post_type"     => "ai_code",
            "code_language" => $language,
            "visibility"    => $visibility,
            // "original_prompt" => $originalPrompt, // Uncomment if you added this column
        ];

        try {
            $conn->db->insert("posts", $dataToInsert);
            $newPostId = $conn->db->id();
            if (!$newPostId) {
                error_log("Failed to get new post ID after insert. Medoo error: " . json_encode($conn->db->errorInfo()));
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save post (no ID returned).']);
                exit;
            }
        } catch (\PDOException $e) {
            error_log("PDOException during post insert in CodeController@codesave: " . $e->getMessage() . " | Data: " . json_encode($dataToInsert));
            header('Content-Type: application/json');
            http_response_code(500);
            if (strpos($e->getMessage(), '1452') !== false) { // FK violation
                 echo json_encode(['success' => false, 'message' => 'Error creating post: Invalid user reference during insert.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'Database error while saving post.']);
            }
            exit;
        }
        // --- END: Database Insert ---

        // --- START: Fetch and Prepare Response Data ---
        $newlySavedPost = $conn->db->get("posts", [
            "[>]users" => ["user_id" => "id"]
        ], [
            "posts.id", "posts.content", "posts.image", "posts.visibility",
            "posts.post_type", "posts.code_language",
            // "posts.original_prompt",
            "posts.created_at", "posts.updated_at",
            "users.username", "users.full_name", "users.profile_picture", "posts.user_id (post_author_id)"
        ], [
            "posts.id" => $newPostId
        ]);

        if (!$newlySavedPost) {
            error_log("Post saved (ID: {$newPostId}), but could not retrieve it with user info for response.");
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Post saved, but retrieval for response failed.']);
            exit;
        }

        // SVG Avatar Logic (Ensure this part is complete and correct based on previous discussions)
        $profilePictureUrl = $newlySavedPost['profile_picture'];
        if (empty($profilePictureUrl)) {
            $firstLetter = '?';
            $nameForInitial = !empty($newlySavedPost['full_name']) ? $newlySavedPost['full_name'] : $newlySavedPost['username'];
            if (!empty($nameForInitial)) {
                $firstGrapheme = mb_substr($nameForInitial, 0, 1, 'UTF-8');
                if (!empty($firstGrapheme)) {
                     $firstLetter = strtoupper($firstGrapheme);
                }
            }
            $safeFirstLetter = htmlspecialchars($firstLetter, ENT_XML1, 'UTF-8');
            
            $hue = ($newlySavedPost['post_author_id'] * 30) % 360;
            $bgColor = "hsl({$hue}, 70%, 85%)";
            $textColor = "hsl({$hue}, 50%, 35%)";
            $svgWidth = 100; $svgHeight = 100; $fontSize = $svgHeight / 2; // Assuming these are defined or you set them here

            $svgContent = sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="40" height="40">'.
                '<rect width="%d" height="%d" fill="%s"/>'.
                '<text x="50%%" y="50%%" dominant-baseline="central" text-anchor="middle" font-family="Arial, sans-serif" font-size="%.1f" fill="%s" font-weight="bold">%s</text>'.
                '</svg>',
                $svgWidth, $svgHeight, $svgWidth, $svgHeight, $bgColor, $fontSize, $textColor, $safeFirstLetter
            );
            $profilePictureUrl = "data:image/svg+xml;charset=utf-8;base64," . base64_encode($svgContent);
        }
        // --- END: Fetch and Prepare Response Data ---

        // --- START: Final JSON Response ---
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'AI generated code saved as a post successfully!',
            'post' => [
                'id' => $newlySavedPost['id'],
                'user_id' => $newlySavedPost['post_author_id'],
                'username' => $newlySavedPost['username'],
                'full_name' => $newlySavedPost['full_name'],
                'user_avatar' => $profilePictureUrl,
                'content' => $newlySavedPost['content'],
                'image' => $newlySavedPost['image'],
                'visibility' => $newlySavedPost['visibility'],
                'post_type' => $newlySavedPost['post_type'],
                'code_language' => $newlySavedPost['code_language'],
                // 'original_prompt' => $newlySavedPost['original_prompt'],
                'created_at' => $newlySavedPost['created_at'],
                'updated_at' => $newlySavedPost['updated_at'],
                'likes' => 0, 'comments' => 0, 'shares' => 0,
            ]
        ]);
        exit;
        // --- END: Final JSON Response ---
    }

}