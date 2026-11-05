<?php
namespace App\Controllers;

use Core\Controller;
use Medoo\Medoo; 

class ContactsController extends Controller {

    private $currentUserId;

    public function __construct() {
        parent::__construct(); 

        if (isset($_SESSION['user_id'])) {
            $this->currentUserId = (int) $_SESSION['user_id'];
        } else {
            $this->currentUserId = null; 
        }
    }

    /**
     * API endpoint to fetch contacts.
     * (No changes in this method's direct logic)
     */
    public function contacts() {
        header('Content-Type: application/json');

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
            exit;
        }

        if (!$this->db) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Database service unavailable.']);
            exit;
        }

        $contactType = $_GET['type'] ?? 'friends';
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 15, 'min_range' => 1]]);

        $contactsData = [];
        
        if ($contactType === 'friends') {
            $contactsData = $this->getFriends($limit);
        } else {
            $contactsData = $this->getAllUsers($limit);
        }
        
        if ($contactsData === false) {
            $dbError = $this->db->error();
            error_log("ContactsController DB Error: " . ($dbError ? json_encode($dbError) : 'Unknown Medoo error.') . " Last Query: " . $this->db->last());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error fetching contacts. Please try again later.']);
            exit;
        }

        $results = [];
        foreach ($contactsData as $contact) {
            $displayName = !empty($contact['full_name']) ? trim($contact['full_name']) : trim($contact['username']);
            $avatar = $contact['profile_picture'] ?: $this->generateFallbackAvatar($displayName, 32);

            // --- MODIFIED ---
            // We no longer calculate online status in PHP. We use the 'is_online' flag
            // that the database query now provides directly.
            $isOnline = (bool)$contact['is_online'];

            $results[] = [
                'id' => (int)$contact['id'],
                'name' => $displayName,
                'avatar' => $avatar,
                'isOnline' => $isOnline 
            ];
        }

        echo json_encode(['success' => true, 'contacts' => $results]);
        exit;
    }
    
    /**
     * --- MODIFIED ---
     * The `statuses` method now also uses a direct database calculation.
     */
    public function statuses() {
        header('Content-Type: application/json');

        if (!$this->currentUserId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $userIds = array_filter(array_map('intval', $data['ids'] ?? []));

        if (empty($userIds)) {
            echo json_encode(['success' => true, 'statuses' => []]);
            exit;
        }
        
        // --- MODIFIED ---
        // Ask the database to calculate the online status for us.
        // `is_online` will be 1 if true, 0 if false.
        $usersData = $this->db->select('users', [
            'id',
            'is_online' => Medoo::raw('CASE WHEN last_seen_at IS NOT NULL AND last_seen_at > NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END')
        ], ['id' => $userIds]);

        if ($usersData === false) {
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => 'Database error']);
             exit;
        }

        $statuses = [];
        // --- MODIFIED ---
        // The logic is much simpler now. We just read the result from the DB.
        foreach ($usersData as $user) {
            $statuses[$user['id']] = (bool)$user['is_online'];
        }

        echo json_encode(['success' => true, 'statuses' => $statuses]);
        exit;
    }

    /**
     * --- MODIFIED ---
     * This method now asks the database to calculate the online status directly.
     */
    private function getFriends(int $limit) {
        $friendships = $this->db->select('friends', ['user_id', 'friend_id'], [
            "AND" => ["status" => "accepted", "OR" => ["user_id" => $this->currentUserId, "friend_id" => $this->currentUserId]]
        ]);

        if (empty($friendships)) { return []; }

        $friendIds = [];
        foreach ($friendships as $friendship) {
            if ($friendship['user_id'] != $this->currentUserId) { $friendIds[] = $friendship['user_id']; }
            if ($friendship['friend_id'] != $this->currentUserId) { $friendIds[] = $friendship['friend_id']; }
        }
        $uniqueFriendIds = array_unique($friendIds);

        if (empty($uniqueFriendIds)) { return []; }
        
        // --- MODIFIED ---
        // We add a new 'is_online' field to the SELECT statement.
        // The database will calculate this for us. `NOW() - INTERVAL 5 MINUTE` is the key.
        return $this->db->select("users", [
            "id", "username", "full_name", "profile_picture",
            "is_online" => Medoo::raw('CASE WHEN last_seen_at IS NOT NULL AND last_seen_at > NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END')
        ], [
            "id" => $uniqueFriendIds,
            "ORDER" => ["full_name" => "ASC"],
            "LIMIT" => $limit
        ]);
    }
    
    /**
     * --- MODIFIED ---
     * This method also now asks the database to calculate the online status.
     */
    private function getAllUsers(int $limit) {
         // --- MODIFIED ---
         // Same change as getFriends(), asking the database for the 'is_online' status.
         return $this->db->select("users", [
            "id", "username", "full_name", "profile_picture",
            "is_online" => Medoo::raw('CASE WHEN last_seen_at IS NOT NULL AND last_seen_at > NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END')
        ], [
            "AND" => ["id[!]" => $this->currentUserId, "status" => "active"],
            "ORDER" => ["full_name" => "ASC"],
            "LIMIT" => $limit
        ]);
    }

    /**
     * No changes needed to the heartbeat function. It's working correctly.
     */
    public function activity() {
        header('Content-Type: application/json');
        if (!$this->currentUserId || !$this->db) {
            http_response_code(401);
            exit;
        }
        $this->db->update('users', 
            ['last_seen_at' => Medoo::raw('NOW()')], 
            ['id' => $this->currentUserId]
        );
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * No changes needed to the avatar generator.
     */
    private function generateFallbackAvatar(string $name, int $size = 32): string {
        // ... (your existing generateFallbackAvatar method code)
        $initial = '?';
        $trimmedName = trim($name);
        if (!empty($trimmedName)) {
            $titlesToRemove = ['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Engr.', 'Pastor', 'Atty.'];
            $nameWithoutTitles = str_ireplace($titlesToRemove, '', $trimmedName);
            $nameWithoutTitles = trim(preg_replace('/\s+/', ' ', $nameWithoutTitles));
            $nameParts = explode(' ', $nameWithoutTitles);
            if (!empty($nameParts[0])) {
                $initial = strtoupper(mb_substr($nameParts[0], 0, 1));
                if (count($nameParts) >= 2 && !empty(end($nameParts))) {
                    $lastInitial = strtoupper(mb_substr(end($nameParts), 0, 1));
                    if ($lastInitial !== $initial && ctype_alpha($lastInitial)) {
                        $initial .= $lastInitial;
                    }
                }
            }
            if (empty($initial) || strlen($initial) > 2 || !preg_match('/^[A-Z]{1,2}$/', $initial)) {
                $firstChar = strtoupper(mb_substr($trimmedName, 0, 1));
                $initial = ctype_alpha($firstChar) ? $firstChar : '?';
            }
        }
        $hueSeed = crc32(strtolower($trimmedName));
        $hue = $hueSeed % 360;
        $bgColor = "hsl({$hue}, 75%, 60%)";
        $textColor = "hsl({$hue}, 25%, 95%)";
        $fontSizePercentage = (mb_strlen($initial) > 1) ? '40' : '50';
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%d" height="%d" role="img" aria-label="Avatar for %s"><rect width="100" height="100" fill="%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="%s" fill="%s" font-weight="bold">%s</text></svg>',
            $size, $size, htmlspecialchars($trimmedName), htmlspecialchars($bgColor), $fontSizePercentage, htmlspecialchars($textColor), htmlspecialchars($initial)
        );
        return 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svg);
    }
}