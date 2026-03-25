<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

class CodeController
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Ginto\Core\Database::getInstance();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * GET /code
     */
    public function code(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            exit;
        }

        $isLoggedIn = !empty($_SESSION['user_id']);
        $username = $_SESSION['user'] ?? null;

        // Determine expiry time if provided via session (controller-level source)
        $expiryTime = null;
        if (!empty($_SESSION['code_access_expires_at'])) {
            $expiryTime = (string)$_SESSION['code_access_expires_at'];
        }

        // Determine if this user has an active paid plan (bypasses prompt limit)
        $isPaid = false;
        $isAdmin = false;
        $userId = $isLoggedIn ? (int)$_SESSION['user_id'] : null;
        if ($userId) {
            $userRow = $this->db->get('users', ['payment_status', 'is_admin', 'role_id'], ['id' => $userId]);
            $isPaid  = ($userRow['payment_status'] ?? 'free') === 'paid';
            $isAdmin = !empty($userRow['is_admin']) || in_array((int)($userRow['role_id'] ?? 0), [1, 2]);
        }

        // Prompt usage for non-paid, non-admin users
        $promptsUsed      = 0;
        $promptsRemaining = \Ginto\Helpers\CodePromptLimiter::FREE_LIMIT;
        if (!$isPaid && !$isAdmin) {
            $limiter          = new \Ginto\Helpers\CodePromptLimiter($this->db);
            $promptsUsed      = $limiter->getUsed($userId);
            $promptsRemaining = max(0, \Ginto\Helpers\CodePromptLimiter::FREE_LIMIT - $promptsUsed);
        }

        View::view('code/code', [
            'title'            => 'Code Editor',
            'isLoggedIn'       => $isLoggedIn,
            'username'         => $username,
            'expiryTime'       => $expiryTime,
            'isPaid'           => $isPaid,
            'isAdmin'          => $isAdmin,
            'promptsUsed'      => $promptsUsed,
            'promptsRemaining' => $promptsRemaining,
            'freeLimit'        => \Ginto\Helpers\CodePromptLimiter::FREE_LIMIT,
        ]);
    }
}
