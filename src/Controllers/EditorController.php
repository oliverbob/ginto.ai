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

    /**
     * Get file tree
     */
    public function tree(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Get sandbox ID or root path
        $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $sandboxId = null;
        $isLoggedIn = !empty($_SESSION['user_id']);
        
        try {
            putenv('GINTO_SKIP_SANDBOX_START=1');
            $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db, $_SESSION ?? null);
            putenv('GINTO_SKIP_SANDBOX_START');
            
            // Check if result is a sandbox ID (short alphanumeric) or a filesystem path
            if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
                $sandboxId = $result;
            } else {
                $editorRoot = $result ?: $editorRoot;
            }
        } catch (\Throwable $e) {}
        
        // If we have a sandbox ID, validate it exists before using
        if ($sandboxId) {
            $sandboxValid = \Ginto\Helpers\ClientSandboxHelper::validateSandboxExists($sandboxId, $this->db);
            
            if (!$sandboxValid) {
                // Sandbox is stale - clean up and create a new one
                \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);
                unset($_SESSION['sandbox_id']);
                
                // Create fresh sandbox
                $newSandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($this->db, $_SESSION);
                if ($newSandboxId) {
                    $createResult = \Ginto\Helpers\LxdSandboxManager::createSandbox($newSandboxId);
                    if ($createResult['success']) {
                        \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($newSandboxId);
                        $_SESSION['sandbox_id'] = $newSandboxId;
                        $sandboxId = $newSandboxId;
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to create sandbox', 'tree' => []]);
                        exit;
                    }
                }
            }
            
            $listResult = \Ginto\Helpers\LxdSandboxManager::listFiles($sandboxId, '/home', 5);
            if ($listResult['success']) {
                echo json_encode(['success' => true, 'tree' => $listResult['tree'], 'sandbox_id' => $sandboxId]);
            } else {
                echo json_encode(['success' => false, 'error' => $listResult['error'] ?? 'Failed to list files', 'tree' => []]);
            }
            exit;
        }
        
        // Build tree recursively for local filesystem (admin mode)
        $tree = $this->buildEditorTree($editorRoot);
        echo json_encode(['success' => true, 'tree' => $tree]);
        exit;
    }

    /**
     * Helper to build editor tree recursively
     */
    private function buildEditorTree($dir, $maxDepth = 10, $depth = 0, $base = ''): array
    {
        if ($depth > $maxDepth || !is_dir($dir)) return [];
        
        $tree = [];
        $items = @scandir($dir);
        if (!$items) return [];
        
        // Filter and sort - folders first, then files
        $folders = [];
        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, ['vendor', 'node_modules', '.git', '__pycache__', '.cache', '.idea'], true)) continue;
            
            $path = $dir . '/' . $item;
            $relPath = $base ? $base . '/' . $item : $item;
            
            if (is_dir($path)) {
                $folders[] = ['name' => $item, 'path' => $path, 'relPath' => $relPath];
            } else {
                $files[] = ['name' => $item, 'path' => $path, 'relPath' => $relPath];
            }
        }
        
        // Sort alphabetically
        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        
        // Add folders
        foreach ($folders as $f) {
            $tree[$f['name']] = [
                'type' => 'folder',
                'path' => $f['relPath'],
                'encoded' => base64_encode($f['relPath']),
                'children' => $this->buildEditorTree($f['path'], $maxDepth, $depth + 1, $f['relPath'])
            ];
        }
        
        // Add files
        foreach ($files as $f) {
            $tree[$f['name']] = [
                'type' => 'file',
                'path' => $f['relPath'],
                'encoded' => base64_encode($f['relPath'])
            ];
        }
        
        return $tree;
    }

    /**
     * Create file or folder
     */
    public function create(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Get sandbox ID or root path
        $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $sandboxId = null;
        try {
            putenv('GINTO_SKIP_SANDBOX_START=1');
            $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db, $_SESSION ?? null);
            putenv('GINTO_SKIP_SANDBOX_START');
            
            // Check if result is a sandbox ID or a filesystem path
            if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
                $sandboxId = $result;
            } else {
                $editorRoot = $result ?: $editorRoot;
            }
        } catch (\Throwable $e) {}
        
        $path = $_POST['path'] ?? '';
        $type = $_POST['type'] ?? 'file';
        
        if (empty($path)) {
            echo json_encode(['success' => false, 'error' => 'Path is required']);
            exit;
        }
        
        // Security: prevent path traversal
        $path = str_replace(['../', '..\\'], '', $path);
        
        // If we have a sandbox ID, use LXD to create item
        if ($sandboxId) {
            // Check if already exists
            if (\Ginto\Helpers\LxdSandboxManager::pathExists($sandboxId, $path)) {
                echo json_encode(['success' => false, 'error' => ($type === 'folder' ? 'Folder' : 'File') . ' already exists']);
                exit;
            }
            
            $createResult = \Ginto\Helpers\LxdSandboxManager::createItem($sandboxId, $path, $type);
            if ($createResult['success']) {
                echo json_encode([
                    'success' => true,
                    'path' => $path,
                    'encoded' => base64_encode($path)
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => $createResult['error'] ?? 'Failed to create']);
            }
            exit;
        }
        
        // Local filesystem (admin mode)
        $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
        
        // Ensure parent directory exists
        $parentDir = dirname($fullPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }
        
        if ($type === 'folder') {
            if (is_dir($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Folder already exists']);
                exit;
            }
            mkdir($fullPath, 0755, true);
        } else {
            if (file_exists($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'File already exists']);
                exit;
            }
            file_put_contents($fullPath, '');
        }
        
        echo json_encode([
            'success' => true,
            'path' => $path,
            'encoded' => base64_encode($path)
        ]);
        exit;
    }
}
