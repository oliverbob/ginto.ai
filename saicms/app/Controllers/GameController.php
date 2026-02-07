<?php
namespace App\Controllers;

use Core\Controller;
use IconCaptcha\IconCaptcha;
use Medoo\Medoo;
use DBConnect;

class GameController extends Controller {


    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Helper function to check if a user has an active subscription.
     * @param int $userId
     * @return bool
     */
    private function isPremiumUser(int $userId): bool
    {
        // Checks if a user has a subscription that is currently active.
        // CORRECTED: Replaced the incorrect 'ends_at' with the actual column name 'current_period_end'.
        return $this->db->has("subscriptions", [
            "user_id" => $userId,
            "status" => "active",
            "current_period_end[>]" => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * Loads the typing game view.
     * It checks if the user is premium and passes their progress to the view.
     */
    public function game() {
        $userId = $_SESSION['user_id'] ?? null;
        $isPremium = false;
        $startLessonIndex = 0;
        $isAdmin = false;
        
        // NEW: Prepare user data for the view
        $userData = [
            'id' => null,
            'full_name' => 'Guest',
            'profile_picture' => null
        ];

        if ($userId) {
            // Use actual DB columns: `fullname`, `avatar`, `is_admin`, `role_id`
            $user = $this->db->get('users', ['id', 'role_id', 'is_admin', 'fullname', 'avatar'], ['id' => $userId]);
            if ($user) {
                // Populate user data, mapping DB keys to expected view keys
                $userData = [
                    'id' => $user['id'],
                    'full_name' => $user['fullname'] ?? 'User',
                    'profile_picture' => $user['avatar'] ?? null
                ];

                // Determine admin: either explicit is_admin flag or role_id == 1 or user id 1
                if ($user['id'] == 1 || (!empty($user['is_admin']) && $user['is_admin']) || (isset($user['role_id']) && (int)$user['role_id'] === 1)) {
                    $isAdmin = true;
                }
                
                $isPremium = $this->isPremiumUser($userId);
                if ($isPremium) {
                    $progress = $this->db->get("user_typing_progress", "current_lesson_index", ["user_id" => $userId]);
                    if ($progress !== null) {
                        $startLessonIndex = (int)$progress;
                    }
                }
            }
        }
        
        $this->view('game', [
            'isPremium' => $isPremium,
            'startLessonIndex' => $startLessonIndex,
            'isAdmin' => $isAdmin,
            'currentUser' => $userData // Pass the full user data object
        ]);
    }

    /**
     * Saves the result of a completed typing lesson via an AJAX request.
     * This endpoint is for premium users only and includes CSRF protection.
     */
    public function saveProgress()
    {
        // Set header to return JSON
        header('Content-Type: application/json');

        // 1. CSRF Token Validation
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        // HTTP headers are prefixed with HTTP_ and converted to uppercase
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null; 

        if (empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken)) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Invalid or missing CSRF token. Request rejected.']);
            exit;
        }

        // 2. Security Check: Ensure user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
            exit;
        }

        $userId = $_SESSION['user_id'];

        // 3. Premium Check: Ensure user has an active subscription
        if (!$this->isPremiumUser($userId)) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'This feature is for premium users only.']);
            exit;
        }
        
        // 4. Get and Validate Input
        $data = json_decode(file_get_contents('php://input'), true);
        if (
            !isset($data['lessonIndex'], $data['wpm'], $data['accuracy']) ||
            !is_numeric($data['lessonIndex']) ||
            !is_numeric($data['wpm']) ||
            !is_numeric($data['accuracy'])
        ) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
            exit;
        }

        $lessonIndex = (int)$data['lessonIndex'];
        $wpm = (int)$data['wpm'];
        $accuracy = (int)$data['accuracy'];
        $nextLessonIndex = $lessonIndex + 1;

        // 5. Save the result to the database (This part is now safe to run)
        try {
            // ... (The rest of the database logic remains the same) ...
            
            // A. Insert the result of this specific attempt
            $this->db->insert('typing_results', [
                'user_id' => $userId,
                'lesson_index' => $lessonIndex,
                'wpm' => $wpm,
                'accuracy' => $accuracy
            ]);

            // B. Update the user's overall progress tracker
            $hasProgressRecord = $this->db->has('user_typing_progress', ['user_id' => $userId]);

            if ($hasProgressRecord) {
                $this->db->update('user_typing_progress', 
                    ['current_lesson_index' => $nextLessonIndex],
                    [
                        'user_id' => $userId,
                        'current_lesson_index[<]' => $nextLessonIndex
                    ]
                );
            } else {
                $this->db->insert('user_typing_progress', [
                    'user_id' => $userId,
                    'current_lesson_index' => $nextLessonIndex
                ]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Progress saved.']);

        } catch (\Exception $e) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

}
?>