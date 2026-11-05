<?php
// /src/Controllers/PromptsController.php
namespace Ginto\Controllers;

class PromptsController {
    /**
     * Serve role-based prompts as JSON for the chat UI.
     * Returns prompts based on user role: admin, user, or visitor.
     */
    public static function getPrompts() {
        // Ensure session is started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Determine user role from session
        $role = 'visitor'; // Default for non-logged-in users
        
        // Check if user is logged in
        if (!empty($_SESSION['user_id'])) {
            // Check if admin
            $sessionRole = $_SESSION['user_role'] ?? '';
            if ($sessionRole === 'admin') {
                $role = 'admin';
            } else {
                $role = 'user';
            }
        }
        
        // Load prompts config
        $prompts = include(__DIR__ . '/../Views/chat/prompts/prompts.php');
        $result = isset($prompts[$role]) ? $prompts[$role] : ($prompts['visitor'] ?? []);
        
        // For visitors, randomly select 4 prompts from the pool
        if ($role === 'visitor' && count($result) > 4) {
            // Shuffle and pick 4 random prompts
            $shuffled = $result;
            shuffle($shuffled);
            $result = array_slice($shuffled, 0, 4);
        }
        
        // Include course catalog for AI context (only for visitors)
        $courseCatalog = null;
        if ($role === 'visitor' && isset($prompts['course_catalog'])) {
            $courseCatalog = $prompts['course_catalog'];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'role' => $role, 
            'prompts' => $result,
            'course_catalog' => $courseCatalog
        ]);
        exit;
    }
}
