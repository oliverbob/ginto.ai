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
}
