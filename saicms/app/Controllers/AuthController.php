<?php
namespace App\Controllers;

use Core\Controller;
use IconCaptcha\IconCaptcha;
use Medoo\Medoo; // ✅ Import the correct namespaced class
use DBConnect;

class AuthController extends Controller {

    public function __construct()
    {
        parent::__construct();
                            
    }
    
    public function showLoginForm() {
        $this->view('auth/login');
    }

    public function home(){
    
        if (isset($_SESSION['user'])) {
            $this->view('home');
            exit;
        }
        header('Location: /login');
        exit;
    }

    public function login()
    {
        // Accept either email or username from the form's `email` field.
        $identifier = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Lookup by email OR username so users can log in with either.
        $user = $this->db->get("users", "*", [
            "OR" => ["email" => $identifier, "username" => $identifier]
        ]);

        // DB stores the password in `password_hash` and full name in `fullname`.
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = $user['email'];
            $_SESSION['user_id'] = $user['id'];

            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_full_name'] = $user['fullname'] ?? 'User';

            $_SESSION['user_username'] = $user['username'] ?? '';
            $_SESSION['user_profile_picture'] = $user['avatar'] ?? null;
            
            // Redirect to home on successful login
            header('Location: /');
            exit;
        }

        // Failed login
        $_SESSION['error'] = 'Invalid email or password.';
        // Preserve the submitted identifier for the form (could be email or username).
        $_SESSION['old_email'] = $identifier;
        header('Location: /login');
        exit;
    }

    public function dashboard()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        // --- NEW LOGIC ---
        // Instantiate the RewardsController
        $rewardsController = new RewardsController();
        
        // Get the referral data
        $rewardsData = $rewardsController->getDashboardData();
        
        // Pass the data to the view
        $this->view('dashboard', [
            'rewards' => $rewardsData
        ]);
        // --- END NEW LOGIC ---
    }
    

    public function logout()
    {
        session_destroy();
        header('Location: /login');
        exit;
    }
    
    
    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return $this->view('auth/register');
        }

        $fullName = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $username = explode('@', $email)[0];

        if ($password !== $confirmPassword) {
            $_SESSION['error'] = "Passwords do not match.";
            $_SESSION['old_email'] = $email;
            header('Location: /register');
            exit;
        }

        if ($this->db->has("users", ["email" => $email])) {
            $_SESSION['error'] = "Email is already registered.";
            $_SESSION['old_email'] = $email;
            header('Location: /register');
            exit;
        }

        $insert_id = $this->createUser([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName,
        ]);

        if($insert_id){
            // Set session to simulate login
            $_SESSION['user'] = $email;
            $_SESSION['user_id'] = $insert_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_full_name'] = $fullName;

            // --- NEW: Referral Processing Logic ---
            // Check if a referral ID exists in the session from their initial visit.
            if (isset($_SESSION['referral_id'])) {
                $referrerId = (int)$_SESSION['referral_id'];
                $referredUserId = $insert_id;

                // A user cannot refer themselves.
                if ($referrerId !== $referredUserId) {
                    // Call our new method to create the referral record.
                    $this->createReferralRecord($referrerId, $referredUserId);
                }

                // IMPORTANT: Clean up the session variable to prevent it from being used again.
                unset($_SESSION['referral_id']);
            }
            // --- End of New Logic ---
        }

        // Redirect to dashboard instead of login
        header('Location: /');
        exit;
    }

    /**
     * Creates a referral record in the database.
     *
     * @param int $referrerId The ID of the user who referred.
     * @param int $referredUserId The ID of the new user who was referred.
     * @return void
     */
    private function createReferralRecord(int $referrerId, int $referredUserId): void
    {
        // Basic validation
        if ($referrerId <= 0 || $referredUserId <= 0) {
            return;
        }

        // Use a try-catch block for robustness in case of DB errors (like unique key violation)
        try {
            $this->db->insert('referrals', [
                'referrer_id' => $referrerId,
                'referred_user_id' => $referredUserId,
                // The 'status' and 'created_at' fields will use their database defaults.
            ]);
            error_log("Referral record created: Referrer #{$referrerId} -> New User #{$referredUserId}");
        } catch (\Exception $e) {
            // Log the error if the insert fails (e.g., duplicate entry)
            error_log("Failed to create referral record. Error: " . $e->getMessage());
        }

        // Here, you could also trigger a notification for the referrer!
        // Example: $this->createNotification($referrerId, "Congratulations, {$newUserName} joined using your link!");
    }

    /**
     * Creates a new user in the database.
     *
     * @param array $data Associative array of user data:
     *                    'username', 'email', 'password', 'full_name'
     * @return int|null The ID of the newly created user, or null on failure.
     */
    public function createUser(array $data): ?int
    {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            error_log("DBConnect::createUser Error: Missing username, email, or password.");
            return null;
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            error_log("DBConnect::createUser Error: Password hashing failed.");
            return null;
        }

        $insertData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $hashedPassword, // Store the hash in `password_hash`
            'fullname' => $data['full_name'] ?? $data['fullname'] ?? null, // DB column is `fullname`
            'status' => 'active',                 // Default status
            'role_id' => $data['role_id'] ?? 5,   // Use `role_id` (default 5)
            // 'created_at' and 'updated_at' should ideally be handled by DB defaults (e.g., DEFAULT CURRENT_TIMESTAMP)
            // If not, you can add:
            // 'created_at' => Medoo::raw('NOW()'),
            // 'updated_at' => Medoo::raw('NOW()')
        ];

        // Perform the insert operation
        $statement = $this->db->insert('users', $insertData);

        // Check if the insert was successful
        if (!$statement || $statement->rowCount() === 0) {
            $errorInfo = $this->db->error; // Get Medoo's error info array
            $errorMessage = $errorInfo ? ($errorInfo[2] ?? json_encode($errorInfo)) : "Unknown insert error";
            error_log("DBConnect::createUser Error: Failed to insert user into database. Error: {$errorMessage}. Query: " . $this->db->last());
            return null; // Indicate failure
        }

        // If insert was successful, get the last inserted ID
        $lastId = $this->db->id();

        if ($lastId && is_numeric($lastId) && (int)$lastId > 0) {
            error_log("DBConnect::createUser: User created successfully with ID: {$lastId}");
            return (int)$lastId;
        } else {
            // This case is problematic: insert reported success (rowCount > 0) but id() is invalid.
            // This might happen if the table 'users' doesn't have a primary key named 'id'
            // or if the driver has issues with lastInsertId(). Your 'users' table does.
            error_log("DBConnect::createUser Error: User inserted, but failed to retrieve a valid new user ID. Medoo->id() returned: " . var_export($lastId, true) . ". This may indicate an issue with the primary key or DB driver configuration.");
            // You might attempt a SELECT to get the user by email/username if absolutely needed here as a fallback,
            // but generally, this indicates a more fundamental issue if id() fails after a successful insert.
            return null; 
        }
    }

    public function classroom(){
    
        if (isset($_SESSION['user'])) {
            $this->view('classroom');
            exit;
        }
    }

}
?>