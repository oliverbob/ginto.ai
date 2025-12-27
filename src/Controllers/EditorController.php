<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

/**
 * Editor Controller
 * Handles editor-related routes: file management, tree view, sandbox toggle
 */
class EditorController
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = \Ginto\Core\Database::getInstance();
        }
        $this->db = $db;
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Editor main page
     */
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            exit;
        }
        
        // Check if user is logged in
        $isLoggedIn = !empty($_SESSION['user_id']);
        
        // Get existing sandbox root for this user (with validation to clear stale session data)
        $sandboxRoot = null;
        $sandboxId = 'unavailable';
        try {
            $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getSandboxRootIfExists($this->db ?? null, $_SESSION ?? null, true);
            if (!empty($sandboxRoot)) {
                $sandboxId = basename($sandboxRoot);
            }
        } catch (\Throwable $e) {
            $sandboxRoot = null;
            $sandboxId = 'unavailable';
        }
        
        // Generate CSRF token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        \Ginto\Core\View::view('editor/editor', [
            'title' => 'My Files',
            'isLoggedIn' => $isLoggedIn,
            'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
            'sandboxRoot' => $sandboxRoot,
            'sandboxId' => $sandboxId,
            'csrfToken' => $_SESSION['csrf_token']
        ]);
    }

    /**
     * Toggle sandbox/repo mode (admin only)
     */
    public function toggleSandbox(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // Check if user is admin
        $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || (!empty($_SESSION['is_admin']));
        if (!$isAdmin && $this->db && !empty($_SESSION['user_id'])) {
            try {
                $ur = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
                if (!empty($ur) && !empty($ur['role_id'])) {
                    $rr = $this->db->get('roles', ['name', 'display_name'], ['id' => $ur['role_id']]);
                    $rname = strtolower((string)($rr['display_name'] ?? $rr['name'] ?? ''));
                    if (in_array($rname, ['administrator', 'admin'], true)) $isAdmin = true;
                }
            } catch (\Throwable $_) {}
        }
        
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden - admin only']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        // Toggle sandbox mode
        $val = ($_POST['use_sandbox'] ?? '') === '1';
        $_SESSION['editor_use_sandbox'] = $val ? true : false;
        
        // Also set playground flag for compatibility
        $_SESSION['playground_use_sandbox'] = $_SESSION['editor_use_sandbox'];
        
        // Persist to DB
        try {
            $this->db->update('users', ['playground_use_sandbox' => $val], ['id' => $_SESSION['user_id']]);
        } catch (\Throwable $_) {}
        
        // Ensure sandbox exists when enabling
        if ($val) {
            try {
                putenv('GINTO_SKIP_SANDBOX_START=1');
                \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                putenv('GINTO_SKIP_SANDBOX_START');
            } catch (\Throwable $_) {}
        }
        
        // Get current sandbox id
        $sandboxId = null;
        try {
            putenv('GINTO_SKIP_SANDBOX_START=1');
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            putenv('GINTO_SKIP_SANDBOX_START');
            $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
            $isAdminRoot = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/'));
            if (!$isAdminRoot) $sandboxId = basename($editorRoot);
        } catch (\Throwable $_) {}
        
        echo json_encode([
            'success' => true,
            'csrf_ok' => true,
            'use_sandbox' => $_SESSION['editor_use_sandbox'] ?? false,
            'sandbox_id' => $sandboxId,
            'csrf_token' => $_SESSION['csrf_token'] ?? null
        ]);
        exit;
    }
}
