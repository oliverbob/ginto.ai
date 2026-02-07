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

        View::view('code/code', [
            'title' => 'Code Editor',
            'isLoggedIn' => $isLoggedIn,
            'username' => $username,
            'expiryTime' => $expiryTime,
        ]);
    }
}
