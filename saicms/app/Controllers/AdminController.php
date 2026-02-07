<?php
namespace App\Controllers;

use Core\Controller;

class AdminController extends Controller {

    public function __construct()
    {
        parent::__construct();
        // Secure this entire controller. Redirect if not an admin.
        if (!$this->isSuperUser()) {
            // Or show a 403 Forbidden error page
            header('Location: /'); 
            exit;
        }
    }

    /**
     * Checks if the logged-in user has admin privileges.
     * @return bool
     */
    private function isSuperUser(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user = $this->db->get('users', ['id', 'role'], ['id' => $_SESSION['user_id']]);
        return ($user && ($user['id'] == 1 || $user['role'] === 'admin'));
    }

    /**
     * Searches for users for the typeahead functionality.
     * Returns JSON.
     */
    public function searchUsers()
    {
        header('Content-Type: application/json');
        $query = $_GET['query'] ?? '';

        if (strlen($query) < 2) {
            echo json_encode([]);
            exit;
        }

        // Search for users by name or email, excluding the admin themselves
        $users = $this->db->select('users', 
            ['id', 'full_name', 'email', 'profile_picture', 'role'], 
            [
                "AND" => [
                    "id[!]" => $_SESSION['user_id'],
                    "OR" => [
                        "full_name[~]" => $query,
                        "email[~]" => $query
                    ]
                ],
                "LIMIT" => 10
            ]
        );

        // For each user, check if they have an active subscription
        foreach ($users as &$user) {
            // =========================================================================
            // THE FIX IS HERE: 'ends_at' has been replaced with 'current_period_end'
            // =========================================================================
            $user['is_premium'] = $this->db->has("subscriptions", [
                "user_id" => $user['id'],
                "status" => "active",
                "current_period_end[>]" => date("Y-m-d H:i:s")
            ]);
        }

        echo json_encode($users);
    }

    /**
     * Updates a user's role and premium status.
     * Handles POST requests from the admin modal.
     */
    public function updateUserStatus()
    {
        header('Content-Type: application/json');

        // CSRF Token Validation
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $userIdToUpdate = $data['userId'] ?? null;
        $setAsAdmin = $data['setAsAdmin'] ?? false;
        $setAsPremium = $data['setAsPremium'] ?? false;

        if (!$userIdToUpdate || !is_numeric($userIdToUpdate)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid User ID.']);
            exit;
        }

        // --- Update User Role ---
        $newRole = $setAsAdmin ? 'admin' : 'user';
        $this->db->update('users', ['role' => $newRole], ['id' => $userIdToUpdate]);

        // --- Update Premium Status ---
        if ($setAsPremium) {
            // Grant premium: Add or update subscription record
            $farFutureDate = date('Y-m-d H:i:s', strtotime('+100 years'));
            $existingSub = $this->db->get('subscriptions', '*', ['user_id' => $userIdToUpdate]);
            
            // CORRECTED: Using 'start_date' and 'current_period_end' from your actual table schema.
            if ($existingSub) {
                $this->db->update('subscriptions', 
                    ['status' => 'active', 'current_period_end' => $farFutureDate],
                    ['user_id' => $userIdToUpdate]
                );
            } else {
                $this->db->insert('subscriptions', [
                    'user_id' => $userIdToUpdate,
                    'paypal_plan_id' => 'admin_granted', // Use an appropriate plan ID column
                    'status' => 'active',
                    'start_date' => date('Y-m-d H:i:s'),
                    'current_period_end' => $farFutureDate
                ]);
            }
        } else {
            // Revoke premium: Set status to 'cancelled' and end date to now.
            // CORRECTED: Replaced 'ends_at' with 'current_period_end'
            $this->db->update('subscriptions', 
                ['status' => 'cancelled', 'current_period_end' => date('Y-m-d H:i:s')],
                ['user_id' => $userIdToUpdate]
            );
        }

        echo json_encode(['status' => 'success', 'message' => 'User updated successfully.']);
    }
}