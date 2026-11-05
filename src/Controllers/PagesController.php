<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class PagesController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();

        // simple admin check
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        if (!$user || !in_array($user['role_id'], [1, 2])) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    public function index()
    {
        $pages = [];
        try {
            $pages = $this->db->select('pages', ['id','title','status','created_at'], ['ORDER' => ['created_at' => 'DESC']]);
        } catch (\Throwable $e) {}

        // Also build a view-scripts tree (limited to src/Views/user) so the pages index can show the file tree
        $tree = $this->buildViewScriptsTree();

        $this->view('admin/pages/index', ['title' => 'Pages', 'pages' => $pages, 'tree' => $tree]);
    }

    /**
     * Build hierarchical tree of files under src/Views/user
     * returns an array structure used by the scripts UI
     */
    private function buildViewScriptsTree(): array
    {
        $base = ROOT_PATH . '/src/Views/user';
        $tree = [];

        try {
            if (is_dir($base)) {
                $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $file) {
                    $rel = substr($file->getPathname(), strlen($base) + 1);
                    $parts = explode(DIRECTORY_SEPARATOR, $rel);
                    $node =& $tree;
                    for ($i = 0; $i < count($parts); $i++) {
                        $p = $parts[$i];
                        if ($i === count($parts) - 1) {
                            if (!isset($node[$p])) {
                                  // Build a path that is relative to project root
                                  // (so tokens decode to a path under ROOT_PATH).
                                  $relPath = 'src/Views/user/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
                                  $node[$p] = [
                                          'type' => 'file',
                                          'path' => $relPath,
                                          'encoded' => rawurlencode(base64_encode($relPath)),
                                          'mtime' => $file->getMTime()
                                      ];
                            }
                        } else {
                            if (!isset($node[$p])) {
                                $node[$p] = ['type' => 'dir', 'children' => []];
                            }
                            $node =& $node[$p]['children'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('PagesController::buildViewScriptsTree error: ' . $e->getMessage());
        }

        return $tree;
    }

    public function create()
    {
        $this->view('admin/pages/new', ['title' => 'Create Page', 'csrf_token' => generateCsrfToken()]);
    }

    public function store($input = [])
    {
        if (empty($input) && !empty($_POST)) { $input = $_POST; }
        try {
            $insert = [
                'title' => $input['title'] ?? 'Untitled',
                'slug' => $input['slug'] ?? null,
                'content' => $input['content'] ?? '',
                'status' => $input['status'] ?? 'draft',
                'author_id' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('pages', $insert);
            header('Location: /admin/pages');
            exit;
        } catch (\Throwable $e) {
            error_log('PagesController::store error: ' . $e->getMessage());
            $this->view('admin/pages/new', ['title' => 'Create Page', 'error' => $e->getMessage(), 'old' => $input]);
        }
    }

    public function show($id)
    {
        $page = $this->db->get('pages', '*', ['id' => $id]);
        if (!$page) {
            http_response_code(404);
            echo 'Page not found';
            return;
        }
        $this->view('admin/pages/show', ['title' => $page['title'], 'page' => $page]);
    }

    public function edit($id)
    {
        $page = $this->db->get('pages', '*', ['id' => $id]);
        if (!$page) {
            http_response_code(404);
            echo 'Page not found';
            return;
        }
        $this->view('admin/pages/edit', ['title' => 'Edit Page', 'page' => $page, 'csrf_token' => generateCsrfToken()]);
    }

    public function update($id, $input = [])
    {
        if (empty($input) && !empty($_POST)) { $input = $_POST; }
        try {
            $update = [
                'title' => $input['title'] ?? null,
                'slug' => $input['slug'] ?? null,
                'content' => $input['content'] ?? null,
                'status' => $input['status'] ?? 'draft',
            ];
            $this->db->update('pages', $update, ['id' => $id]);
            header('Location: /admin/pages');
            exit;
        } catch (\Throwable $e) {
            error_log('PagesController::update error: ' . $e->getMessage());
            $this->view('admin/pages/edit', ['title' => 'Edit Page', 'error' => $e->getMessage(), 'page' => array_merge($input, ['id' => $id])]);
        }
    }

    /**
     * GET /admin/pages/scripts
     * List editable user-view scripts under src/Views/user
     */
    public function scriptsIndex()
    {
        $base = ROOT_PATH . '/src/Views/user';
        $tree = [];

        $tree = $this->buildViewScriptsTree();
        $this->view('admin/pages/scripts_index', ['title' => 'User view scripts', 'tree' => $tree, 'scripts' => []]);
    }

    /**
     * GET /admin/pages/routes
     * Display a list of all registered routes (using global $ROUTE_REGISTRY)
     */
    public function routes()
    {
        $routes = [];
        try {
            // 1) Add routes registered via global $ROUTE_REGISTRY (from req() helper in web.php)
            global $ROUTE_REGISTRY;
            if (!empty($ROUTE_REGISTRY) && is_array($ROUTE_REGISTRY)) {
                foreach ($ROUTE_REGISTRY as $r) {
                    $methods = $r['methods'] ?? [];
                    $path = $r['path'] ?? '';
                    $handler = $r['handler'] ?? null;

                    // Friendly handler label
                    if (is_string($handler)) {
                        $handlerLabel = $handler;
                    } elseif ($handler instanceof \Closure) {
                        $handlerLabel = 'Closure';
                    } elseif (is_array($handler)) {
                        $handlerLabel = implode('::', $handler);
                    } elseif (is_object($handler)) {
                        $handlerLabel = get_class($handler);
                    } else {
                        $handlerLabel = is_null($handler) ? '' : json_encode($handler);
                    }

                    $routes[] = ['methods' => $methods, 'path' => $path, 'handler' => $handlerLabel];
                }
            }

            // 2) Parse route files in src/Routes for $router->get/post (absolute admin-prefixed routes)
            $routeDir = ROOT_PATH . '/src/Routes';
            if (is_dir($routeDir)) {
                $files = scandir($routeDir);
                foreach ($files as $file) {
                    if (!preg_match('/\.php$/', $file)) continue;
                    $contents = @file_get_contents($routeDir . '/' . $file);
                    if (!$contents) continue;

                    // match $router->get('/path', 'Handler') or $router->post('/path', 'Handler')
                    if (preg_match_all("/\$router->(get|post)\(\s*'([^)']+)'\s*,\s*'([^']+)'\s*\)/", $contents, $m, PREG_SET_ORDER)) {
                        foreach ($m as $match) {
                            $verb = strtoupper($match[1]);
                            $path = $match[2];
                            $handlerLabel = $match[3];
                            $routes[] = ['methods' => [$verb], 'path' => $path, 'handler' => $handlerLabel];
                        }
                    }

                    // match $req('/pages', 'Controller@method') (admin_controller_routes.php etc)
                    if (preg_match_all("/\$req\(\s*'([^']+)'\s*,\s*'([^']+)'(?:\s*,\s*([^\)]+))?\)/", $contents, $m2, PREG_SET_ORDER)) {
                        foreach ($m2 as $match) {
                            $path = $match[1];
                            $handlerLabel = $match[2];
                            $methodsRaw = trim($match[3] ?? '');
                            $methodsList = [];
                            // If a methods array literal was supplied like ['GET'] try to parse it
                            if ($methodsRaw) {
                                if (preg_match_all("/'([A-Z]+)'/", $methodsRaw, $mm)) {
                                    $methodsList = $mm[1];
                                }
                            }
                            // admin controller routes live under /admin when mounted
                            $prefixPath = 
                                '/' . ltrim($path, '/');
                            // Avoid duplicate leading slashes if path already contains '/admin'
                            if (!str_starts_with($prefixPath, '/admin')) {
                                $displayPath = '/admin' . $prefixPath;
                            } else {
                                $displayPath = $prefixPath;
                            }

                            if (empty($methodsList)) $methodsList = ['GET','POST'];
                            $routes[] = ['methods' => $methodsList, 'path' => $displayPath, 'handler' => $handlerLabel];
                        }
                    }
                }
            }

            // sort alphabetically by path for readability
            usort($routes, function($a, $b) {
                return strcmp($a['path'] ?? '', $b['path'] ?? '');
            });
        } catch (\Throwable $e) {
            error_log('PagesController::routes error: ' . $e->getMessage());
        }

        $this->view('admin/pages/routes', ['title' => 'Routes', 'routes' => $routes]);
    }

    /**
     * GET /admin/pages/scripts/edit?file=<token>
     * Show edit page for an encoded file token
     */
    public function editScript()
    {
        $enc = $_GET['file'] ?? ($_REQUEST['file'] ?? '');
        if (!$enc) {
            header('Location: /admin/pages/scripts');
            exit;
        }

        // decode token (rawurlencoded(base64)) and normalize it so it never
        // contains a leading "./" which can make option matching fail.
        $decoded = base64_decode(rawurldecode($enc));
        if (!is_string($decoded)) $decoded = '';
        // remove any leading ./ sequences introduced by older code paths
        $decoded = preg_replace('#^\./+#', '', $decoded);
        if (!$decoded) { http_response_code(400); echo 'Invalid file token'; return; }

        // normalize and prevent directory traversal
        $safeRel = str_replace(['..', '\\'], ['', '/'], $decoded);
        // ensure safeRel doesn't start with './' either
        $safeRel = preg_replace('#^\./+#', '', $safeRel);
        // Allow editing files anywhere inside the project root (admins only).
        // Keep strict normalization and enforce the final file to live under ROOT_PATH.
        $filePath = ROOT_PATH . '/' . ltrim($safeRel, '/');

        // Accept files where the directory exists inside the project root.
        // Using dirname realpath is robust for paths where the file may not
        // resolve with realpath (e.g. symlinks, non-normalised paths) but
        // still reside under ROOT_PATH.
        $realDir = realpath(dirname($filePath));
        if (!$realDir || !str_starts_with($realDir, realpath(ROOT_PATH))) {
            // Log helpful diagnostic info for admins/developers so we can
            // troubleshoot failing requests (e.g. symlinked dirs or bad
            // tokens) without exposing internals to the client.
            error_log('PagesController::editScript - directory validation failed for: ' . $filePath . ' realDir=' . var_export($realDir, true));
            // For AJAX requests provide clear JSON error for the frontend.
            if (!empty($_GET['ajax']) || !empty($_GET['xhr'])) {
                header('Content-Type: application/json; charset=utf-8', true, 403);
                echo json_encode(['error' => 'Forbidden', 'message' => 'Path is not within project root or directory does not exist.']);
                return;
            }
            http_response_code(403); echo 'Forbidden'; return;
        }

        // File must exist to be opened for edit. If the path doesn't exist
        // as provided, attempt a common fallback: many legacy tokens encode
        // only the view filename (e.g. "register.php") which lives under
        // src/Views/user — attempt that before failing.
        if (!is_file($filePath)) {
            $fallbackUserView = realpath(ROOT_PATH . '/src/Views/user/' . ltrim($safeRel, '/')) ?: false;
            if ($fallbackUserView && is_file($fallbackUserView)) {
                // Use the fallback full path
                $filePath = $fallbackUserView;
            }
        }

        if (!is_file($filePath)) {
            // Diagnostic logging to capture cases where a token decodes to a
            // path that doesn't exist. This will appear in PHP error logs.
            error_log('PagesController::editScript - file not found: ' . $filePath);
            if (!empty($_GET['ajax']) || !empty($_GET['xhr'])) {
                header('Content-Type: application/json; charset=utf-8', true, 404);
                echo json_encode(['error' => 'Not Found', 'message' => 'File does not exist']);
                return;
            }
            // For non-AJAX requests show a helpful debug page so admins can
            // quickly see what token decoded to and where the controller
            // attempted to look for the file. Keep this concise and admin-only.
            http_response_code(404);
            echo '<!doctype html><html><head><meta charset="utf-8"><title>File not found</title></head><body style="font-family:system-ui,Segoe UI,Roboto,Arial;margin:32px;color:#111">';
            echo '<h1>File not found</h1>';
            echo '<p>The file token you requested decoded to a path which does not exist on disk.</p>';
            echo '<ul style="line-height:1.5">';
            echo '<li><strong>Encoded token:</strong> ' . htmlspecialchars($enc) . '</li>';
            echo '<li><strong>Decoded token:</strong> ' . htmlspecialchars($decoded) . '</li>';
            echo '<li><strong>Sanitised relative path:</strong> ' . htmlspecialchars($safeRel) . '</li>';
            echo '<li><strong>Resolved file path:</strong> ' . htmlspecialchars($filePath) . '</li>';
            echo '</ul>';
            echo '<p>Check that the file exists inside the project root and the token is correct. If you need me to debug further, paste the token here or check your PHP error log for an entry starting with <code>PagesController::editScript</code> which contains diagnostic info.</p>';
            echo '</body></html>';
            return;
        }

        if (!is_file($filePath)) { http_response_code(404); echo 'File not found'; return; }

        $content = file_get_contents($filePath);

        // Live URL: try to build a human-friendly path (strip .php and prefix slash)
        // NOTE: some views (e.g. 'profile.php') map to routes that require
        // a user identifier. Build a safe preview route for those cases so
        // the preview iframe doesn't attempt a wrong relative path like
        // '/profile' which won't match the router.
        $clean = preg_replace('#\.php$#', '', str_replace('\\', '/', $decoded));
        $liveUrl = '/' . $clean;

        // Special-case handler for views which require identifiers in the
        // public route. For example 'profile.php' is served at
        // '/user/profile/{ident}', so construct a usable preview URL using
        // the current user's public_id (fall back to numeric id).
        try {
            if ($clean === 'profile') {
                $currentId = $_SESSION['user_id'] ?? null;
                if ($currentId) {
                    $pid = $this->db->get('users', 'public_id', ['id' => $currentId]);
                    if ($pid) {
                        $liveUrl = '/user/profile/' . rawurlencode($pid);
                    } else {
                        $liveUrl = '/user/profile/' . intval($currentId);
                    }
                }
            }
        } catch (\Throwable $_) {
            // keep fallback $liveUrl if the DB lookup fails
        }

        // Try to choose a better preview route using the global route registry
        // If a matching route exists (e.g. /api/user/commissions for commissions.php)
        // prefer API routes, then /user/ routes, then any match.
        try {
            global $ROUTE_REGISTRY;
            $previewCandidates = [];

            // Normalize a friendly path from the decoded token: strip leading src/Views if present
            $normalized = $clean;
            $normalized = preg_replace('#^(.*/)?src/Views/#', '', $normalized);

            // Base filename without extension
            $fileBase = basename($clean, '.php');

            if (!empty($ROUTE_REGISTRY) && is_array($ROUTE_REGISTRY)) {
                foreach ($ROUTE_REGISTRY as $r) {
                    $rpath = $r['path'] ?? '';
                    // exact match to normalized path (/user/network-tree/circle-view)
                    if ($rpath === '/' . $normalized) {
                        $previewCandidates[] = $rpath;
                        continue;
                    }
                    // match by last segment (commissions => /api/user/commissions)
                    if (preg_match('#/' . preg_quote($fileBase, '#') . '(?:$|/)#', $rpath)) {
                        $previewCandidates[] = $rpath;
                        continue;
                    }
                }
            }

            if (!empty($previewCandidates)) {
                // prefer API routes first
                foreach ($previewCandidates as $c) {
                    if (strpos($c, '/api/') === 0) { $liveUrl = $c; break; }
                }
                // then prefer user routes
                if ($liveUrl === '/' . $clean) {
                    foreach ($previewCandidates as $c) {
                        if (strpos($c, '/user/') === 0) { $liveUrl = $c; break; }
                    }
                }
                // fallback to first candidate
                if ($liveUrl === '/' . $clean) $liveUrl = $previewCandidates[0];

                // If the chosen route contains placeholders like {ident}, try to replace them
                if (strpos($liveUrl, '{') !== false) {
                    // special-case profile to use current user's public_id
                    if (strpos($liveUrl, 'profile') !== false) {
                        $currentId = $_SESSION['user_id'] ?? null;
                        if ($currentId) {
                            try {
                                $pid = $this->db->get('users', 'public_id', ['id' => $currentId]);
                                $replacement = $pid ? rawurlencode($pid) : intval($currentId);
                            } catch (\Throwable $_) { $replacement = intval($currentId); }
                        } else {
                            $replacement = '1';
                        }
                    } else {
                        // generic placeholder replacement: use '1' as a best-effort
                        $replacement = '1';
                    }
                    $liveUrl = preg_replace('/\{[^}]+\}/', $replacement, $liveUrl);
                }
            }
        } catch (\Throwable $_) {
            // keep existing $liveUrl if anything fails
        }

        // Build a list of sibling files in the same directory so the editor can
        // present a dropdown for quick navigation between files in the same folder.
        // dirname() returns '.' for files in the top-level folder. Normalize
        // that to an empty string so we don't produce "./file.php" rels.
        $dir = trim(dirname($safeRel), '/');
        if ($dir === '.' || $dir === '') $dir = '';
        $siblings = [];
        $dirPath = realpath(ROOT_PATH . '/' . ($dir ? $dir . '/' : '')) ?: realpath(ROOT_PATH);
        if ($dirPath && is_dir($dirPath)) {
            // Recursively find all files under the current directory and present
            // them as relative paths when listing siblings in the dropdown. We
            // keep the encoded token as the full path relative to project root
            // to match existing edit/save semantics.
            try {
                $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $file) {
                    if (!$file->isFile()) continue;
                    $abs = $file->getPathname();
                    // relative inside this directory (display label)
                    $inside = ltrim(str_replace('\\', '/', substr($abs, strlen($dirPath))), '/');
                    if ($inside === '') $inside = $file->getBasename();
                    // full relative path from project root (used for token)
                    $fullRel = ($dir ? $dir . '/' : '') . $inside;
                    $siblings[] = ['name' => $file->getBasename(), 'rel' => $fullRel, 'display' => $inside, 'encoded' => rawurlencode(base64_encode($fullRel))];
                }
            } catch (\Throwable $_) {
                // fallback: non-recursive listing
                $entries = scandir($dirPath);
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $p = $dirPath . '/' . $entry;
                    if (!is_file($p)) continue;
                    $rel = ($dir ? $dir . '/' : '') . $entry;
                    $siblings[] = ['name' => $entry, 'rel' => $rel, 'display' => $entry, 'encoded' => rawurlencode(base64_encode($rel))];
                }
            }
        }

        // If the request is an AJAX request (JS-based select navigation), return
        // JSON so the frontend can update the editor in-place without a full
        // reload. This keeps the editor state (preview, cursor, etc.) intact.
        if (!empty($_GET['ajax']) || !empty($_GET['xhr'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'file' => $decoded,
                'content' => $content,
                'encoded' => $enc,
                'live_url' => $liveUrl,
                'siblings' => $siblings
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->view('admin/pages/edit_script', ['title' => 'Edit view script', 'file' => $decoded, 'content' => $content, 'encoded' => $enc, 'live_url' => $liveUrl, 'csrf_token' => generateCsrfToken(), 'siblings' => $siblings]);
    }

    /**
     * POST /admin/pages/scripts/edit
     * Save file contents from editor
     */
    public function saveScript()
    {
        // Expect file decoded token + content
        $enc = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';

        if (!$enc) {
            http_response_code(400); echo 'Missing file token'; return;
        }

        $decoded = base64_decode(rawurldecode($enc));
        if (!$decoded) { http_response_code(400); echo 'Invalid file token'; return; }

        $safeRel = str_replace(['..', '\\'], ['', '/'], $decoded);
        // Allow saving files anywhere under project root. Ensure sanitized dest is inside ROOT_PATH.
        $filePath = ROOT_PATH . '/' . ltrim($safeRel, '/');

        $realDir = realpath(dirname($filePath)) ?: false;
        $realFile = realpath($filePath) ?: ($filePath);
                        if (!$realDir || !str_starts_with($realFile, realpath(ROOT_PATH))) {
                            error_log('PagesController::saveScript - forbidden save path: ' . $filePath . ' realFile=' . var_export($realFile, true));
                            http_response_code(403);
                            echo '<!doctype html><html><head><meta charset="utf-8"><title>Forbidden</title></head><body style="font-family:system-ui,Segoe UI,Roboto,Arial;margin:32px;color:#111">';
                            echo '<h1>Forbidden</h1>';
                            echo '<p>The requested path is not allowed. This usually indicates the decoded path would place the file outside the project root or the directory does not exist.</p>';
                            echo '<ul style="line-height:1.5">';
                            echo '<li><strong>Encoded token:</strong> ' . htmlspecialchars($enc) . '</li>';
                            echo '<li><strong>Decoded token:</strong> ' . htmlspecialchars($decoded) . '</li>';
                            echo '<li><strong>Sanitised relative path:</strong> ' . htmlspecialchars($safeRel) . '</li>';
                            echo '<li><strong>Directory realpath:</strong> ' . htmlspecialchars(var_export($realDir, true)) . '</li>';
                            echo '<li><strong>Resolved file path:</strong> ' . htmlspecialchars($filePath) . '</li>';
                            echo '</ul>';
                            echo '<p>See the PHP error log for more details (entries prefixed with <code>PagesController::editScript</code>).</p>';
                            echo '</body></html>';
                            return;
        }

        try {
            // Ensure destination dir exists
            @mkdir(dirname($filePath), 0755, true);
            // If file exists, create a timestamped backup to storage/backups/views
            if (is_file($filePath)) {
                // store backups under storage/backups/repo/<dir>
                $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage';
                $backupDir = $storagePath . '/backups/repo/' . dirname($safeRel);
                @mkdir($backupDir, 0755, true);
                $backupName = basename($filePath) . '.' . time() . '.bak';
                $backupPath = $backupDir . '/' . $backupName;
                // copy current file to backup (if possible)
                @copy($filePath, $backupPath);
            }

            file_put_contents($filePath, $content);
            // redirect back to scripts index
            header('Location: /admin/pages/scripts');
            exit;
        } catch (\Throwable $e) {
            error_log('PagesController::saveScript failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'Failed to save file';
            return;
        }
    }
}
