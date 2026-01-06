<?php
namespace Ginto\Models;

use Ginto\Core\Database; // Our Medoo wrapper
use Medoo\Medoo;
use Exception;

class User
{
    private Medoo $db;
    private string $table = 'users';

    public function __construct()
    {
        // Get the Medoo instance from our Database wrapper
        $this->db = Database::getInstance();
    }

    /**
     * Finds a user by ID.
     */
    public function find(int $id): ?array
    {
        return $this->db->get($this->table, '*', ['id' => $id]);
    }

    /**
     * Finds a user by username, email, or phone number.
     * @param string $identifier The username, email, or phone to search for
     * @return array|null The user data if found, null otherwise
     */
    public function findByCredentials(string $identifier): ?array
    {
        // Clean phone number to remove any non-numeric characters if it looks like a phone number
        $cleanPhone = preg_replace('/\D/', '', $identifier);
        // Build OR conditions. Only include phone LIKE when the cleaned phone has enough digits
        // to avoid accidental matches when an email (which cleans to an empty string) is used.
        $or = [
            'username' => $identifier,
            'email' => $identifier,
        ];

        // Only attempt a phone LIKE match when the cleaned phone is at least 3 digits long.
        // This prevents an empty string (or very short values) from producing a LIKE '%%' which
        // could match all rows and falsely indicate the email exists.
        if (is_string($cleanPhone) && strlen($cleanPhone) >= 3) {
            $or['phone[~]'] = $cleanPhone; // Use LIKE for flexible phone matching
        }

        return $this->db->get($this->table, '*', [
            'OR' => $or
        ]);
    }

