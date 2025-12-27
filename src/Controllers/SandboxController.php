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
}
