<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

/**
 * Sandbox Controller
 * Handles sandbox-related routes: status, destroy, install, start, call
 */
class SandboxController
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
     * Check LXD installation progress (admin only)
     */
    public function imageInstallStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Admin check
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        
        // Check for status file
        $gintoRoot = dirname(__DIR__, 2);
        $statusFile = dirname($gintoRoot) . '/storage/.image_install_status';
        $logFile = $gintoRoot . '/install.log';
        
        if (!file_exists($statusFile)) {
            echo json_encode([
                'success' => true,
                'status' => 'not_started',
                'message' => 'No installation status found. Installation has not been started.',
                'ready_for_sandbox' => false
            ]);
            exit;
        }
        
        $statusContent = file_get_contents($statusFile);
        $status = json_decode($statusContent, true);
        
        if (!$status) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid status file format'
            ]);
            exit;
        }
        
        // Add last few lines of log if available
        $logTail = '';
        if (file_exists($logFile)) {
            $logLines = file($logFile);
            $logTail = implode('', array_slice($logLines, -20));
        }
        
        echo json_encode([
            'success' => true,
            'status' => $status['status'] ?? 'unknown',
            'step' => $status['step'] ?? null,
            'message' => $status['message'] ?? null,
            'timestamp' => $status['timestamp'] ?? null,
            'ready_for_sandbox' => $status['ready_for_sandbox'] ?? false,
            'log_tail' => $logTail
        ]);
        exit;
    }

    /**
     * Get sandbox status (LXC container status)
     */
    public function status(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Allow both logged-in users (user_id) and visitors (public_id)
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized', 'status' => 'unauthorized']);
            exit;
        }
        
        try {
            // Check if user has a sandbox ID - WITH VALIDATION (clears stale session if sandbox gone)
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (empty($sandboxId)) {
                echo json_encode([
                    'success' => true,
                    'status' => 'not_created',
                    'sandbox_id' => null,
                    'container_status' => null,
                    'message' => 'No sandbox has been created for your account.'
                ]);
                exit;
            }
            
            // Check LXC container status
            $containerExists = \Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId);
            $containerRunning = $containerExists ? \Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId) : false;
            $containerIp = $containerRunning ? \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId) : null;
            
            // Double-check: if container doesn't exist, clear session and return not_created
            if (!$containerExists) {
                unset($_SESSION['sandbox_id']);
                echo json_encode([
                    'success' => true,
                    'status' => 'not_created',
                    'sandbox_id' => null,
                    'container_status' => null,
                    'message' => 'Your sandbox session expired. Click "My Files" to create a new one.'
                ]);
                exit;
            }
            
            $containerStatus = 'not_installed';
            if ($containerExists && $containerRunning) {
                $containerStatus = 'running';
            } elseif ($containerExists) {
                $containerStatus = 'stopped';
            }
            
            echo json_encode([
                'success' => true,
                'status' => $containerStatus === 'running' ? 'ready' : ($containerExists ? 'installed' : 'not_installed'),
                'sandbox_id' => $sandboxId,
                'container_status' => $containerStatus,
                'container_ip' => $containerIp,
                'sandbox_path' => $sandboxId,
                'message' => $containerStatus === 'running' 
                    ? 'Your sandbox is running and ready to use.'
                    : ($containerExists ? 'Your sandbox is installed but not running.' : 'Sandbox directory exists but LXC container not installed.')
            ]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to check sandbox status: ' . $e->getMessage(),
                'status' => 'error'
            ]);
            exit;
        }
    }

    /**
     * Destroy sandbox completely (container + DB + Redis + session)
     */
    public function destroy(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Parse JSON body if Content-Type is application/json
        $data = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            if (is_array($jsonData)) {
                $data = $jsonData;
            }
        }
        
        // CSRF validation
        $token = $data['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        try {
            // Get sandbox ID from session
            $sandboxId = $_SESSION['sandbox_id'] ?? null;
            
            if (empty($sandboxId)) {
                echo json_encode(['success' => true, 'message' => 'No sandbox to destroy']);
                exit;
            }
            
            // Delete sandbox completely (container + DB + Redis + directory)
            $result = \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);
            
            // Clear session data
            unset($_SESSION['sandbox_id']);
            unset($_SESSION['sandbox_created_at']);
            
            // For visitors, also clear the session timestamp to give a fresh start
            if (empty($_SESSION['user_id'])) {
                unset($_SESSION['session_created_at']);
            }
            
            error_log("[/sandbox/destroy] Destroyed sandbox: {$sandboxId}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Sandbox destroyed completely',
                'sandbox_id' => $sandboxId
            ]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to destroy sandbox: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Install/Create LXC sandbox container
     */
    public function install(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Parse JSON body if Content-Type is application/json
        $data = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            if (is_array($jsonData)) {
                $data = $jsonData;
            }
        }
        
        // Allow both logged-in users (user_id) and visitors (public_id)
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $data['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        // Check if user accepted terms
        $acceptedTerms = !empty($data['accept_terms']) && ($data['accept_terms'] === '1' || $data['accept_terms'] === true || $data['accept_terms'] === 1);
        if (!$acceptedTerms) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You must accept the terms and conditions to create a sandbox.']);
            exit;
        }
        
        try {
            // PRE-FLIGHT CHECK: Is LXC/LXD installed and configured?
            $lxcStatus = \Ginto\Helpers\LxdSandboxManager::checkLxcAvailability();
            if (!$lxcStatus['available']) {
                echo json_encode([
                    'success' => false,
                    'error' => $lxcStatus['message'],
                    'error_code' => $lxcStatus['error'],
                    'install_required' => true,
                    'install_command' => $lxcStatus['install_command'],
                    'step' => 'lxc_check'
                ]);
                exit;
            }
            
            // Step 1: Create sandbox directory and database entry (without starting container)
            putenv('GINTO_SKIP_SANDBOX_START=1');
            
            // Force sandbox mode for this session (including admins who click "My Files")
            $_SESSION['playground_use_sandbox'] = true;
            
            $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            putenv('GINTO_SKIP_SANDBOX_START');
            
            $sandboxId = basename($sandboxRoot);
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['sandbox_id'] = $sandboxId;
            
            // Step 2: Create LXC container
            $result = \Ginto\Helpers\LxdSandboxManager::createSandbox($sandboxId, [
                'cpu' => '1',
                'memory' => '256MB',
                'packages' => ['php82', 'php82-fpm', 'caddy', 'mysql-client', 'git', 'nodejs', 'npm']
            ]);
            
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? 'Failed to create LXC container';
                
                // Add nesting hint if it looks like a nesting/forkstart error
                if (stripos($errorMessage, 'forkstart') !== false || stripos($errorMessage, 'failed to run') !== false) {
                    $errorMessage .= ' (Nesting may not be enabled. Run on HOST: lxc profile set default security.nesting=true OR lxc config set <container-name> security.nesting=true)';
                }
                
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'sandbox_id' => $sandboxId,
                    'step' => 'container_creation'
                ]);
                exit;
            }
            
            // Step 3: Get container name
            $containerName = \Ginto\Helpers\LxdSandboxManager::containerName($sandboxId);
            
            // Record acceptance of terms in database
            if ($this->db) {
                try {
                    $this->db->update('client_sandboxes', [
                        'terms_accepted_at' => date('Y-m-d H:i:s'),
                        'container_created_at' => date('Y-m-d H:i:s'),
                        'container_name' => $containerName,
                        'container_status' => 'running',
                        'last_accessed_at' => date('Y-m-d H:i:s')
                    ], ['sandbox_id' => $sandboxId]);
                    
                    // Persist sandbox mode preference for logged-in users
                    if (!empty($_SESSION['user_id'])) {
                        $this->db->update('users', ['playground_use_sandbox' => 1], ['id' => $_SESSION['user_id']]);
                    }
                } catch (\Throwable $_) {}
            }
            
            echo json_encode([
                'success' => true,
                'sandbox_id' => $sandboxId,
                'container_name' => $result['sandboxId'],
                'container_ip' => $result['ip'],
                'status' => 'running',
                'message' => 'Your sandbox has been created and is now running!'
            ]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to install sandbox: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Start an existing sandbox
     */
    public function start(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Allow both logged-in users (user_id) and visitors (public_id)
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        // CSRF validation
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        try {
            $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getSandboxRootIfExists($this->db ?? null, $_SESSION ?? null, true);
            if (empty($sandboxRoot)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'No sandbox found. Your session may have expired.', 'needs_setup' => true]);
                exit;
            }
            
            $sandboxId = basename($sandboxRoot);
            $started = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId, $sandboxRoot);
            
            if ($started) {
                $ip = \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId);
                echo json_encode([
                    'success' => true,
                    'sandbox_id' => $sandboxId,
                    'container_ip' => $ip,
                    'status' => 'running',
                    'message' => 'Sandbox started successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to start sandbox',
                    'sandbox_id' => $sandboxId
                ]);
            }
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error starting sandbox: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Call sandbox-scoped MCP tools
     * This endpoint allows users with active sandboxes to call sandbox_* tools
     * Tools are restricted to sandbox-prefixed tools for security
     * Security restrictions:
     * - Logged-in users only (no visitors)
     * - sandbox_exec requires premium subscription (or admin)
     * - Admin users have no restrictions
     */
    public function call(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        // Check if user is admin (admins bypass all restrictions)
        $isAdmin = \Ginto\Controllers\UserController::isAdmin($_SESSION);
        
        // SECURITY: Require logged-in user for all sandbox tools (unless admin)
        if (!$isAdmin && empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'error' => 'Please log in to use sandbox tools. Create a free account to get started!',
                'action' => 'login'
            ]);
            exit;
        }
        
        // Parse input early to check for special wizard tool
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
            exit;
        }
        
        $tool = $input['tool'] ?? null;
        $args = $input['args'] ?? [];
        
        // SPECIAL CASE: ginto_install runs the server-side installation script
        if ($tool === 'ginto_install') {
            echo json_encode([
                'success' => true,
                'action' => 'ginto_install',
                'message' => 'Starting Ginto installation...',
                'result' => [
                    'action' => 'ginto_install',
                    'command' => 'sudo bash ~/ginto.ai/bin/ginto.sh install',
                    'message' => 'I\'ll start the Ginto installation for you. This will install LXC/LXD and set up the sandbox system. Please run this command in your server\'s SSH terminal: sudo bash ~/ginto.ai/bin/ginto.sh install'
                ]
            ]);
            exit;
        }
        
        // SPECIAL CASE: sandbox_install_wizard doesn't require existing sandbox
        if ($tool === 'sandbox_install_wizard') {
            echo json_encode([
                'success' => true,
                'action' => 'install_sandbox',
                'message' => 'Opening sandbox installation wizard...',
                'result' => [
                    'action' => 'install_sandbox',
                    'message' => 'I\'ll open the sandbox installation wizard for you now.'
                ]
            ]);
            exit;
        }
        
        // Get sandbox ID
        $sandboxId = $_SESSION['sandbox_id'] ?? null;
        if (empty($sandboxId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No active sandbox. Please create a sandbox first by clicking "My Files".']);
            exit;
        }
        
        // Verify sandbox exists
        if (!\Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Sandbox not found. It may have been destroyed. Please create a new one.']);
            exit;
        }
        
        if (empty($tool)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing tool parameter']);
            exit;
        }
        
        // Security: Only allow sandbox-prefixed tools
        if (!str_starts_with($tool, 'sandbox_')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied. Only sandbox tools are allowed for non-admin users.']);
            exit;
        }
        
        // SECURITY: sandbox_exec requires premium subscription (unless admin)
        if (!$isAdmin && $tool === 'sandbox_exec') {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $isPremium = false;
            
            if ($userId > 0) {
                // Check if user has active subscription
                $activeSub = $this->db->get('user_subscriptions', ['id', 'plan_id'], [
                    'user_id' => $userId,
                    'status' => 'active',
                    'OR' => [
                        'expires_at' => null,
                        'expires_at[>]' => date('Y-m-d H:i:s')
                    ]
                ]);
                $isPremium = !empty($activeSub);
            }
            
            if (!$isPremium) {
                http_response_code(403);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Command execution (sandbox_exec) requires a Premium subscription. Upgrade to unlock this powerful feature!',
                    'action' => 'upgrade',
                    'upgrade_url' => '/upgrade'
                ]);
                exit;
            }
        }
        
        // Ensure handlers are loaded
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        foreach (glob($root . '/src/Handlers/*.php') as $f) {
            require_once $f;
        }
        
        try {
            $result = \App\Core\McpInvoker::invoke($tool, $args);
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/sandbox/call', 'tool' => $tool, 'sandbox_id' => $sandboxId]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Tool execution failed: ' . $e->getMessage()]);
        }
        exit;
    }
}
