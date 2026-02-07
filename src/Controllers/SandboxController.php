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
     * Check if install session is running (admin only)
     * Returns whether ginto.sh install process is currently running
     */
    public function installSessionStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Admin check
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        
        // Check for running ginto.sh install process
        // Use ps + grep with [g] trick to exclude the grep itself
        $output = [];
        $returnCode = 0;
        exec('ps aux | grep "[g]into.sh install" 2>/dev/null', $output, $returnCode);
        
        // Filter out any false positives (lines that are just grep commands)
        $realProcesses = array_filter($output, function($line) {
            return strpos($line, 'grep') === false && strpos($line, 'pgrep') === false;
        });
        
        $processRunning = !empty($realProcesses);
        
        // If status file says "in_progress" but no process is running, clean up the stale status
        $gintoRoot = dirname(__DIR__, 2);
        $statusFile = dirname($gintoRoot) . '/storage/.image_install_status';
        if (!$processRunning && file_exists($statusFile)) {
            $statusContent = @file_get_contents($statusFile);
            $status = @json_decode($statusContent, true);
            if (isset($status['status']) && $status['status'] === 'in_progress') {
                // Stale status file - process died without updating status
                // Update to error state
                $status['status'] = 'error';
                $status['message'] = 'Installation process was interrupted. Please try again.';
                $status['timestamp'] = time();
                @file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
            }
        }
        
        echo json_encode([
            'success' => true,
            'session_exists' => $processRunning,
            'process_running' => $processRunning
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
     * Health check - verify sandbox web server is responding
     * Curls the sandbox IP on port 80 to check if it's ready to serve content
     */
    public function health(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Allow both logged-in users (user_id) and visitors (public_id)
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized', 'healthy' => false]);
            exit;
        }
        
        try {
            $isVisitor = empty($_SESSION['user_id']);
            $sandboxId = null;
            
            if ($isVisitor) {
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
                $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            }
            
            if (empty($sandboxId)) {
                echo json_encode(['success' => true, 'healthy' => false, 'reason' => 'no_sandbox']);
                exit;
            }
            
            // Check if container is running first
            $containerExists = UnifiedSandbox::exists($sandboxId);
            $containerRunning = $containerExists ? UnifiedSandbox::isRunning($sandboxId) : false;
            
            if (!$containerRunning) {
                echo json_encode(['success' => true, 'healthy' => false, 'reason' => 'not_running', 'sandbox_id' => $sandboxId]);
                exit;
            }
            
            // Get container IP
            $containerIp = UnifiedSandbox::getIp($sandboxId);
            
            if (empty($containerIp)) {
                echo json_encode(['success' => true, 'healthy' => false, 'reason' => 'no_ip', 'sandbox_id' => $sandboxId]);
                exit;
            }
            
            // Try to curl the sandbox IP on port 80 with a short timeout
            // Use -I (HEAD) and check if Server header contains Caddy
            $healthy = false;
            $httpCode = 0;
            $serverHeader = '';
            
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "http://{$containerIp}/",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_NOBODY => true, // HEAD request only
                    CURLOPT_HEADER => true, // Include headers in output
                    CURLOPT_FOLLOWLOCATION => false,
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                // Check if response contains Server: Caddy header
                if ($response && preg_match('/Server:\s*Caddy/i', $response)) {
                    $serverHeader = 'Caddy';
                    $healthy = true;
                } elseif ($httpCode > 0) {
                    // Any HTTP response means web server is up
                    $healthy = true;
                }
            }
            
            // Fallback: if curl fails but container is running with IP, try a simple socket test
            if (!$healthy && !empty($containerIp)) {
                $socket = @fsockopen($containerIp, 80, $errno, $errstr, 2);
                if ($socket) {
                    fclose($socket);
                    $healthy = true;
                    $httpCode = -1; // Indicates socket test passed
                }
            }
            
            echo json_encode([
                'success' => true,
                'healthy' => $healthy,
                'sandbox_id' => $sandboxId,
                'container_ip' => $containerIp,
                'http_code' => $httpCode,
                'server' => $serverHeader,
                'curl_error' => $curlError ?? '',
                'reason' => $healthy ? 'ok' : 'no_response'
            ]);
            exit;
            
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'healthy' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Create sandbox asynchronously (non-blocking)
     * 
     * Uses LXD REST API to start container copy in background.
     * Returns immediately with operation ID. Frontend polls /api/sandbox/creation-status.
     */
    public function createAsync(): void
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
        
        // Parse JSON body
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
            // Get or create sandbox ID
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($this->db ?? null, $_SESSION ?? null);
            
            if (empty($sandboxId)) {
                echo json_encode(['success' => false, 'error' => 'Failed to get or create sandbox ID']);
                exit;
            }
            
            // Check if base container exists
            $lxcCheck = \Ginto\Helpers\LxdSandboxManager::checkLxcAvailability();
            if (!$lxcCheck['available']) {
                echo json_encode([
                    'success' => false,
                    'error' => $lxcCheck['message'],
                    'needs_install' => true,
                    'install_command' => $lxcCheck['install_command'] ?? null,
                ]);
                exit;
            }
            
            // Start async creation
            $result = \Ginto\Helpers\LxdSandboxManager::createSandboxAsync($sandboxId);
            
            // Store sandbox ID in session
            $_SESSION['sandbox_id'] = $sandboxId;
            
            echo json_encode([
                'success' => $result['success'],
                'status' => $result['status'],
                'operation' => $result['operation'] ?? null,
                'sandbox_id' => $result['sandboxId'],
                'ip' => $result['ip'] ?? null,
                'message' => $result['message'] ?? null,
                'error' => $result['error'] ?? null,
            ]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error creating sandbox: ' . $e->getMessage()]);
            exit;
        }
    }
    
    /**
     * Get sandbox creation status (for polling)
     * 
     * Called by frontend to check if async creation is complete.
     */
    public function creationStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Allow both logged-in users (user_id) and visitors (public_id)
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        try {
            // Get sandbox ID from session
            $sandboxId = $_SESSION['sandbox_id'] ?? null;
            
            if (empty($sandboxId)) {
                // Try to look up from DB
                $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            }
            
            if (empty($sandboxId)) {
                echo json_encode([
                    'success' => true,
                    'status' => 'not_created',
                    'ready' => false,
                    'error' => null,
                ]);
                exit;
            }
            
            // Get creation status from LxdSandboxManager
            $status = \Ginto\Helpers\LxdSandboxManager::getCreationStatus($sandboxId);
            
            echo json_encode([
                'success' => true,
                'status' => $status['status'],
                'ready' => $status['ready'],
                'ip' => $status['ip'] ?? null,
                'sandbox_id' => $status['sandboxId'] ?? null,
                'progress' => $status['progress'] ?? null,
                'error' => $status['error'] ?? null,
            ]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error checking status: ' . $e->getMessage()]);
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
        
        // Check if user accepted terms (admins are exempt)
        $isAdmin = !empty($_SESSION['is_admin']);
        $acceptedTerms = !empty($data['accept_terms']) && ($data['accept_terms'] === '1' || $data['accept_terms'] === true || $data['accept_terms'] === 1);
        if (!$isAdmin && !$acceptedTerms) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You must accept the terms and conditions to create a sandbox.']);
            exit;
        }
        
        // Handle sandbox_type selection (docker vs lxc)
        // On live (ginto.ai), only Docker is allowed for free users; LXC requires Enterprise
        $requestedType = $data['sandbox_type'] ?? 'docker';
        $isLive = (strpos($_SERVER['HTTP_HOST'] ?? '', 'ginto.ai') !== false) || 
                  (($_ENV['APP_URL'] ?? '') === 'https://ginto.ai');
        
        if ($isLive && $requestedType === 'lxc') {
            // Check if user has Enterprise subscription
            $hasEnterprise = false;
            if (!empty($_SESSION['user_id']) && $this->db) {
                $user = $this->db->get('users', ['subscription_tier'], ['id' => $_SESSION['user_id']]);
                $hasEnterprise = !empty($user['subscription_tier']) && in_array($user['subscription_tier'], ['enterprise', 'professional']);
            }
            
            if (!$hasEnterprise) {
                http_response_code(403);
                echo json_encode([
                    'success' => false, 
                    'error' => 'LXC sandboxes require an Enterprise subscription. Please select Docker or upgrade your plan.',
                    'upgrade_required' => true,
                    'upgrade_url' => '/register'
                ]);
                exit;
            }
        }
        
        // Force Docker mode on live for non-enterprise users
        if ($isLive && $requestedType === 'docker') {
            putenv('SANDBOX_MODE=docker');
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
            
            // Step 2: Create container using ASYNC method to avoid blocking PHP
            // Frontend will poll /api/sandbox/creation-status for completion
            $result = UnifiedSandbox::createAsync($sandboxId, [
                'cpu' => '1',
                'memory' => '256MB',
                'packages' => ['php82', 'php82-fpm', 'caddy', 'mysql-client', 'git', 'nodejs', 'npm']
            ]);
            
            // Check if this is an async operation (has 'status' key) or sync result
            $isAsync = isset($result['status']) && in_array($result['status'], ['copying', 'starting', 'configuring']);
            
            if ($isAsync) {
                // Async operation started - return immediately
                // Frontend should poll /api/sandbox/creation-status
                echo json_encode([
                    'success' => true,
                    'sandbox_id' => $sandboxId,
                    'container_name' => UnifiedSandbox::containerName($sandboxId),
                    'status' => $result['status'],
                    'backend' => $backend,
                    'operation' => $result['operation'] ?? null,
                    'async' => true,
                    'message' => 'Sandbox creation started. Please wait...',
                    'poll_url' => '/api/sandbox/creation-status'
                ]);
                exit;
            }
            
            // Check for immediate success (sandbox already exists) or sync completion
            if ($result['success'] || $result['status'] === 'ready') {
                // Sandbox is ready now
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
            }
            
            // Error occurred
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
        
        $debug = [];
        
        try {
            // TRACE 1: Session data
            $debug['session_sandbox_id'] = $_SESSION['sandbox_id'] ?? 'NOT_SET';
            $debug['session_user_id'] = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 'NOT_SET';
            
            // TRACE 2: Get sandbox ID
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            $debug['getSandboxIdIfExists'] = $sandboxId ?? 'NULL';
            
            if (!$sandboxId) {
                // No per-user sandbox found. For Docker backend, check if a
                // shared OpenWebUI container exists (common dev setup).
                if (isset($backend) && $backend === 'docker') {
                    try {
                        $out = [];
                        // Check for a container named "open-webui"
                        exec('docker ps --filter name=open-webui --format "{{.Names}}" 2>/dev/null', $out, $code);
                        if (!empty($out) && $code === 0) {
                            // Found a shared OpenWebUI Docker container - report installed
                            $openUrl = (isset($_SERVER['HTTP_HOST']) ? (strpos($_SERVER['HTTP_HOST'], ':') !== false ? strtok($_SERVER['HTTP_HOST'], ':') : $_SERVER['HTTP_HOST']) : '127.0.0.1');
                            $openUrl = "http://" . $openUrl . ":8088/"; // assume host port mapping

                            echo json_encode([
                                'success' => true,
                                'installed' => true,
                                'running' => true,
                                'sandbox_exists' => true,
                                'message' => 'Shared OpenWebUI available on Docker host',
                                'url' => $openUrl,
                                '_debug' => array_merge($debug, ['docker_openwebui' => $out])
                            ]);
                            exit;
                        }
                    } catch (\Throwable $e) {
                        // ignore and fall through to normal response
                    }
                }

                echo json_encode([
                    'success' => true,
                    'installed' => false,
                    'sandbox_exists' => false,
                    'message' => 'No sandbox installed',
                    'openwebui_enabled' => (getenv('OPENWEBUI_ENABLED') ?: ($_ENV['OPENWEBUI_ENABLED'] ?? 'false')) === 'true',
                    '_debug' => $debug
                ]);
                exit;
            }
            
            // TRACE 3: Check container exists using UnifiedSandbox (works for both Docker and LXD)
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $debug['backend'] = $backend;
            
            $containerName = \Ginto\Helpers\UnifiedSandbox::containerName($sandboxId);
            $debug['container_name'] = $containerName;
            
            // Use unified sandbox check (works for both Docker and LXD)
            $containerExists = \Ginto\Helpers\UnifiedSandbox::exists($sandboxId);
            $debug['container_exists'] = $containerExists ? 'YES' : 'NO';
            
            if (!$containerExists) {
                // Clear stale session
                if (isset($_SESSION['sandbox_id'])) {
                    unset($_SESSION['sandbox_id']);
                    $debug['cleared_session'] = 'YES';
                }
                echo json_encode([
                    'success' => true,
                    'installed' => false,
                    'sandbox_exists' => false,
                    'message' => 'Sandbox container does not exist',
                    'openwebui_enabled' => (getenv('OPENWEBUI_ENABLED') ?: ($_ENV['OPENWEBUI_ENABLED'] ?? 'false')) === 'true',
                    '_debug' => $debug
                ]);
                exit;
            }
            
            // Detect backend and home directory
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            // Check if OpenWebUI Docker container exists
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                'docker ps -a --filter "name=open-webui" --format "{{.Names}}" 2>/dev/null | grep -q "open-webui" && echo "installed"',
                $homeDir,
                10
            );
            
            $installed = trim($output) === 'installed';
            
            // Check if OpenWebUI is running (Docker container running on port 8088)
            $running = false;
            if ($installed) {
                [$code2, $output2, $err2] = \Ginto\Helpers\UnifiedSandbox::exec(
                    $sandboxId,
                    'docker ps --filter "name=open-webui" --filter "status=running" --format "{{.Names}}" 2>/dev/null | grep -q "open-webui" && echo "running"',
                    $homeDir,
                    5
                );
                $running = trim($output2) === 'running';
            }
            
            // Get server IP for OpenWebUI URL
            $hostIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
            $hostIp = preg_replace('/:\d+$/', '', $hostIp);
            
            // Use subdomain for LXD (online), direct IP for Docker (offline)
            if ($backend === 'lxd') {
                $openwebuiUrl = 'https://oi.ginto.ai/';
            } else {
                // Docker backend - use direct IP access
                $openwebuiUrl = "http://{$hostIp}:8088/";
            }
            
            echo json_encode([
                'success' => true,
                'installed' => $installed,
                'running' => $running,
                'sandbox_exists' => true,
                'sandbox_id' => $sandboxId,
                'backend' => $backend,
                'url' => $running ? $openwebuiUrl : null,
                'openwebui_enabled' => (getenv('OPENWEBUI_ENABLED') ?: ($_ENV['OPENWEBUI_ENABLED'] ?? 'false')) === 'true',
                '_debug' => $debug
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Install OpenWebUI in user's sandbox - runs Docker container
     * Works with both Docker sandboxes and LXD/Alpine sandboxes (Docker-in-LXC)
     * ADMIN ONLY - Only administrators can install OpenWebUI
     */
    public function openwebuiInstall(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Check if OpenWebUI installation is enabled by admin
        $openwebuiEnabled = getenv('OPENWEBUI_ENABLED') ?: ($_ENV['OPENWEBUI_ENABLED'] ?? 'false');
        if ($openwebuiEnabled !== 'true') {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'error' => 'OpenWebUI installation has been disabled by the administrator.',
                'disabled_by_admin' => true
            ]);
            exit;
        }
        
        // Admin check - only admins can install OpenWebUI
        if (empty($_SESSION['is_admin'])) {
            $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id'] ?? 0]);
            if (!$user || !in_array($user['role_id'], [1, 2])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required to install OpenWebUI']);
                exit;
            }
        }
        
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
            
            // Get host IP from server
            $hostIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Remove port if present
            $hostIp = preg_replace('/:\d+$/', '', $hostIp);
            
            // Detect backend
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            
            // Build resilient install command that:
            // 1. Creates an install script that runs in background (survives console disconnect)
            // 2. Tails the log so user can see progress
            // 3. Handles partial downloads, retries, and existing containers
            
            $logFile = '/root/openwebui-install.log';
            $scriptFile = '/root/openwebui-install.sh';
            
            if ($backend === 'lxd') {
                // LXD/Alpine: Create install script, run in background, then tail log
                // The script survives console disconnection
                $installCmd = 
                    // Create the install script
                    'cat > ' . $scriptFile . ' << \'INSTALLSCRIPT\'
#!/bin/sh
LOG="' . $logFile . '"
HOST_IP="' . $hostIp . '"
exec >> "$LOG" 2>&1
echo "========================================"
echo "[$(date)] OpenWebUI Installation Started"
echo "========================================"
echo "Setting up Docker in Alpine..."
command -v docker >/dev/null 2>&1 && echo "[$(date)] Docker already installed" || (echo "[$(date)] Installing Docker..." && apk update && apk add --no-cache docker docker-cli-compose)
rc-service docker status >/dev/null 2>&1 && echo "[$(date)] Docker service running" || (echo "[$(date)] Starting Docker service..." && rc-update add docker boot 2>/dev/null; rc-service docker start)
sleep 2
echo "[$(date)] Docker info: $(docker --version)"
if docker ps --filter "name=open-webui" --filter "status=running" -q | grep -q .; then
  echo "[$(date)] OpenWebUI is already running!"
  echo "Access it at: http://$HOST_IP:8088/"
  exit 0
fi
echo "[$(date)] Removing any existing container..."
docker rm -f open-webui 2>/dev/null || true
echo "[$(date)] Pulling OpenWebUI image (this may take several minutes)..."
if docker pull ghcr.io/open-webui/open-webui:main; then
  echo "[$(date)] Image pull completed successfully"
  echo "[$(date)] Starting OpenWebUI container..."
  docker run -d --name open-webui --restart unless-stopped -p 8088:8080 -v open-webui:/app/backend/data ghcr.io/open-webui/open-webui:main
  echo ""
  echo "[$(date)] ✅ OpenWebUI installed successfully!"
  echo "🌐 Access it at: http://$HOST_IP:8088/"
else
  echo "[$(date)] ❌ Image pull failed!"
fi
echo "[$(date)] Installation script completed"
INSTALLSCRIPT
' .
                    'chmod +x ' . $scriptFile . ' && ' .
                    // Clear previous log
                    'echo "" > ' . $logFile . ' && ' .
                    // Run script in background with nohup (survives disconnect)
                    'nohup ' . $scriptFile . ' > /dev/null 2>&1 & ' .
                    'sleep 1 && ' .
                    // Tail log so user can see progress
                    'echo "📋 Installation started in background. Showing progress..." && ' .
                    'tail -f ' . $logFile;
            } else {
                // Docker sandbox: Same approach
                $installCmd = 
                    'cat > ' . $scriptFile . ' << \'INSTALLSCRIPT\'
#!/bin/sh
LOG="' . $logFile . '"
HOST_IP="' . $hostIp . '"
exec >> "$LOG" 2>&1
echo "========================================"
echo "[$(date)] OpenWebUI Installation Started"
echo "========================================"
if docker ps --filter "name=open-webui" --filter "status=running" -q | grep -q .; then
  echo "[$(date)] OpenWebUI is already running!"
  echo "Access it at: http://$HOST_IP:8088/"
  exit 0
fi
echo "[$(date)] Removing any existing container..."
docker rm -f open-webui 2>/dev/null || true
echo "[$(date)] Pulling OpenWebUI image (this may take several minutes)..."
if docker pull ghcr.io/open-webui/open-webui:main; then
  echo "[$(date)] Image pull completed successfully"
  echo "[$(date)] Starting OpenWebUI container..."
  docker run -d --name open-webui --restart unless-stopped -p 8088:8080 -v open-webui:/app/backend/data ghcr.io/open-webui/open-webui:main
  echo ""
  echo "[$(date)] ✅ OpenWebUI installed successfully!"
  echo "🌐 Access it at: http://$HOST_IP:8088/"
else
  echo "[$(date)] ❌ Image pull failed!"
fi
echo "[$(date)] Installation script completed"
INSTALLSCRIPT
' .
                    'chmod +x ' . $scriptFile . ' && ' .
                    'echo "" > ' . $logFile . ' && ' .
                    'nohup ' . $scriptFile . ' > /dev/null 2>&1 & ' .
                    'sleep 1 && ' .
                    'echo "📋 Installation started in background. Showing progress..." && ' .
                    'tail -f ' . $logFile;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Installing OpenWebUI...',
                'command' => $installCmd,
                'backend' => $backend,
                'log_file' => $logFile
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Start OpenWebUI in user's sandbox (Docker container)
     * ADMIN ONLY - Only administrators can start OpenWebUI
     */
    public function openwebuiStart(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Admin check - only admins can start OpenWebUI
        if (empty($_SESSION['is_admin'])) {
            $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id'] ?? 0]);
            if (!$user || !in_array($user['role_id'], [1, 2])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required to start OpenWebUI']);
                exit;
            }
        }
        
        try {
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed']);
                exit;
            }
            
            // Detect backend and home directory
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            // For LXD, ensure Docker daemon is running first
            if ($backend === 'lxd') {
                \Ginto\Helpers\UnifiedSandbox::exec(
                    $sandboxId,
                    'rc-service docker start 2>/dev/null || true',
                    $homeDir,
                    10
                );
            }
            
            // Start OpenWebUI Docker container
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                'docker start open-webui 2>&1 && sleep 2 && docker ps --filter "name=open-webui" --filter "status=running" -q | grep -q . && echo "STARTED"',
                $homeDir,
                30
            );
            
            // Get host IP for URL
            $hostIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
            $hostIp = preg_replace('/:\d+$/', '', $hostIp);
            
            // Use subdomain for LXD (online), direct IP for Docker (offline)
            if ($backend === 'lxd') {
                $openwebuiUrl = 'https://oi.ginto.ai/';
            } else {
                // Docker backend - use direct IP access
                $openwebuiUrl = "http://{$hostIp}:8088/";
            }
            
            if (strpos($output, 'STARTED') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'OpenWebUI started',
                    'url' => $openwebuiUrl,
                    'backend' => $backend
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to start OpenWebUI',
                    'output' => $output,
                    'stderr' => $err,
                    'backend' => $backend
                ]);
            }
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Stop OpenWebUI in user's sandbox (Docker container)
     * ADMIN ONLY - Only administrators can stop OpenWebUI
     */
    public function openwebuiStop(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Admin check - only admins can stop OpenWebUI
        if (empty($_SESSION['is_admin'])) {
            $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id'] ?? 0]);
            if (!$user || !in_array($user['role_id'], [1, 2])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required to stop OpenWebUI']);
                exit;
            }
        }
        
        try {
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed']);
                exit;
            }
            
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            $homeDir = ($backend === 'docker') ? '/home/sandbox' : '/root';
            
            // Stop OpenWebUI Docker container
            [$code, $output, $err] = \Ginto\Helpers\UnifiedSandbox::exec(
                $sandboxId,
                'docker stop open-webui 2>&1; echo "STOPPED"',
                $homeDir,
                15
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
    
    /**
     * Check if a URL is ready (returns HTTP 200)
     * Used for checking if services like OpenWebUI are fully started
     */
    public function checkUrlReady(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $url = $_GET['url'] ?? $_POST['url'] ?? null;
            
            if (!$url) {
                echo json_encode(['success' => false, 'error' => 'URL parameter required']);
                exit;
            }
            
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid URL']);
                exit;
            }
            
            // Only allow http/https
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'])) {
                echo json_encode(['success' => false, 'error' => 'Only HTTP/HTTPS URLs allowed']);
                exit;
            }
            
            // Security: Only allow checking URLs on same host, localhost, or private IPs
            $host = parse_url($url, PHP_URL_HOST);
            $serverHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $allowedHosts = ['localhost', '127.0.0.1', $serverHost, $_SERVER['SERVER_ADDR'] ?? ''];
            
            // Also allow private IP ranges (10.x.x.x, 192.168.x.x, 172.16-31.x.x)
            $isPrivateIp = filter_var($host, FILTER_VALIDATE_IP) && (
                filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false ||
                $host === '127.0.0.1'
            );
            
            if (!in_array($host, $allowedHosts) && !$isPrivateIp) {
                echo json_encode(['success' => false, 'error' => 'URL host not allowed']);
                exit;
            }
            
            // Check the URL with curl
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_NOBODY => true, // HEAD request
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Ready if we get a 2xx or 3xx response
            $ready = $httpCode >= 200 && $httpCode < 400;
            
            echo json_encode([
                'success' => true,
                'ready' => $ready,
                'http_code' => $httpCode,
                'error' => $error ?: null
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Register OpenWebUI domain (oi.ginto.ai) with Caddy proxy to sandbox:8088
     * Called after OpenWebUI installation completes to set up the reverse proxy
     * ADMIN ONLY - Only administrators can register OpenWebUI domains
     */
    public function registerOpenwebuiDomain(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Admin check - only admins can register OpenWebUI domains
        if (empty($_SESSION['is_admin'])) {
            $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id'] ?? 0]);
            if (!$user || !in_array($user['role_id'], [1, 2])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required to register OpenWebUI domain']);
                exit;
            }
        }
        
        try {
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed']);
                exit;
            }
            
            if (!\Ginto\Helpers\UnifiedSandbox::exists($sandboxId)) {
                echo json_encode(['success' => false, 'error' => 'Sandbox does not exist']);
                exit;
            }
            
            // Get sandbox IP - must query actual container IP for LXD
            $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
            if ($backend === 'lxd') {
                // For LXD, query the actual container IP from LXD
                $sandboxIp = \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId);
            } else {
                // For Docker, use the container network IP
                $sandboxIp = \Ginto\Helpers\UnifiedSandbox::getIp($sandboxId);
            }
            
            if (!$sandboxIp) {
                echo json_encode(['success' => false, 'error' => 'Could not determine sandbox IP']);
                exit;
            }
            
            $containerName = \Ginto\Helpers\UnifiedSandbox::containerName($sandboxId);
            $domain = 'oi.ginto.ai';
            $parentZone = 'ginto.ai';
            $proxyTarget = "http://{$sandboxIp}:8088";
            
            // Get server public IP for DNS record
            $serverIp = $this->getServerPublicIp();
            
            // Create DNS A record for oi.ginto.ai in ginto.ai zone
            $dnsResult = $this->createSubdomainDnsRecord($domain, $parentZone, $serverIp);
            
            // Generate Caddy config for OpenWebUI proxy
            // Same-origin with ginto.ai - no complex CORS needed
            $caddyConfig = <<<CADDY
{$domain} {
    reverse_proxy {$proxyTarget} {
        header_up Host {host}
        header_up X-Real-IP {remote_host}
        header_up X-Forwarded-For {remote_host}
        header_up X-Forwarded-Proto {scheme}
    }
    
    encode gzip
    
    # Handle WebSocket connections for OpenWebUI
    @websockets {
        header Connection *Upgrade*
        header Upgrade websocket
    }
    reverse_proxy @websockets {$proxyTarget}
}
CADDY;
            
            $configFile = "/etc/caddy/sites-available/{$domain}.caddy";
            
            // Write Caddy config to sites-available only
            // Note: oi.ginto.ai is handled in tunnels.caddy, this is for reference/backup
            if (file_put_contents($configFile, $caddyConfig) === false) {
                echo json_encode(['success' => false, 'error' => 'Failed to write Caddy config']);
                exit;
            }
            
            // Save to database
            try {
                $exists = $this->db->has('virtual_hosts', ['domain' => $domain]);
                $data = [
                    'domain' => $domain,
                    'document_root' => null,
                    'proxy_type' => 'container',
                    'proxy_target' => $proxyTarget,
                    'proxy_container_name' => $containerName,
                    'enable_php' => 0,
                    'enable_ssl' => 1,
                    'is_enabled' => 1
                ];
                
                if ($exists) {
                    $this->db->update('virtual_hosts', $data, ['domain' => $domain]);
                } else {
                    $this->db->insert('virtual_hosts', $data);
                }
            } catch (\Exception $e) {
                error_log("Failed to save OpenWebUI domain to database: " . $e->getMessage());
            }
            
            // Reload Caddy
            exec('sudo systemctl reload caddy 2>&1', $output, $code);
            
            echo json_encode([
                'success' => true,
                'message' => "Domain {$domain} registered",
                'domain' => $domain,
                'proxy_target' => $proxyTarget,
                'url' => "https://{$domain}/",
                'dns' => $dnsResult
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Register a temporary cloud subdomain for the user's sandbox
     * Creates an alphanumeric subdomain that expires in 5 minutes
     * POST /api/sandbox/cloud/register
     */
    public function registerCloudDomain(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Allow logged-in users and visitors
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        try {
            $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($this->db ?? null, $_SESSION ?? null, true);
            
            if (!$sandboxId) {
                echo json_encode(['success' => false, 'error' => 'No sandbox installed. Please create a sandbox first.']);
                exit;
            }
            
            if (!UnifiedSandbox::exists($sandboxId)) {
                echo json_encode(['success' => false, 'error' => 'Sandbox container not found.']);
                exit;
            }
            
            if (!UnifiedSandbox::isRunning($sandboxId)) {
                // Try to start it
                UnifiedSandbox::start($sandboxId);
                usleep(500000); // Wait 0.5s for container to start
                if (!UnifiedSandbox::isRunning($sandboxId)) {
                    echo json_encode(['success' => false, 'error' => 'Sandbox is not running. Please start it first.']);
                    exit;
                }
            }
            
            // Get sandbox IP
            $sandboxIp = UnifiedSandbox::getIp($sandboxId);
            if (!$sandboxIp) {
                echo json_encode(['success' => false, 'error' => 'Could not determine sandbox IP.']);
                exit;
            }
            
            // Generate alphanumeric subdomain (8 chars)
            $subdomain = $this->generateCloudSubdomain();
            $domain = "{$subdomain}.ginto.ai";
            
            // Expiry: 5 minutes from now
            $expiresIn = 300; // 5 minutes
            $expiresAt = time() + $expiresIn;
            
            // Use the same pattern as /admin/lxc - create a proper Caddy config
            // that reverse proxies to the sandbox container IP
            $caddyResult = $this->createCloudCaddyConfig($domain, $sandboxIp, $sandboxId, $expiresAt);
            if (!$caddyResult['success']) {
                echo json_encode(['success' => false, 'error' => $caddyResult['error'] ?? 'Failed to create Caddy config']);
                exit;
            }
            
            // Save cloud subdomain info in session for the countdown
            $_SESSION['cloud_subdomain'] = $subdomain;
            $_SESSION['cloud_subdomain_expires'] = $expiresAt;
            $_SESSION['cloud_sandbox_id'] = $sandboxId;
            
            echo json_encode([
                'success' => true,
                'subdomain' => $subdomain,
                'url' => "https://{$domain}/",
                'sandbox_ip' => $sandboxIp,
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'message' => "Your sandbox is now available at https://{$domain}/ for 5 minutes."
            ]);
            
        } catch (\Throwable $e) {
            error_log('Cloud domain registration error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Create Caddy config for cloud subdomain (same pattern as /admin/hosting/domains/quick-assign)
     */
    private function createCloudCaddyConfig(string $domain, string $sandboxIp, string $sandboxId, int $expiresAt): array
    {
        try {
            // Generate Caddy config with reverse proxy to sandbox IP
            $config = "{$domain} {\n";
            $config .= "    # Ginto Cloud temporary subdomain\n";
            $config .= "    # Sandbox: {$sandboxId}\n";
            $config .= "    # Expires: " . date('Y-m-d H:i:s', $expiresAt) . "\n";
            $config .= "    reverse_proxy http://{$sandboxIp}:80\n";
            $config .= "    encode gzip\n";
            $config .= "}\n";
            
            $configFile = "/etc/caddy/sites-available/{$domain}.caddy";
            $enabledFile = "/etc/caddy/sites-enabled/{$domain}.caddy";
            
            // Write config file
            if (file_put_contents($configFile, $config) === false) {
                return ['success' => false, 'error' => 'Failed to write Caddy config'];
            }
            
            // Enable site (create symlink)
            if (!file_exists($enabledFile)) {
                if (!symlink($configFile, $enabledFile)) {
                    return ['success' => false, 'error' => 'Failed to enable site'];
                }
            }
            
            // Save to virtual_hosts table for tracking
            try {
                $exists = $this->db->has('virtual_hosts', ['domain' => $domain]);
                $data = [
                    'domain' => $domain,
                    'document_root' => null,
                    'owner_username' => $_SESSION['username'] ?? $_SESSION['public_id'] ?? null,
                    'owner_fullname' => $_SESSION['fullname'] ?? null,
                    'proxy_type' => 'container',
                    'proxy_target' => "http://{$sandboxIp}:80",
                    'proxy_container_name' => $sandboxId,
                    'enable_php' => 0,
                    'enable_ssl' => 1,
                    'is_enabled' => 1,
                    'is_cloud_temporary' => 1,
                    'expires_at' => date('Y-m-d H:i:s', $expiresAt)
                ];
                
                if ($exists) {
                    $this->db->update('virtual_hosts', $data, ['domain' => $domain]);
                } else {
                    $this->db->insert('virtual_hosts', $data);
                }
            } catch (\Exception $e) {
                // Database error is non-fatal
                error_log("Failed to save cloud domain to database: " . $e->getMessage());
            }
            
            // Reload Caddy to apply the new config
            $reloadOutput = shell_exec('sudo systemctl reload caddy 2>&1');
            
            return ['success' => true, 'message' => "Cloud domain {$domain} created"];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get cloud domain status
     * GET /api/sandbox/cloud/status
     */
    public function cloudDomainStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $subdomain = $_SESSION['cloud_subdomain'] ?? null;
        $expiresAt = $_SESSION['cloud_subdomain_expires'] ?? 0;
        
        if (!$subdomain) {
            echo json_encode([
                'success' => true,
                'active' => false,
                'subdomain' => null,
                'url' => null,
                'expires_at' => null
            ]);
            exit;
        }
        
        $isActive = $expiresAt > time();
        
        echo json_encode([
            'success' => true,
            'active' => $isActive,
            'subdomain' => $subdomain,
            'url' => $isActive ? "https://{$subdomain}.ginto.ai/" : null,
            'expires_at' => $expiresAt,
            'remaining' => max(0, $expiresAt - time())
        ]);
        exit;
    }
    
    /**
     * Generate a random alphanumeric subdomain (8 chars)
     */
    private function generateCloudSubdomain(): string
    {
        // Use a short alphanumeric string like git commit hashes
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }
    
    /**
     * Get server's public IP address
     */
    private function getServerPublicIp(): ?string
    {
        // Try to get from environment first
        $ip = $_ENV['SERVER_IP'] ?? null;
        if ($ip) return $ip;

        // Try to get from hostname
        $output = shell_exec('hostname -I 2>/dev/null');
        if ($output) {
            $ips = explode(' ', trim($output));
            foreach ($ips as $ip) {
                $ip = trim($ip);
                // Skip private IPs (10.x, 192.168.x, 172.16-31.x)
                if (!preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
            // If no public IP found, return first IPv4
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
        }

        // Fallback: try external service
        $ip = @file_get_contents('https://api.ipify.org?format=text');
        if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
            return trim($ip);
        }

        return null;
    }
    
    /**
     * Sync DNS zone to PowerDNS
     */
    private function syncZoneToPowerDNS(string $zoneName): bool
    {
        try {
            // Get zone data
            $zone = $this->db->get('dns_zones', ['id', 'name'], ['name' => $zoneName]);
            if (!$zone) {
                return false;
            }
            
            // Get all records for this zone
            $records = $this->db->select('dns_records', '*', ['zone_id' => $zone['id']]);
            
            // Build rrsets for PowerDNS
            $rrsets = [];
            $recordsByNameType = [];
            
            foreach ($records as $record) {
                $key = $record['name'] . '|' . $record['type'];
                if (!isset($recordsByNameType[$key])) {
                    $recordsByNameType[$key] = [
                        'name' => $record['name'] . '.',
                        'type' => $record['type'],
                        'ttl' => (int)$record['ttl'],
                        'changetype' => 'REPLACE',
                        'records' => []
                    ];
                }
                $recordsByNameType[$key]['records'][] = [
                    'content' => $record['content'],
                    'disabled' => false
                ];
            }
            
            $rrsets = array_values($recordsByNameType);
            
            // Call PowerDNS API
            $pdnsApiUrl = rtrim($_ENV['PDNS_API_URL'] ?? 'http://127.0.0.1:8081', '/');
            $pdnsApiKey = $_ENV['PDNS_API_KEY'] ?? '';
            
            if (!$pdnsApiKey) {
                error_log("PowerDNS API key not configured");
                return false;
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$pdnsApiUrl}/api/v1/servers/localhost/zones/{$zoneName}.",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode(['rrsets' => $rrsets]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-API-Key: ' . $pdnsApiKey
                ],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }
            
            error_log("PowerDNS sync failed for {$zoneName}: HTTP {$httpCode} - {$response}");
            return false;
        } catch (\Exception $e) {
            error_log("PowerDNS sync error: " . $e->getMessage());
            return false;
        }
    }
}

