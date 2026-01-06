<?php
namespace Ginto\Controllers;

use Ginto\Core\View;
use Ginto\Helpers\UnifiedSandbox;

/**
 * Sandbox Controller
 * Handles sandbox-related routes: status, destroy, install, start, call
 * 
 * Supports both LXD and Docker backends via UnifiedSandbox abstraction.
 * Docker mode has limited features (no VNC/Console).
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
            $isVisitor = empty($_SESSION['user_id']);
            $sandboxId = null;
            
            if ($isVisitor) {
                // VISITORS: All share ONE sandbox (lookup by public_id = 'visitor')
                if ($this->db) {
                    $sharedSandbox = $this->db->get('client_sandboxes', ['sandbox_id'], [
                        'public_id' => 'visitor',
                        'user_id' => null,
                        'ORDER' => ['id' => 'DESC']
                    ]);
                    if (!empty($sharedSandbox['sandbox_id'])) {
                        $sandboxId = $sharedSandbox['sandbox_id'];
                    }
                }
            } else {
                // LOGGED-IN USERS: Check their own sandbox
                $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            }
            
            if (empty($sandboxId)) {
                echo json_encode([
                    'success' => true,
                    'status' => 'not_created',
                    'sandbox_id' => null,
                    'container_status' => null,
                    'backend' => UnifiedSandbox::getBackend(),
                    'message' => 'No sandbox has been created for your account.'
                ]);
                exit;
            }
            
            // Check container status using unified sandbox abstraction (works with both LXD and Docker)
            $backend = UnifiedSandbox::getBackend();
            $containerExists = UnifiedSandbox::exists($sandboxId);
            $containerRunning = $containerExists ? UnifiedSandbox::isRunning($sandboxId) : false;
            $containerIp = $containerRunning ? UnifiedSandbox::getIp($sandboxId) : null;
            
            // Double-check: if container doesn't exist, clean up stale data and return not_created
            if (!$containerExists) {
                // Clear session
                unset($_SESSION['sandbox_id']);
                
                // Delete stale database entry to prevent recurring lookups
                if ($this->db) {
                    try {
                        $this->db->delete('client_sandboxes', ['sandbox_id' => $sandboxId]);
                    } catch (\Throwable $_) {}
                }
                
                echo json_encode([
                    'success' => true,
                    'status' => 'not_created',
                    'sandbox_id' => null,
                    'container_status' => null,
                    'backend' => $backend,
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
                'backend' => $backend,
                'sandbox_path' => $sandboxId,
                'message' => $containerStatus === 'running' 
                    ? 'Your sandbox is running and ready to use.'
                    : ($containerExists ? 'Your sandbox is installed but not running.' : 'Sandbox container not installed.')
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
            
            // Delete sandbox completely using unified interface (works with both LXD and Docker)
            $result = UnifiedSandbox::deleteCompletely($sandboxId, $this->db);
            
            // Clear session data
            unset($_SESSION['sandbox_id']);
            unset($_SESSION['sandbox_created_at']);
            
            // For visitors, also clear the session timestamp to give a fresh start
            if (empty($_SESSION['user_id'])) {
                unset($_SESSION['session_created_at']);
            }
            
            error_log("[/sandbox/destroy] Destroyed sandbox: {$sandboxId} (backend: " . ($result['backend'] ?? 'unknown') . ")");
            
            echo json_encode([
                'success' => true,
                'message' => 'Sandbox destroyed completely',
                'sandbox_id' => $sandboxId,
                'backend' => $result['backend'] ?? UnifiedSandbox::getBackend()
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
        
        // LOGGED-IN USER SANDBOX LIMIT: Users get ONE sandbox per account
        if (!empty($_SESSION['user_id']) && $this->db) {
            // First check by user_id
            $existingSandbox = $this->db->get('client_sandboxes', ['sandbox_id'], [
                'user_id' => $_SESSION['user_id'],
                'ORDER' => ['id' => 'DESC']
            ]);
            
            // If not found by user_id, check by public_id (claim visitor sandbox)
            if (empty($existingSandbox['sandbox_id']) && !empty($_SESSION['public_id'])) {
                $existingSandbox = $this->db->get('client_sandboxes', ['sandbox_id'], [
                    'public_id' => $_SESSION['public_id'],
                    'user_id' => null,  // Only claim unclaimed visitor sandboxes
                    'ORDER' => ['id' => 'DESC']
                ]);
                
                // Claim this sandbox for the logged-in user
                if (!empty($existingSandbox['sandbox_id'])) {
                    $this->db->update('client_sandboxes', [
                        'user_id' => $_SESSION['user_id']
                    ], ['sandbox_id' => $existingSandbox['sandbox_id']]);
                }
            }
            
            if (!empty($existingSandbox['sandbox_id'])) {
                $sandboxId = $existingSandbox['sandbox_id'];
                // Check if container still exists (works with both LXD and Docker)
                if (UnifiedSandbox::exists($sandboxId)) {
                    // Return existing sandbox - users can only have one
                    $_SESSION['sandbox_id'] = $sandboxId;
                    
                    // Check if running, start if not
                    $isRunning = UnifiedSandbox::isRunning($sandboxId);
                    if (!$isRunning) {
                        UnifiedSandbox::start($sandboxId);
                        $isRunning = true;
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'sandbox_id' => $sandboxId,
                        'container_name' => UnifiedSandbox::containerName($sandboxId),
                        'status' => $isRunning ? 'running' : 'stopped',
                        'backend' => UnifiedSandbox::getBackend(),
                        'message' => 'Using your existing sandbox.',
                        'reused' => true
                    ]);
                    exit;
                }
                // Container doesn't exist, delete stale record and create new
                $this->db->delete('client_sandboxes', ['sandbox_id' => $sandboxId]);
            }
        }
        
        // VISITOR SANDBOX: All visitors share ONE sandbox (public_id = 'visitor')
        $isVisitor = empty($_SESSION['user_id']) && !empty($_SESSION['public_id']);
        if ($isVisitor && $this->db) {
            $existingSandbox = $this->db->get('client_sandboxes', ['sandbox_id'], [
                'public_id' => 'visitor',
                'user_id' => null,
                'ORDER' => ['id' => 'DESC']
            ]);
            
            if (!empty($existingSandbox['sandbox_id'])) {
                $sandboxId = $existingSandbox['sandbox_id'];
                // Check if container still exists (works with both LXD and Docker)
                if (UnifiedSandbox::exists($sandboxId)) {
                    // Return existing shared visitor sandbox
                    $_SESSION['sandbox_id'] = $sandboxId;
                    
                    // Check if running, start if not
                    $isRunning = UnifiedSandbox::isRunning($sandboxId);
                    if (!$isRunning) {
                        UnifiedSandbox::start($sandboxId);
                        $isRunning = true;
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'sandbox_id' => $sandboxId,
                        'container_name' => UnifiedSandbox::containerName($sandboxId),
                        'status' => $isRunning ? 'running' : 'stopped',
                        'backend' => UnifiedSandbox::getBackend(),
                        'message' => 'Using shared visitor sandbox.',
                        'reused' => true
                    ]);
                    exit;
                }
                // Container doesn't exist, delete stale record and create new
                $this->db->delete('client_sandboxes', ['sandbox_id' => $sandboxId]);
            }
        }
        
        try {
            // PRE-FLIGHT CHECK: Is sandbox system (LXD or Docker) available?
            $backend = UnifiedSandbox::getBackend();
            $availabilityCheck = UnifiedSandbox::checkAvailability();
            if (!$availabilityCheck['available']) {
                echo json_encode([
                    'success' => false,
                    'error' => $availabilityCheck['message'] ?? 'No sandbox backend available',
                    'error_code' => $availabilityCheck['error'] ?? 'no_backend',
                    'install_required' => true,
                    'install_command' => $availabilityCheck['install_command'] ?? null,
                    'backend' => $backend,
                    'step' => 'backend_check'
                ]);
                exit;
            }
            
            // Step 1: Create sandbox directory and database entry (without starting container)
            putenv('GINTO_SKIP_SANDBOX_START=1');
            
            // Force sandbox mode for this session (including admins who click "My Files")
            $_SESSION['playground_use_sandbox'] = true;
            
            // For visitors, we create a shared sandbox with public_id = 'visitor'
            if ($isVisitor) {
                // Generate a unique sandbox ID
                $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                $sandboxId = '';
                for ($i = 0; $i < 12; $i++) {
                    $sandboxId .= $chars[random_int(0, strlen($chars) - 1)];
                }
                
                // Insert with public_id = 'visitor' for shared access
                $this->db->insert('client_sandboxes', [
                    'sandbox_id' => $sandboxId,
                    'public_id' => 'visitor',
                    'user_id' => null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                $sandboxId = basename($sandboxRoot);
            }
            putenv('GINTO_SKIP_SANDBOX_START');
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['sandbox_id'] = $sandboxId;
            
            // Step 2: Create container using unified sandbox interface (works with both LXD and Docker)
            $result = UnifiedSandbox::create($sandboxId, [
                'cpu' => '1',
                'memory' => '256MB',
                'packages' => ['php82', 'php82-fpm', 'caddy', 'mysql-client', 'git', 'nodejs', 'npm']
            ]);
            
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? 'Failed to create sandbox container';
                
                // Add nesting hint if it looks like a nesting/forkstart error (LXD-specific)
                if ($backend === 'lxd' && (stripos($errorMessage, 'forkstart') !== false || stripos($errorMessage, 'failed to run') !== false)) {
                    $errorMessage .= ' (Nesting may not be enabled. Run on HOST: lxc profile set default security.nesting=true OR lxc config set <container-name> security.nesting=true)';
                }
                
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'sandbox_id' => $sandboxId,
                    'backend' => $backend,
                    'step' => 'container_creation'
                ]);
                exit;
            }
            
            // Step 3: Get container name
            $containerName = UnifiedSandbox::containerName($sandboxId);
            $containerIp = $result['ip'] ?? $result['ip_address'] ?? UnifiedSandbox::getIp($sandboxId);
            
            // Record acceptance of terms in database
            if ($this->db) {
                try {
                    $this->db->update('client_sandboxes', [
                        'terms_accepted_at' => date('Y-m-d H:i:s'),
                        'container_created_at' => date('Y-m-d H:i:s'),
                        'container_name' => $containerName,
                        'container_status' => 'running',
                        'backend_type' => $backend,
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
                'container_name' => $containerName,
                'container_ip' => $containerIp,
                'status' => 'running',
                'backend' => $backend,
                'message' => 'Your sandbox has been created and is now running!'
            ]);
            exit;
            
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to install sandbox: ' . $e->getMessage(),
                'backend' => $backend ?? UnifiedSandbox::getBackend()
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

    /**
     * VNC Connect - Start VNC desktop for user's sandbox and return connection info
     * POST /sandbox/vnc
     */
    public function vnc(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }
        
        // Must be logged in
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
            // Get or create user's sandbox ID (auto-creates for visitors if needed)
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($this->db ?? null, $_SESSION ?? null);
            
            if (empty($sandboxId)) {
                $debugInfo = [
                    'has_db' => !empty($this->db),
                    'has_user_id' => !empty($_SESSION['user_id']),
                    'has_public_id' => !empty($_SESSION['public_id']),
                ];
                echo json_encode(['success' => false, 'error' => 'Failed to get or create sandbox.', 'debug' => $debugInfo]);
                exit;
            }
            
            // Ensure the container exists and is running
            if (!\Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId)) {
                // Create the container
                $createResult = \Ginto\Helpers\LxdSandboxManager::createSandbox($sandboxId);
                if (!$createResult['success']) {
                    echo json_encode(['success' => false, 'error' => 'Failed to create sandbox container: ' . ($createResult['error'] ?? 'Unknown error')]);
                    exit;
                }
            } elseif (!\Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId)) {
                // Container exists but not running - start it
                \Ginto\Helpers\LxdSandboxManager::startSandbox($sandboxId);
            }
            
            // Store in session for future requests
            $_SESSION['sandbox_id'] = $sandboxId;
            
            // Container name is ginto-sandbox-{id}
            $containerName = 'ginto-sandbox-' . $sandboxId;
            
            // Validate container name format
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $containerName)) {
                echo json_encode(['success' => false, 'error' => 'Invalid container name']);
                exit;
            }
            
            // Find LXC binary
            $lxcBin = null;
            foreach (['/snap/bin/lxc', '/usr/bin/lxc', '/usr/local/bin/lxc'] as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $lxcBin = $path;
                    break;
                }
            }
            if (!$lxcBin) {
                $which = trim(shell_exec('which lxc 2>/dev/null') ?? '');
                if (!empty($which) && file_exists($which)) {
                    $lxcBin = $which;
                }
            }
            
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            // Use LXD REST API via Unix socket to check container state
            $socket = '/var/snap/lxd/common/lxd/unix.socket';
            $apiUrl = "http://localhost/1.0/instances/" . urlencode($containerName) . "/state";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $apiData = json_decode($response, true);
            $metadata = $apiData['metadata'] ?? [];
            
            if ($httpCode !== 200 || ($metadata['status'] ?? '') !== 'Running') {
                echo json_encode(['success' => false, 'error' => 'Sandbox is not running. Please start your sandbox first.']);
                exit;
            }
            
            // Get container IP from API response
            $containerIp = null;
            $network = $metadata['network'] ?? [];
            foreach ($network as $iface => $ifData) {
                if ($iface === 'lo') continue;
                foreach ($ifData['addresses'] ?? [] as $addr) {
                    if ($addr['family'] === 'inet') {
                        $containerIp = $addr['address'];
                        break 2;
                    }
                }
            }
            
            if (!$containerIp) {
                echo json_encode(['success' => false, 'error' => 'Container has no IP address']);
                exit;
            }
            
            // Check if VNC server is running in container (port 5900)
            $checkVnc = "sudo $lxcBin exec " . escapeshellarg($containerName) . " -- sh -c 'netstat -tln 2>/dev/null | grep -q :5900 || ss -tln 2>/dev/null | grep -q :5900' 2>/dev/null";
            $vncRunning = shell_exec($checkVnc . " && echo 'yes' || echo 'no'");
            
            // Always ensure XFCE panel config is correct (base image may have old config)
            \Ginto\Helpers\LxdSandboxManager::ensureXfcePanel($containerName);
            
            if (trim($vncRunning) !== 'yes') {
                // Start Xvfb (virtual framebuffer) and then x11vnc
                $startVnc = "sudo $lxcBin exec " . escapeshellarg($containerName) . " -- sh -c '
                    if ! command -v x11vnc >/dev/null 2>&1; then
                        echo \"NO_VNC_SERVER\"
                        exit 1
                    fi
                    
                    # Kill any existing sessions
                    pkill -9 Xvfb 2>/dev/null || true
                    pkill -9 x11vnc 2>/dev/null || true
                    pkill -9 xfce4-session 2>/dev/null || true
                    pkill -9 dbus-daemon 2>/dev/null || true
                    sleep 1
                    
                    # Setup environment
                    export DISPLAY=:0
                    export HOME=/root
                    export XDG_RUNTIME_DIR=/tmp/runtime-root
                    mkdir -p \$XDG_RUNTIME_DIR
                    chmod 700 \$XDG_RUNTIME_DIR
                    
                    # Start Xvfb (virtual X display)
                    Xvfb :0 -screen 0 1280x720x24 &
                    sleep 1
                    
                    # Start dbus for desktop
                    export DBUS_SESSION_BUS_ADDRESS=unix:path=/tmp/dbus-session
                    dbus-daemon --session --address=\"\$DBUS_SESSION_BUS_ADDRESS\" --fork 2>/dev/null || true
                    
                    # Start XFCE desktop
                    startxfce4 &
                    sleep 3
                    
                    # Start x11vnc to share the display
                    x11vnc -display :0 -nopw -forever -shared -bg -rfbport 5900 -xkb
                    
                    echo \"VNC_STARTED\"
                ' 2>&1";
                $result = shell_exec($startVnc);
                
                if (strpos($result, 'NO_VNC_SERVER') !== false) {
                    // VNC not installed - auto-upgrade the sandbox
                    $gintoRoot = dirname(__DIR__, 2);
                    $gintoScript = $gintoRoot . '/bin/ginto.sh';
                    
                    if (file_exists($gintoScript)) {
                        // Run upgrade in background to install VNC packages
                        $upgradeCmd = "sudo bash " . escapeshellarg($gintoScript) . " upgrade " . escapeshellarg($containerName) . " > /tmp/sandbox_upgrade_{$sandboxId}.log 2>&1 &";
                        shell_exec($upgradeCmd);
                        
                        echo json_encode([
                            'success' => false, 
                            'error' => 'VNC server not installed in your sandbox. Upgrading now... Please wait 30-60 seconds and try again.',
                            'action' => 'upgrading',
                            'sandbox_id' => $sandboxId
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false, 
                            'error' => 'VNC server not installed. Please contact support.',
                            'action' => 'upgrade_needed'
                        ]);
                    }
                    exit;
                }
                
                // Wait for VNC to start
                sleep(2);
            }
            
            // Generate a port for websockify (6170-6180 range to match Caddy proxy config)
            $wsPort = 6170 + (abs(crc32($containerName)) % 11);
            
            // Always call start_websockify.sh - it handles checking if already running
            // and will restart if target IP changed (sandbox was recreated)
            $appRoot = dirname(__DIR__, 2);
            $scriptPath = $appRoot . '/bin/start_websockify.sh';
            $webRoot = $appRoot . '/public/lib/novnc';
            $cmd = "bash " . escapeshellarg($scriptPath) . " $wsPort $containerIp:5900 " . escapeshellarg($webRoot) . " 2>&1";
            
            $output = shell_exec($cmd);
            
            // Check if it started
            usleep(500000);
            $checkPort = "ss -tlnp 2>/dev/null | grep -q ':$wsPort ' && echo 'running'";
            $wsRunning = trim(shell_exec($checkPort) ?? '');
            if ($wsRunning !== 'running') {
                error_log("Failed to start websockify for user sandbox: $output");
            }
            
            // Return connection info
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $hostOnly = preg_replace('/:\d+$/', '', $host);
            
            // Verify websockify is running
            $finalCheck = trim(shell_exec("ss -tlnp 2>/dev/null | grep -q ':$wsPort ' && echo 'running'") ?? '');
            
            echo json_encode([
                'success' => true,
                'wsUrl' => 'ws://' . $hostOnly . ':' . $wsPort . '/',
                'port' => $wsPort,
                'path' => '',
                'password' => '',
                'containerIp' => $containerIp,
                'sandboxId' => $sandboxId
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Check if OpenWebUI is installed in user's sandbox
     */
    public function openwebuiStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // Get user's sandbox ID
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode([
                    'success' => true,
                    'installed' => false,
                    'sandbox_exists' => false,
                    'message' => 'No sandbox installed'
                ]);
                exit;
            }
            
            // Check if sandbox exists using UnifiedSandbox (works for both Docker and LXD)
            if (!\Ginto\Helpers\UnifiedSandbox::exists($sandboxId)) {
                echo json_encode([
                    'success' => true,
                    'installed' => false,
                    'sandbox_exists' => false,
                    'message' => 'Sandbox does not exist'
                ]);
                exit;
            }
            
            // Detect backend and home directory
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            // Check if OpenWebUI is installed (marker file or pip package)
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                "(test -f $homeDir/open-webui/.installed || which open-webui > /dev/null 2>&1 || test -f $homeDir/.local/bin/open-webui) && echo 'installed'",
                $homeDir,
                10
            );
            
            $installed = trim($output) === 'installed';
            
            // Check if OpenWebUI is running (port 3000)
            $running = false;
            if ($installed) {
                [$code2, $output2, $err2] = \Ginto\Helpers\UnifiedSandbox::exec(
                    $sandboxId,
                    'pgrep -f "open-webui" > /dev/null 2>&1 && echo "running" || (ss -tlnp 2>/dev/null | grep -q ":3000 " && echo "running")',
                    $homeDir,
                    5
                );
                $running = trim($output2) === 'running';
            }
            
            echo json_encode([
                'success' => true,
                'installed' => $installed,
                'running' => $running,
                'sandbox_exists' => true,
                'sandbox_id' => $sandboxId,
                'backend' => $backend
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Install OpenWebUI in user's sandbox - creates install script for Console to run
     */
    public function openwebuiInstall(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // Get user's sandbox ID
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed. Please create a sandbox first.']);
                exit;
            }
            
            if (!\Ginto\Helpers\UnifiedSandbox::exists($sandboxId)) {
                echo json_encode(['success' => false, 'error' => 'Sandbox does not exist']);
                exit;
            }
            
            // Detect backend and home directory
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            // Create install script - git clone then run
            if ($backend === 'docker') {
                $installScript = <<<'BASH'
#!/bin/bash
set -e
echo "=== Installing OpenWebUI ==="
echo ""

# Install dependencies
echo "Installing dependencies..."
sudo apt-get update -qq
sudo apt-get install -y -qq git python3 python3-pip python3-venv nodejs npm curl

# Clone OpenWebUI
echo ""
echo "Cloning OpenWebUI..."
cd ~
if [ -d "open-webui" ]; then
    echo "Directory exists, pulling latest..."
    cd open-webui && git pull
else
    git clone https://github.com/open-webui/open-webui.git
    cd open-webui
fi

# Install backend dependencies
echo ""
echo "Installing Python dependencies..."
pip3 install -r requirements.txt --user 2>/dev/null || pip3 install -r backend/requirements.txt --user

# Install frontend dependencies
echo ""
echo "Installing Node dependencies..."
cd frontend 2>/dev/null || true
npm install 2>/dev/null || true
cd ..

# Create startup script
cat > ~/start-openwebui.sh << 'EOF'
#!/bin/bash
cd ~/open-webui
export DATA_DIR=~/open-webui-data
export WEBUI_AUTH=false
./start.sh 2>/dev/null || python3 -m open_webui.main --host 0.0.0.0 --port 3000
EOF
chmod +x ~/start-openwebui.sh

# Mark as installed
touch ~/open-webui/.installed

echo ""
echo "=== OpenWebUI Installed Successfully! ==="
echo ""
echo "To start OpenWebUI, run:"
echo "  ~/start-openwebui.sh"
echo ""
echo "Or click 'Start OpenWebUI' in the sidebar."
BASH;
            } else {
                // LXD/Alpine install script
                $installScript = <<<'BASH'
#!/bin/sh
set -e
echo "=== Installing OpenWebUI ==="
echo ""

# Install dependencies
echo "Installing dependencies..."
apk add --no-cache git python3 py3-pip nodejs npm curl

# Clone OpenWebUI
echo ""
echo "Cloning OpenWebUI..."
cd ~
if [ -d "open-webui" ]; then
    echo "Directory exists, pulling latest..."
    cd open-webui && git pull
else
    git clone https://github.com/open-webui/open-webui.git
    cd open-webui
fi

# Install backend dependencies
echo ""
echo "Installing Python dependencies..."
pip3 install -r requirements.txt --user 2>/dev/null || pip3 install -r backend/requirements.txt --user

# Install frontend dependencies  
echo ""
echo "Installing Node dependencies..."
cd frontend 2>/dev/null || true
npm install 2>/dev/null || true
cd ..

# Create startup script
cat > ~/start-openwebui.sh << 'EOF'
#!/bin/sh
cd ~/open-webui
export DATA_DIR=~/open-webui-data
export WEBUI_AUTH=false
./start.sh 2>/dev/null || python3 -m open_webui.main --host 0.0.0.0 --port 3000
EOF
chmod +x ~/start-openwebui.sh

# Mark as installed
touch ~/open-webui/.installed

# Update Caddy to forward port 80 to 3000
cat > /etc/caddy/Caddyfile << 'CADDYEOF'
:80 {
    reverse_proxy localhost:3000
}
CADDYEOF

# Restart Caddy
rc-service caddy restart 2>/dev/null || true

echo ""
echo "=== OpenWebUI Installed Successfully! ==="
echo ""
echo "To start OpenWebUI, run:"
echo "  ~/start-openwebui.sh"
echo ""
echo "Or click 'Start OpenWebUI' in the sidebar."
BASH;
            }
            
            // Write the install script to the sandbox
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                "cat > $homeDir/install-openwebui.sh << 'SCRIPTEOF'\n" . $installScript . "\nSCRIPTEOF\nchmod +x $homeDir/install-openwebui.sh && echo 'SCRIPT_CREATED'",
                $homeDir,
                10
            );
            
            if (strpos($output, 'SCRIPT_CREATED') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Install script created. Run it in Console.',
                    'script' => "$homeDir/install-openwebui.sh",
                    'backend' => $backend
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create install script',
                    'output' => $output,
                    'backend' => $backend
                ]);
            }
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Start OpenWebUI in user's sandbox
     */
    public function openwebuiStart(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed']);
                exit;
            }
            
            // Detect backend and home directory
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            // Start OpenWebUI in background
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                "nohup $homeDir/start-openwebui.sh > $homeDir/openwebui.log 2>&1 & sleep 3 && (pgrep -f 'open_webui' || ss -tlnp 2>/dev/null | grep -q ':3000 ') && echo 'STARTED'",
                $homeDir,
                30
            );
            
            if (strpos($output, 'STARTED') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'OpenWebUI started',
                    'url' => '/clients/' . $sandboxId . '/',
                    'backend' => $backend
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to start OpenWebUI',
                    'output' => $output,
                    'backend' => $backend
                ]);
            }
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Stop OpenWebUI in user's sandbox
     */
    public function openwebuiStop(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed']);
                exit;
            }
            
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                'pkill -f "open_webui" 2>/dev/null; echo "STOPPED"',
                $homeDir,
                10
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'OpenWebUI stopped',
                'backend' => $backend
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