    /**
     * Registers a new user and creates their referral record.
     * @param array $data Contains fullname, username, email, password, referrer_id, country, phone.
     * @return int|false The new user id on success, or false on failure
     */
    public function register(array $data)
    {

        // 1. Hash the password for security
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']); // Remove raw password

        // 2. Set default values - determine ginto_level based on package purchased
        // Map package names to levels: Starter=1, Professional/Basic=2, Executive/Silver=3, Gold=4, Platinum=5
        $packageLevelMap = [
            'Starter' => 1,
            'Basic' => 2,
            'Professional' => 2,
            'Silver' => 3,
            'Executive' => 3,
            'Gold' => 4,
            'Platinum' => 5
        ];
        $package = $data['package'] ?? 'Starter';
        $data['ginto_level'] = $packageLevelMap[$package] ?? 0;
        $data['created_at'] = date('Y-m-d H:i:s');

        // 3. Ensure first/middle/last name fields are set (for DB columns)
        $data['firstname'] = $data['firstname'] ?? null;
        $data['middlename'] = $data['middlename'] ?? null;
        $data['lastname'] = $data['lastname'] ?? null;

        error_log('User registration data: ' . json_encode($data));

        // 3. Start manual transaction
        $this->db->pdo->beginTransaction();
        
        try {
            error_log('Starting user registration transaction');

            // Ensure a public alphanumeric id exists for public profile URLs
            if (empty($data['public_id'])) {
                // Try generating a 16-character hex id and ensure uniqueness
                $tries = 0;
                do {
                    $candidate = bin2hex(random_bytes(8)); // 16 hex chars
                    $exists = $this->db->count($this->table, ['public_id' => $candidate]);
                    $tries++;
                } while ($exists && $tries < 5);
                if (!$exists) $data['public_id'] = $candidate;
            }

            // Insert the user
            $insert = $this->db->insert($this->table, $data);
            error_log('User insert result: ' . json_encode(['rowCount' => $insert->rowCount(), 'lastInsertId' => $this->db->id()]));
            
            if ($insert->rowCount() === 0) {
                error_log('User insert failed - no rows affected');
                $this->db->pdo->rollBack();
                return false;
            }

            $newUserId = $this->db->id();
            error_log('New user ID: ' . $newUserId);

            // Note: Referrals are tracked via referrer_id in users table
            // Note: Referrals are tracked via referrer_id in users table
            // Create an orders entry recording the purchased package (default to 'Gold')
            try {
                // Build metadata for payment tracking
                $orderMetadata = [];
                if (!empty($data['paypal_order_id'])) {
                    $orderMetadata['paypal_order_id'] = $data['paypal_order_id'];
                    $orderMetadata['payment_gateway'] = 'paypal';
                }
                if (!empty($data['paypal_payment_status'])) {
                    $orderMetadata['paypal_payment_status'] = $data['paypal_payment_status'];
                }
                if (!empty($data['pay_method'])) {
                    $orderMetadata['pay_method'] = $data['pay_method'];
                }
                
                $orderData = [
                    'user_id' => $newUserId,
                    'package' => $data['package'] ?? 'Gold',
                    // Default amount should be 10000 per request
                    'amount' => isset($data['package_amount']) ? $data['package_amount'] : 10000.0,
                    'currency' => $data['package_currency'] ?? 'PHP',
                    'status' => 'completed',
                    'metadata' => !empty($orderMetadata) ? json_encode($orderMetadata) : null,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                // Insert orders table if available; if not, this will throw and be caught.
                $this->db->insert('orders', $orderData);
                error_log('Order record created for user ' . $newUserId . ' package=' . ($orderData['package'] ?? 'Gold') . (isset($orderMetadata['paypal_order_id']) ? ' paypal_order=' . $orderMetadata['paypal_order_id'] : ''));
            } catch (Exception $e) {
                // Do not fail registration if order insertion fails; just log.
                error_log('Failed to create order record for user ' . $newUserId . ': ' . $e->getMessage());
            }

            error_log('User created successfully and order recorded (if orders table exists)');

            // Commit the transaction
            $this->db->pdo->commit();
            error_log('Registration transaction successful, returning user ID: ' . $newUserId);
            return $newUserId;
            
        } catch (Exception $e) {
            error_log('Registration transaction failed with exception: ' . $e->getMessage());
            $this->db->pdo->rollBack();
            return false;
        }
    }

    /**
     * Retrieves all users referred by a specific user (Level 1 Downline).
     * @param int $referrerId The ID of the referrer.
     * @param int|null $limit Optional limit for the number of results.
     */
    public function getDirectReferrals(int $referrerId, ?int $limit = null): array
    {
        $where = ['referrer_id' => $referrerId];
        if ($limit !== null) {
            $where['LIMIT'] = $limit;
        }

        return $this->db->select($this->table, ['id', 'fullname', 'username', 'ginto_level', 'created_at'], $where);
    }
    
    /**
     * Counts all users referred by a specific user (Level 1 Downline).
     */
    public function countDirectReferrals(int $referrerId): int
    {
        return $this->db->count($this->table, [
            'referrer_id' => $referrerId
        ]);
    }

    /**
     * Retrieves the single most recent user referred by a specific user.
     * @param int $referrerId The ID of the referrer.
     * @return array|null The user data if found, null otherwise.
     */
    public function getLastDirectReferral(int $referrerId): ?array
    {
        return $this->db->get($this->table, ['id', 'fullname', 'username', 'email', 'ginto_level', 'created_at'], [
            'referrer_id' => $referrerId,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => 1
        ]);
    }

    /**
     * Get network tree data for a user with specified depth
     * @param int $userId The root user ID
     * @param int $depth Maximum depth to retrieve (default: 3)
     * @return array Network tree data
     */
    public function getNetworkTree(int $userId, int $depth = 3): array
    {
        return $this->buildNetworkTree($userId, 0, $depth);
    }

    /**
     * Recursively build network tree
     * @param int $userId Current user ID
     * @param int $currentDepth Current depth level
     * @param int $maxDepth Maximum depth to build
     * @return array User data with children
     */
    private function buildNetworkTree(int $userId, int $currentDepth, int $maxDepth): array
    {
        // Get user data
        $user = $this->find($userId);
        if (!$user) {
            return [];
        }

        // Get direct referrals count
        $directReferralsCount = $this->countDirectReferrals($userId);

        // Compute commission/earnings sums from orders table.
        // The system uses `orders` (completed purchases) as the authoritative source
        // rather than maintaining redundant `transactions` commission rows.
        $totalCommissions = 0;
        $monthlyCommissions = 0;
        try {
            $db = $this->db;
            // Sum completed orders amounts for this user as the base earnings
            $totalCommissions = $db->sum('orders', 'amount', [
                'user_id' => $userId,
                'status' => 'completed'
            ]) ?: 0;

            // Sum completed orders within this month
            $monthlyCommissions = $db->sum('orders', 'amount', [
                'user_id' => $userId,
                'status' => 'completed',
                'created_at[>=]' => date('Y-m-01 00:00:00'),
                'created_at[<=]' => date('Y-m-t 23:59:59')
            ]) ?: 0;
        } catch (Exception $e) {
            // Orders table might not exist or query may fail; continue with 0 values
            error_log('Warning: failed to compute commissions from orders: ' . $e->getMessage());
        }

        // Ensure phone and country are explicitly fetched (defensive)
        $profile = $this->db->get($this->table, ['phone', 'country'], ['id' => $userId]);

        // Debug: write profile values to a temp log (temporary)
        @file_put_contents('/tmp/ginto_buildNetworkTree.log', "user={$userId} profile_phone=" . ($profile['phone'] ?? 'NULL') . " profile_country=" . ($profile['country'] ?? 'NULL') . "\n", FILE_APPEND);

        // Prepare user data
        $userData = [
            'id' => $user['id'],
            'public_id' => $user['public_id'] ?? '',
            // camelCase alias for front-end compatibility
            'publicId' => $user['public_id'] ?? '',
            'fullname' => $user['fullname'] ?? 'Unknown',
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'phone' => $profile['phone'] ?? ($user['phone'] ?? ''),
            'country' => $profile['country'] ?? ($user['country'] ?? ''),
            'ginto_level' => $user['ginto_level'] ?? 0,
            'created_at' => $user['created_at'] ?? '',
            'level' => $currentDepth,
            'status' => $user['status'] ?? 'active',
            'joinDate' => $user['created_at'] ?? '',
            'referrerId' => $user['referrer_id'] ?? null,
            'directReferrals' => $directReferralsCount,
            'totalCommissions' => floatval($totalCommissions),
            'monthlyCommissions' => floatval($monthlyCommissions),
            'children' => []
        ];

        // Attach referrer/sponsor info for frontend convenience.
        $referrer = null;
        $refId = $user['referrer_id'] ?? null;
        if ($refId) {
            try {
                $refRow = $this->find((int)$refId);
                if ($refRow) {
                    $referrer = [
                        'id' => $refRow['id'],
                        'username' => $refRow['username'] ?? '',
                        'fullname' => $refRow['fullname'] ?? ''
                    ];
                }
            } catch (Exception $e) {
                // Ignore if referrer lookup fails; keep referrer as null
            }
        }

        $userData['referrer'] = $referrer;
        $userData['sponsor'] = $referrer ? ($referrer['username'] ?: $referrer['fullname']) : '';
        $userData['referrer_username'] = $referrer['username'] ?? null;

        // If we haven't reached max depth, get children
        if ($currentDepth < $maxDepth) {
            $children = $this->getDirectReferrals($userId);
            foreach ($children as $child) {
                $childTree = $this->buildNetworkTree($child['id'], $currentDepth + 1, $maxDepth);
                if (!empty($childTree)) {
                    $userData['children'][] = $childTree;
                }
            }
        }

        return $userData;
    }
}