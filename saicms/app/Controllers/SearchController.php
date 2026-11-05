<?php
namespace App\Controllers;

use Core\Controller;
use Medoo\Medoo; // Still good to have for type hinting if used directly

class SearchController extends Controller {

    private $currentUserId; // Keep this if specific to SearchController logic

    public function __construct() {
        parent::__construct(); // IMPORTANT: Call parent constructor

        // Get current logged-in user ID from session (if needed beyond DB access)
        if (isset($_SESSION['user_id'])) {
            $this->currentUserId = (int) $_SESSION['user_id'];
        } else {
            $this->currentUserId = null;
        }

        // $this->db is now available from the parent Core\Controller
    }

    public function search() {
        header('Content-Type: application/json');

        if (!$this->currentUserId) { // currentUserId is set in this controller's constructor
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in to search.']);
            exit;
        }

        // Check if $this->db (Medoo instance) is available
        if (!$this->db) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Database service unavailable.']);
            exit;
        }

        $query = trim($_GET['q'] ?? '');

        if (empty($query) || mb_strlen($query) < 1) {
            echo json_encode(['success' => true, 'users' => []]);
            exit;
        }

        $searchPattern = "%" . (string)$query . "%"; // Ensure $query is treated as a string for the pattern
        $currentUserIdToExclude = $this->currentUserId;

        // Use $this->db directly. Column is `fullname` in DB (not `full_name`). Wrap in try/catch to avoid uncaught PDOExceptions.
        try {
            $usersData = $this->db->select("users",
                ["id", "username", "fullname", "profile_picture"],
                [
                    "AND" => [
                        "id[!]" => $currentUserIdToExclude,
                        "status" => "active",
                        "OR" => [ // Search EITHER fullname OR username
                            "fullname[~]" => $searchPattern,
                            "username[~]" => $searchPattern
                        ]
                    ],
                    "LIMIT" => 10
                ]
            );
        } catch (\Throwable $e) {
            error_log("SearchController DB error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error fetching users.']);
            exit;
        }

        // For debugging the query if needed:
        // error_log("Medoo Search Query: " . $this->db->last());
        // error_log("Search Pattern: " . $searchPattern);
        // error_log("Users Data Raw: " . print_r($usersData, true));


        if ($usersData === false) {
            error_log("SearchController DB Error (using inherited DB): " . json_encode($this->db->error()));
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error fetching users.']);
            exit;
        }

        $results = [];
        foreach ($usersData as $user) {
            $displayName = !empty($user['fullname']) ? $user['fullname'] : $user['username'];
            $results[] = [
                'id' => (int)$user['id'],
                'name' => $displayName,
                'username' => $user['username'],
                'avatar' => $user['profile_picture'] ?: $this->generateFallbackAvatar($displayName, 32)
            ];
        }

        echo json_encode(['success' => true, 'users' => $results]);
        exit;
    }

    // generateFallbackAvatar method remains the same
    private function generateFallbackAvatar(string $name, int $size = 32): string
    {
        $initial = '?';
        if (!empty(trim($name))) {
            $nameParts = explode(' ', trim($name));
            if (count($nameParts) >= 2) {
                $initial = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1));
                 if(strlen($initial) === 1) {
                    $initial = strtoupper(mb_substr($nameParts[0], 0, 1));
                 }
            } else {
                 $initial = strtoupper(mb_substr($nameParts[0], 0, 1));
            }
             if(empty($initial)) $initial = '?';
        }
        $hueSeed = crc32(strtolower(trim($name)));
        $hue = $hueSeed % 360;
        $bgColor = "hsl({$hue}, 70%, 85%)";
        $textColor = "hsl({$hue}, 50%, 35%)";
        $fontSize = (mb_strlen($initial) > 1) ? '40' : '50';
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="%s" fill="%s" font-weight="bold">%s</text></svg>',
            $size, $size, htmlspecialchars($bgColor), $fontSize, htmlspecialchars($textColor), htmlspecialchars($initial)
        );
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }
}