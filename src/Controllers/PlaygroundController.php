<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;

/**
 * Playground Controller
 * Handles playground/sandbox development environment routes
 */
class PlaygroundController
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = Database::getInstance();
        }
        $this->db = $db;
    }

    /**
     * Playground main page
     * GET /playground
     */
    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        // Require login
        if (empty($_SESSION['user_id'])) {
            header('Location: /login?redirect=/playground');
            exit;
        }
        
        $pageTitle = 'Playground - Ginto CMS';

        // Fetch recent non-playground application activity logs for dashboard
        $recentActivity = [];
        try {
            $rows = $this->db->select('activity_logs', ['id','user_id','action','model_type','model_id','description','created_at'], [
                'AND' => [
                    'action[!~]' => 'playground.%',
                    'model_type[!]' => 'playground.editor'
                ],
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 6
            ]) ?: [];

            foreach ($rows as $r) {
                $userLabel = $r['user_id'] ? (string)$r['user_id'] : '(system)';
                try {
                    if (!empty($r['user_id'])) {
                        $u = $this->db->get('users', ['firstname','lastname','fullname','username'], ['id' => $r['user_id']]);
                        if ($u) {
                            $userLabel = trim(($u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname'])) ?: ($u['username'] ?? $userLabel));
                        }
                    }
                } catch (\Throwable $_) {}

                $desc = $r['description'] ?? '';
                $message = $r['action'] ?: strtok($desc, "\n");

                $recentActivity[] = [
                    'id' => $r['id'],
                    'user' => $userLabel,
                    'action' => $r['action'],
                    'message' => $message,
                    'details' => $desc,
                    'created_at' => $r['created_at']
                ];
            }
        } catch (\Throwable $_) {
            $recentActivity = [];
        }

        $db = $this->db;
        include ROOT_PATH . '/src/Views/playground/index.php';
        exit;
    }

    /**
     * Playground logs page (admin-only)
     * GET /playground/logs
     */
    public function logs(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            header('Location: /login?redirect=/playground/logs');
            exit;
        }

        // Admin check
        $isAdmin = UserController::isAdmin();
        if (!$isAdmin) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>Admin access required.</p>';
            exit;
        }

        $pageTitle = 'Playground Logs (Admin View)';
        
        // Fetch all playground-related activity logs
        $playgroundLogs = [];
        try {
            $rows = $this->db->select('activity_logs', '*', [
                'OR' => [
                    'action[~]' => 'playground.%',
                    'model_type' => 'playground.editor'
                ],
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 100
            ]) ?: [];

            foreach ($rows as $r) {
                $userLabel = $r['user_id'] ? (string)$r['user_id'] : '(system)';
                try {
                    if (!empty($r['user_id'])) {
                        $u = $this->db->get('users', ['firstname','lastname','fullname','username'], ['id' => $r['user_id']]);
                        if ($u) {
                            $userLabel = trim(($u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname'])) ?: ($u['username'] ?? $userLabel));
                        }
                    }
                } catch (\Throwable $_) {}

                $r['user_label'] = $userLabel;
                $playgroundLogs[] = $r;
            }
        } catch (\Throwable $_) {
            $playgroundLogs = [];
        }

        $db = $this->db;
        include ROOT_PATH . '/src/Views/playground/logs.php';
        exit;
    }

    /**
     * Create sample log entry for testing
     * POST /playground/logs/create-sample
     */
    public function createSampleLog(): void
    {
        header('Content-Type: application/json');
        
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $isAdmin = UserController::isAdmin();
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }

        try {
            $this->db->insert('activity_logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'playground.editor.sample',
                'model_type' => 'playground.editor',
                'model_id' => '0',
                'description' => 'Sample playground log entry created by admin at ' . date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            echo json_encode(['success' => true, 'message' => 'Sample entry created']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create sample entry']);
        }
        exit;
    }

    /**
     * Install working environment (background)
     * POST /playground/editor/install_env
     */
    public function installEnv(): void
    {
        header('Content-Type: application/json');
        
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        // CSRF
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        try {
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            $sandboxId = basename($editorRoot);
            if (empty($sandboxId)) {
                echo json_encode(['success' => false, 'error' => 'No sandbox available']);
                exit;
            }
            $riskless = false;
            if (isset($_POST['riskless']) && ($_POST['riskless'] === '1' || $_POST['riskless'] === 'true')) {
                $riskless = true;
            }
            $started = \Ginto\Helpers\SandboxManager::ensureSandboxRunning($sandboxId, $editorRoot, 6, $riskless);
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(dirname(dirname(__DIR__))) . '/storage';
            $logFile = $storagePath . '/backups/install_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sandboxId) . '.log';
            echo json_encode(['success' => true, 'started' => boolval($started), 'log' => $logFile, 'riskless' => boolval($riskless)]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'install_failed', 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Poll working environment install status
     * GET /playground/editor/install_status
     */
    public function installStatus(): void
    {
        header('Content-Type: application/json');
        
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        try {
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            $sandboxId = basename($editorRoot);
            if (empty($sandboxId)) {
                echo json_encode(['success' => false, 'error' => 'No sandbox available']);
                exit;
            }
            $exists = false;
            try {
                $exists = \Ginto\Helpers\SandboxManager::sandboxExists($sandboxId);
            } catch (\Throwable $_) {
                $exists = false;
            }
            echo json_encode(['success' => true, 'sandbox_exists' => $exists, 'sandbox_id' => $sandboxId, 'editor_root' => $editorRoot]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'status_failed']);
            exit;
        }
    }

    /**
     * Playground tool catch-all route
     * GET /playground/{tool}
     */
    public function tool(string $tool = 'index'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            header('Location: /login?redirect=/playground/' . urlencode($tool));
            exit;
        }
        
        $pageTitle = ucfirst($tool) . ' - Playground';
        $toolView = ROOT_PATH . '/src/Views/playground/' . basename($tool) . '.php';
        
        if (file_exists($toolView)) {
            $db = $this->db;
            include $toolView;
        } else {
            $db = $this->db;
            include ROOT_PATH . '/src/Views/playground/index.php';
        }
        exit;
    }

    /**
     * View specific log entry
     * GET /playground/logs/{id}
     */
    public function logDetail(?string $id = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            header('Location: /login?redirect=/playground/logs');
            exit;
        }

        // Admin check using role_id
        $isAdmin = UserController::isAdmin();
        if (!$isAdmin) {
            header('Location: /playground');
            exit;
        }

        if (!$id) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }

        // Join to users so we can display username
        $log = $this->db->get('activity_logs', [
            '[>]users' => ['user_id' => 'id']
        ], [
            'activity_logs.id', 'activity_logs.user_id', 'users.username(user_name)', 
            'activity_logs.action', 'activity_logs.model_type', 'activity_logs.model_id', 
            'activity_logs.description', 'activity_logs.created_at'
        ], ['activity_logs.id' => (int)$id]);

        if ($log) {
            $log['username'] = $log['user_name'] ?? ($log['user_id'] ? (string)$log['user_id'] : '(system)');

            // Try to detect JSON descriptions for nicer display
            $desc = (string)($log['description'] ?? '');
            $trim = ltrim($desc);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $json = json_decode($desc, true);
                if (is_array($json)) {
                    $log['description_json'] = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
            }
        }
        
        if (!$log) {
            http_response_code(404);
            echo 'Log not found';
            exit;
        }

        $pageTitle = 'Playground Log #' . $log['id'];
        $db = $this->db;
        include ROOT_PATH . '/src/Views/playground/logs/show.php';
        exit;
    }

    /**
     * Save file in playground editor
     * POST /playground/editor/save
     */
    public function save(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        try {
            // Ensure a session sandbox exists for visitors
            if (empty($_SESSION['user_id'])) {
                if (empty($_SESSION['sandbox_id'])) {
                    try {
                        $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                        if (!empty($created)) {
                            $_SESSION['sandbox_id'] = basename($created);
                            if (empty($_SESSION['csrf_token'])) {
                                try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                            }
                        }
                    } catch (\Throwable $_) {}
                }
                if (empty($_SESSION['sandbox_id'])) {
                    http_response_code(401);
                    echo 'Unauthorized';
                    exit;
                }
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                exit;
            }
            
            $token = $_POST['csrf_token'] ?? '';
            if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                echo 'Invalid CSRF token';
                exit;
            }
            
            $enc = $_POST['file'] ?? '';
            $content = $_POST['content'] ?? '';
            
            if (!$enc) {
                http_response_code(400);
                echo 'Missing file token';
                exit;
            }
            
            $decoded = base64_decode(rawurldecode($enc));
            if (!$decoded) {
                http_response_code(400);
                echo 'Invalid file token';
                exit;
            }
            
            $safePath = str_replace(['..', '\\'], ['', '/'], $decoded);
            
            $normalizePath = function($p) {
                $p = str_replace('\\', '/', $p);
                $parts = array_filter(explode('/', $p), function($x){ return $x !== ''; });
                $stack = [];
                foreach ($parts as $part) {
                    if ($part === '.') continue;
                    if ($part === '..') { array_pop($stack); continue; }
                    $stack[] = $part;
                }
                $out = implode('/', $stack);
                if ($p !== '' && $p[0] === '/') $out = '/' . $out;
                return $out;
            };
            
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($safePath, '/');
            
            $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
            $normalizedRoot = rtrim($normalizePath($realRoot), '/');
            $normalizedFull = $normalizePath($fullPath);
            
            if (strpos($normalizedFull, $normalizedRoot) !== 0) {
                http_response_code(403);
                echo 'Access denied';
                exit;
            }
            
            $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
            
            $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (!$isAdminSession && in_array($ext, $forbiddenExt)) {
                http_response_code(403);
                echo 'Forbidden file type';
                exit;
            }
            
            // Quota enforcement (non-admins)
            $sandboxId = basename($editorRoot);
            if (!$isAdminSession) {
                $currentUsed = 0;
                try {
                    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS));
                    foreach ($it as $f) { $currentUsed += $f->getSize(); }
                } catch (\Throwable $_) { $currentUsed = 0; }
                
                $oldSize = file_exists($fullPath) ? filesize($fullPath) : 0;
                $newSize = strlen($content);
                $newUsed = $currentUsed - $oldSize + $newSize;
                
                $quota = 104857600;
                if ($this->db) {
                    $row = $this->db->get('client_sandboxes', ['quota_bytes'], ['sandbox_id' => $sandboxId]);
                    if (!empty($row['quota_bytes'])) $quota = (int)$row['quota_bytes'];
                }
                if ($newUsed > $quota) {
                    http_response_code(413);
                    echo 'Quota exceeded';
                    exit;
                }
            }
            
            $parentDir = dirname($fullPath);
            if (!is_dir($parentDir)) @mkdir($parentDir, 0755, true);
            
            $saved = @file_put_contents($fullPath, $content);
            if ($saved !== false) {
                if (!$isAdminSession && $this->db) {
                    $sizeAfter = 0;
                    try { $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) { $sizeAfter += $f->getSize(); } } catch (\Throwable $_) { $sizeAfter = 0; }
                    $this->db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]);
                }
                echo 'OK';
            } else {
                $err = error_get_last();
                $payload = json_encode(['event' => 'playground_save_failed', 'user_id' => $_SESSION['user_id'] ?? null, 'editor_root' => $editorRoot, 'full_path' => $fullPath, 'content_length' => strlen($content), 'php_error' => $err]);
                error_log('[playground/save] Save failed: ' . $payload);
                if ($this->db) {
                    try { $this->db->insert('activity_logs', ['user_id' => $_SESSION['user_id'] ?? null, 'action' => 'playground.save_failed', 'model_type' => 'playground.editor', 'description' => $payload, 'created_at' => date('Y-m-d H:i:s')]); } catch (\Throwable $_) {}
                }
                http_response_code(500);
                echo 'Failed to save file';
            }
            exit;
        } catch (\Throwable $e) {
            $payload = '[' . date('c') . '] playground/save exception: ' . $e->getMessage();
            error_log($payload);
            http_response_code(500);
            echo 'Internal server error';
            exit;
        }
    }

    /**
     * Toggle sandbox mode
     * POST /playground/editor/toggle_sandbox
     */
    public function toggleSandbox(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $current = $_SESSION['sandbox_mode'] ?? true;
        $_SESSION['sandbox_mode'] = !$current;
        
        echo json_encode(['success' => true, 'sandbox_mode' => $_SESSION['sandbox_mode']]);
        exit;
    }

    /**
     * Session debug info
     * GET /playground/editor/session_debug
     */
    public function sessionDebug(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $isAdmin = UserController::isAdmin();
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
        
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
        
        echo json_encode([
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'sandbox_id' => $_SESSION['sandbox_id'] ?? null,
            'sandbox_mode' => $_SESSION['sandbox_mode'] ?? null,
            'editor_root' => $editorRoot,
            'is_admin' => $isAdmin,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Get file tree
     * GET /playground/editor/tree
     */
    public function tree(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id']) && empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
        
        $buildTree = function($dir, $relativePath = '') use (&$buildTree) {
            $items = [];
            $entries = @scandir($dir);
            if (!$entries) return $items;
            
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $fullPath = $dir . '/' . $entry;
                $relPath = $relativePath ? $relativePath . '/' . $entry : $entry;
                
                if (is_dir($fullPath)) {
                    $items[] = [
                        'name' => $entry,
                        'path' => $relPath,
                        'type' => 'folder',
                        'children' => $buildTree($fullPath, $relPath)
                    ];
                } else {
                    $items[] = [
                        'name' => $entry,
                        'path' => $relPath,
                        'type' => 'file',
                        'size' => filesize($fullPath)
                    ];
                }
            }
            
            usort($items, function($a, $b) {
                if ($a['type'] !== $b['type']) return $a['type'] === 'folder' ? -1 : 1;
                return strcasecmp($a['name'], $b['name']);
            });
            
            return $items;
        };
        
        echo json_encode(['tree' => $buildTree($editorRoot)]);
        exit;
    }

    /**
     * Get console environment info
     * GET /playground/console/environment
     */
    public function consoleEnvironment(): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) { 
            http_response_code(401); 
            echo json_encode(['error'=>'Unauthorized']); 
            exit; 
        }

        $editorRoot = null; $sandboxId = null; $isAdmin = false;
        try {
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
            $isAdmin = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/'));
            if (!$isAdmin) $sandboxId = basename($editorRoot);
        } catch (\Throwable $_) { $editorRoot = null; }

        $isDetectedAdmin = (!empty($_SESSION['is_admin']) || !empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin');

        $displayRoot = ROOT_PATH;
        $displayEditorRoot = $editorRoot;

        if (!$isDetectedAdmin) {
            $displayRoot = '/home';
            if (!empty($sandboxId)) {
                $displayEditorRoot = '/home/' . $sandboxId;
            } else {
                $displayEditorRoot = '/home';
            }
        }

        $out = [
            'php_version' => phpversion(),
            'php_sapi' => php_sapi_name(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'root_path' => $displayRoot,
            'editor_root' => $displayEditorRoot,
            'sandbox_id' => $sandboxId,
            'playground_use_sandbox' => $_SESSION['playground_use_sandbox'] ?? false,
            'detected_is_admin' => $isDetectedAdmin
        ];

        if ($isDetectedAdmin) {
            $out['real_root_path'] = ROOT_PATH;
            $out['real_editor_root'] = $editorRoot;
        }

        echo json_encode($out);
        exit;
    }

    /**
     * Execute console command
     * POST /playground/console/exec
     */
    public function consoleExec(): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) { 
            http_response_code(401); 
            echo json_encode(['success'=>false,'error'=>'Unauthorized']); 
            exit; 
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
            http_response_code(405); 
            echo json_encode(['success'=>false,'error'=>'Method not allowed']); 
            exit; 
        }

        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { 
            http_response_code(403); 
            echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']); 
            exit; 
        }

        $command = trim((string)($_POST['command'] ?? ''));
        if ($command === '') { 
            echo json_encode(['success'=>false,'error'=>'Empty command']); 
            exit; 
        }

        $editorRoot = null; $sandboxId = null; $isAdmin = false;
        try {
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
            $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
            $isAdmin = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/')) || !empty($_SESSION['is_admin']) || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin');
            if (!$isAdmin) $sandboxId = basename($editorRoot);
        } catch (\Throwable $_) { $editorRoot = null; }

        if (!$isAdmin) {
            if (preg_match('/[;&|<>`$()\\\\]/', $command)) {
                http_response_code(403); 
                echo json_encode(['success'=>false,'error'=>'Forbidden characters in command']); 
                exit;
            }
            $allowed = ['ls','pwd','cat','tail','head','php','node','whoami','id','grep','find','wc'];
            $parts = preg_split('/\s+/', $command);
            $base = $parts[0] ?? '';
            if (!in_array($base, $allowed, true)) {
                http_response_code(403); 
                echo json_encode(['success'=>false,'error'=>'Command not allowed for sandbox users']); 
                exit;
            }
        }

        $cwd = $editorRoot ?: getcwd();
        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = null; $output = ''; $err = '';
        $start = microtime(true);
        $timeout = 10;
        $maxBytes = 200000;
        
        try {
            if ($isAdmin) {
                $cmdSpec = ['/bin/sh', '-lc', $command];
            } else {
                $script = rtrim(ROOT_PATH, '/') . '/scripts/sandbox-run.sh';
                $cmdSpec = ['/usr/bin/env', 'bash', $script, $sandboxId, $command];
            }
            $process = proc_open($cmdSpec, $descriptors, $pipes, $cwd);
            if (!is_resource($process)) { throw new \RuntimeException('Failed to start process'); }
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = ''; $stderr = '';
            while (true) {
                $status = proc_get_status($process);
                $read = [$pipes[1], $pipes[2]];
                $write = null; $except = null;
                if (stream_select($read, $write, $except, 0, 200000)) {
                    foreach ($read as $r) {
                        $chunk = stream_get_contents($r);
                        if ($r === $pipes[1]) $stdout .= $chunk; else $stderr .= $chunk;
                        if (strlen($stdout) + strlen($stderr) > $maxBytes) break 2;
                    }
                }
                if (!$status['running']) break;
                if ((microtime(true) - $start) > $timeout) {
                    proc_terminate($process);
                    break;
                }
                usleep(100000);
            }

            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            foreach ($pipes as $p) { @fclose($p); }
            $code = proc_close($process);
            $out = trim($stdout . (strlen($stderr) ? "\nERR:\n".$stderr : ''));

            if (!$isAdmin) {
                $displayRoot = '/home';
                $displayEditorRoot = !empty($sandboxId) ? ('/home/' . $sandboxId) : '/home';
                if (!empty($editorRoot)) {
                    $realEditorRoot = rtrim($editorRoot, '/');
                    if ($realEditorRoot !== '') {
                        $out = str_replace($realEditorRoot, $displayEditorRoot, $out);
                    }
                }
                try { $out = str_replace(rtrim(ROOT_PATH, '/'), $displayRoot, $out); } catch (\Throwable $_) {}
                try { $homeEnv = getenv('HOME'); if ($homeEnv) $out = str_replace(rtrim($homeEnv, '/'), $displayRoot, $out); } catch (\Throwable $_) {}
            }

            $truncated = strlen($out) > $maxBytes;
            if ($truncated) $out = substr($out, 0, $maxBytes) . "\n...[truncated]";

            try { 
                if ($this->db) $this->db->insert('activity_logs', [
                    'user_id'=>$_SESSION['user_id'] ?? null,
                    'action'=>'playground.exec',
                    'model_type'=>'playground.console',
                    'description'=>json_encode(['cmd'=>$command,'cwd'=>$cwd,'admin'=>$isAdmin], JSON_UNESCAPED_UNICODE),
                    'created_at'=>date('Y-m-d H:i:s')
                ]); 
            } catch (\Throwable $_) {}

            echo json_encode(['success'=>true,'output'=>$out,'exit_code'=>$code,'truncated'=>$truncated]);
            exit;
        } catch (\Throwable $e) {
            if (is_resource($process)) { @proc_terminate($process); }
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>'Execution failed','message'=>$e->getMessage()]);
            exit;
        }
    }

    /**
     * Tail console logs
     * GET /playground/console/logs
     */
    public function consoleLogs(): void
    {
        if (empty($_SESSION['user_id'])) { 
            http_response_code(401); 
            echo 'Unauthorized'; 
            exit; 
        }
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 200;
        if ($lines < 1) $lines = 200;

        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage';
        $logFile = $storagePath . '/pma_debug_cli_output.txt';
        if (!file_exists($logFile)) { 
            http_response_code(200); 
            echo "(log file not found: pma_debug_cli_output.txt)"; 
            exit; 
        }

        $data = '';
        try {
            $fp = fopen($logFile, 'rb');
            if ($fp) {
                $buffer = '';
                fseek($fp, 0, SEEK_END);
                $linesFound = 0;
                while ($linesFound < $lines && ftell($fp) > 0) {
                    $seek = max(0, ftell($fp) - 4096);
                    $readLen = ftell($fp) - $seek;
                    fseek($fp, $seek);
                    $chunk = fread($fp, $readLen) . $buffer;
                    $parts = preg_split('/\r?\n/', $chunk);
                    $linesFound = count($parts) - 1;
                    $buffer = $chunk;
                    if ($seek === 0) break;
                    fseek($fp, $seek);
                    if ($seek === 0) break;
                }
                fclose($fp);
                $parts = preg_split('/\r?\n/', $buffer);
                $parts = array_filter($parts, function($r){ return $r !== ''; });
                $last = array_slice($parts, -$lines);
                echo implode("\n", $last);
                exit;
            }
        } catch (\Throwable $e) {}
        http_response_code(500); 
        echo '(failed to read logs)'; 
        exit;
    }

    /**
     * Create file or folder
     * POST /playground/editor/create
     */
    public function create(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            if (empty($_SESSION['sandbox_id'])) {
                try {
                    $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                    if (!empty($created)) {
                        $_SESSION['sandbox_id'] = basename($created);
                        if (empty($_SESSION['csrf_token'])) {
                            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                        }
                    }
                } catch (\Throwable $_) {}
            }
            if (empty($_SESSION['sandbox_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $path = $_POST['path'] ?? '';
        $type = $_POST['type'] ?? 'file';
        
        if (!$path) {
            echo json_encode(['success' => false, 'error' => 'Path required']);
            exit;
        }
        
        $safePath = str_replace(['..', '\\'], ['', '/'], $path);
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
        $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($safePath, '/');

        $normalizePath = function($p) {
            $p = str_replace('\\', '/', $p);
            $parts = array_filter(explode('/', $p), function($x){ return $x !== ''; });
            $stack = [];
            foreach ($parts as $part) {
                if ($part === '.') continue;
                if ($part === '..') { array_pop($stack); continue; }
                $stack[] = $part;
            }
            $out = implode('/', $stack);
            if ($p !== '' && $p[0] === '/') $out = '/' . $out;
            return $out;
        };

        $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
        $normalizedRoot = rtrim($normalizePath($realRoot), '/');
        $parentDir = dirname($fullPath);
        if (!is_dir($parentDir)) {
            @mkdir($parentDir, 0755, true);
        }
        $normalizedParent = $normalizePath($parentDir);
        if (strpos($normalizedParent, $normalizedRoot) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        if (file_exists($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Already exists']);
            exit;
        }
        
        $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
        $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];

        if ($type === 'folder') {
            if (@mkdir($fullPath, 0755, true)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create folder']);
            }
        } else {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (!$isAdminSession && in_array($ext, $forbiddenExt)) {
                echo json_encode(['success' => false, 'error' => 'Forbidden file type']);
                exit;
            }

            if (@file_put_contents($fullPath, '') !== false) {
                echo json_encode(['success' => true, 'encoded' => rawurlencode(base64_encode($safePath))]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create file']);
            }
        }
        exit;
    }

    /**
     * Rename file or folder
     * POST /playground/editor/rename
     */
    public function rename(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            if (empty($_SESSION['sandbox_id'])) {
                try {
                    $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                    if (!empty($created)) {
                        $_SESSION['sandbox_id'] = basename($created);
                        if (empty($_SESSION['csrf_token'])) {
                            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                        }
                    }
                } catch (\Throwable $_) {}
            }
            if (empty($_SESSION['sandbox_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $oldPath = str_replace(['..', '\\'], ['', '/'], $_POST['oldPath'] ?? '');
        $newPath = str_replace(['..', '\\'], ['', '/'], $_POST['newPath'] ?? '');
        
        if (!$oldPath || !$newPath) {
            echo json_encode(['success' => false, 'error' => 'Paths required']);
            exit;
        }
        
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
        $oldFull = rtrim($editorRoot, '/') . '/' . ltrim($oldPath, '/');
        $newFull = rtrim($editorRoot, '/') . '/' . ltrim($newPath, '/');
        
        $normalizePath = function($p) {
            $p = str_replace('\\', '/', $p);
            $parts = array_filter(explode('/', $p), function($x){ return $x !== ''; });
            $stack = [];
            foreach ($parts as $part) {
                if ($part === '.') continue;
                if ($part === '..') { array_pop($stack); continue; }
                $stack[] = $part;
            }
            $out = implode('/', $stack);
            if ($p !== '' && $p[0] === '/') $out = '/' . $out;
            return $out;
        };

        $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
        $realOld = realpath($oldFull);
        if (!$realOld || strpos($realOld, $realRoot) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Source not found']);
            exit;
        }
        
        $newParent = dirname($newFull);
        if (!is_dir($newParent)) @mkdir($newParent, 0755, true);
        $normalizedRoot = rtrim($normalizePath($realRoot), '/');
        $normalizedNew = $normalizePath($newFull);
        if (strpos($normalizedNew, $normalizedRoot) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Destination invalid']);
            exit;
        }

        if (file_exists($newFull)) {
            echo json_encode(['success' => false, 'error' => 'Destination already exists']);
            exit;
        }

        $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
        $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];
        $newExt = strtolower(pathinfo($newFull, PATHINFO_EXTENSION));
        if (!$isAdminSession && in_array($newExt, $forbiddenExt)) {
            echo json_encode(['success' => false, 'error' => 'Forbidden file type']); 
            exit;
        }

        if (@rename($oldFull, $newFull)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to rename']);
        }
        exit;
    }

    /**
     * Delete file or folder
     * POST /playground/editor/delete
     */
    public function delete(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            if (empty($_SESSION['sandbox_id'])) {
                try {
                    $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                    if (!empty($created)) {
                        $_SESSION['sandbox_id'] = basename($created);
                        if (empty($_SESSION['csrf_token'])) {
                            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                        }
                    }
                } catch (\Throwable $_) {}
            }
            if (empty($_SESSION['sandbox_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $path = str_replace(['..', '\\'], ['', '/'], $_POST['path'] ?? '');
        if (!$path) {
            echo json_encode(['success' => false, 'error' => 'Path required']);
            exit;
        }
        
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
        $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
        $realRoot = realpath($editorRoot);
        $realPath = realpath($fullPath);
        
        if (!$realPath || strpos($realPath, $realRoot) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        $protected = ['composer.json', 'composer.lock', 'src', 'public', 'vendor', '.env'];
        if (in_array(basename($path), $protected) && substr_count($path, '/') === 0) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete protected item']);
            exit;
        }
        
        $deleteRecursive = function($path) use (&$deleteRecursive) {
            if (is_dir($path)) {
                $items = array_diff(scandir($path), ['.', '..']);
                foreach ($items as $item) {
                    $deleteRecursive($path . '/' . $item);
                }
                return @rmdir($path);
            }
            return @unlink($path);
        };
        
        if ($deleteRecursive($fullPath)) {
            try {
                $sandboxId = basename($editorRoot);
                if ($this->db) {
                    $sizeAfter = 0;
                    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS));
                    foreach ($it as $f) $sizeAfter += $f->getSize();
                    $this->db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]);
                }
            } catch (\Throwable $_) {}
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete']);
        }
        exit;
    }

    /**
     * Paste (copy/move) file or folder
     * POST /playground/editor/paste
     */
    public function paste(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        if (empty($_SESSION['user_id'])) {
            if (empty($_SESSION['sandbox_id'])) {
                try {
                    $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                    if (!empty($created)) {
                        $_SESSION['sandbox_id'] = basename($created);
                        if (empty($_SESSION['csrf_token'])) {
                            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                        }
                    }
                } catch (\Throwable $_) {}
            }
            if (empty($_SESSION['sandbox_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $source = str_replace(['..', '\\'], ['', '/'], $_POST['source'] ?? '');
        $destination = str_replace(['..', '\\'], ['', '/'], $_POST['destination'] ?? '');
        $action = $_POST['action'] ?? 'copy';
        
        if (!$source) {
            echo json_encode(['success' => false, 'error' => 'Source required']);
            exit;
        }
        
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
        $srcFull = rtrim($editorRoot, '/') . '/' . ltrim($source, '/');
        $srcName = basename($source);
        $destDir = $destination ? rtrim($editorRoot, '/') . '/' . ltrim($destination, '/') : rtrim($editorRoot, '/');
        $destFull = $destDir . '/' . $srcName;
        
        $realRoot = realpath($editorRoot);
        $realSrc = realpath($srcFull);
        
        if (!$realSrc || strpos($realSrc, $realRoot) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Source not found']);
            exit;
        }
        
        if (!is_dir($destDir)) {
            echo json_encode(['success' => false, 'error' => 'Destination folder not found']);
            exit;
        }
        
        if (file_exists($destFull)) {
            $ext = pathinfo($srcName, PATHINFO_EXTENSION);
            $name = pathinfo($srcName, PATHINFO_FILENAME);
            $i = 1;
            while (file_exists($destFull)) {
                $newName = $ext ? "{$name}_copy{$i}.{$ext}" : "{$name}_copy{$i}";
                $destFull = $destDir . '/' . $newName;
                $i++;
            }
        }
        
        $copyRecursive = function($src, $dst) use (&$copyRecursive) {
            if (is_dir($src)) {
                @mkdir($dst, 0755, true);
                $items = array_diff(scandir($src), ['.', '..']);
                foreach ($items as $item) {
                    $copyRecursive($src . '/' . $item, $dst . '/' . $item);
                }
                return is_dir($dst);
            }
            return @copy($src, $dst);
        };
        
        $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
        $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];

        $srcSize = 0;
        $srcExt = null;
        if (is_dir($srcFull)) {
            try {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcFull, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) $srcSize += $f->getSize();
            } catch (\Throwable $_) { $srcSize = 0; }
        } elseif (is_file($srcFull)) {
            $srcSize = filesize($srcFull);
            $srcExt = strtolower(pathinfo($srcFull, PATHINFO_EXTENSION));
        }

        if (!$isAdminSession && $action !== 'cut') {
            if (is_file($srcFull) && in_array($srcExt ?? '', $forbiddenExt)) { 
                echo json_encode(['success' => false, 'error' => 'Forbidden file type']); 
                exit; 
            }
            if ($this->db) {
                $sandboxId = basename($editorRoot);
                $row = $this->db->get('client_sandboxes', ['quota_bytes'], ['sandbox_id' => $sandboxId]);
                $quota = $row['quota_bytes'] ?? 104857600;
                $cur = 0; 
                try { 
                    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); 
                    foreach ($it as $f) $cur += $f->getSize(); 
                } catch (\Throwable $_) { $cur = 0; }
                if (($cur + $srcSize) > $quota) { 
                    echo json_encode(['success' => false, 'error' => 'Quota exceeded']); 
                    exit; 
                }
            }
        }

        $updateUsedBytes = function() {
            try { 
                if ($this->db) { 
                    $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db ?? null, $_SESSION ?? null);
                    $sandboxId = basename($editorRoot); 
                    $sizeAfter = 0; 
                    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); 
                    foreach ($it as $f) $sizeAfter += $f->getSize(); 
                    $this->db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]); 
                } 
            } catch (\Throwable $_) {}
        };

        if ($action === 'cut') {
            if (@rename($srcFull, $destFull)) {
                $updateUsedBytes();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to move']);
            }
        } else {
            if ($copyRecursive($srcFull, $destFull)) {
                $updateUsedBytes();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to copy']);
            }
        }
        exit;
    }
}
