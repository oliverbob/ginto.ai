<?php
/**
 * Playground - Code Editor
 * 
 * NOTE: With the LXD-based architecture, user files live INSIDE LXD containers,
 * NOT on the host filesystem. This editor connects to the sandbox proxy.
 */
// Ensure session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$__playground_repo_tree = [];
$isSessionAdmin = false;
$editorRoot = defined('ROOT_PATH') ? ROOT_PATH : '/';
$isAdminSession = false;
$sandboxId = null;
$adminForcedSandbox = false;
$clientSandboxExists = false;
$clientSandboxId = null;
try {
    // Try to obtain DB connection to allow stable sandbox mapping for non-admin users
    $db = null;
    try { $db = \Ginto\Core\Database::getInstance(); } catch (\Throwable $_) { $db = null; }

    // Load sandbox preference from DB into session BEFORE computing editor root
    if ($db && !empty($_SESSION['user_id'])) {
        try {
            $u = $db->get('users', ['playground_use_sandbox'], ['id' => $_SESSION['user_id']]);
            // Normalize to boolean
            $_SESSION['playground_use_sandbox'] = !empty($u['playground_use_sandbox']);
        } catch (\Throwable $_) {
            // ignore DB lookup failures
        }
    }

    // Check if user is admin
    $isAdminSession = !empty($_SESSION['is_admin']) ||
        (!empty($_SESSION['role_id']) && in_array((int)$_SESSION['role_id'], [1,2], true)) ||
        (!empty($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) ||
        (!empty($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') ||
        (!empty($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin');
    
    // Whether admin session has enabled sandbox mode
    $adminForcedSandbox = !empty($_SESSION['playground_use_sandbox']) || !empty($_SESSION['playground_admin_sandbox']);
    
    if ($isAdminSession && !$adminForcedSandbox) {
        // Admin without forced sandbox - use project root
        $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $sandboxId = null;
    } else {
        // Regular user or admin with forced sandbox - get sandbox ID
        $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($db ?? null, $_SESSION ?? null);
        if (!empty($sandboxId)) {
            // Store in session if not already there
            if (empty($_SESSION['sandbox_id'])) {
                $_SESSION['sandbox_id'] = $sandboxId;
            }
            // For file operations, we'll use the sandbox proxy at /clients/
            // The editorRoot is used for display only
            $editorRoot = 'sandbox'; // Marker for sandbox mode
            $clientSandboxId = $sandboxId;
            $clientSandboxExists = true;
        } else {
            // No sandbox exists - show read-only view of project root
            $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        }
    }

    // Lazily load the repo tree on the client to avoid expensive server-side
    // filesystem scans during initial page render. `window.playgroundRepoTree`
    // will be populated by client JS after the page loads.
    $__playground_repo_tree = [];
    
    // Detect whether the logged-in session is an admin
    $isSessionAdmin = IS_ADMIN;

    // DB fallback: if the session didn't explicitly mark admin, try looking up
    // the user's role via the database (helps in cases where session shape
    // differs or was populated by legacy login flows).
    if (!$isSessionAdmin && $db && !empty($_SESSION['user_id'])) {
        try {
            $u = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
            if (!empty($u) && !empty($u['role_id'])) {
                $roleRow = $db->get('roles', ['name', 'display_name'], ['id' => $u['role_id']]);
                $roleName = strtolower((string)($roleRow['display_name'] ?? $roleRow['name'] ?? ''));
                if (in_array($roleName, ['administrator', 'admin'], true)) {
                    $isSessionAdmin = true;
                }
            }
        } catch (\Throwable $_) {
            // ignore DB lookup failures - leave $isSessionAdmin as-is
        }
    }

    // Load sandbox preference from DB and set in session
    if ($db && !empty($_SESSION['user_id'])) {
        try {
            $u = $db->get('users', ['playground_use_sandbox'], ['id' => $_SESSION['user_id']]);
            $_SESSION['playground_use_sandbox'] = $u['playground_use_sandbox'] ?? false;
        } catch (\Throwable $_) {}
    }
} catch (\Throwable $e) { $__playground_repo_tree = []; }

// Handle file parameter
$file = '';
$content = '';
$encoded = '';
if (!empty($_GET['file'])) {
    $encoded = $_GET['file'];
    $decoded = base64_decode(rawurldecode($encoded));
    if ($decoded) {
        $safePath = str_replace(['..', '\\'], ['', '/'], $decoded);
        // map to editor root (admins will map to repo root; non-admins to their sandbox)
        $fullPath = (isset($editorRoot) ? rtrim($editorRoot, '/') : ROOT_PATH) . '/' . $safePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            $file = $safePath;
            $content = file_get_contents($fullPath);
        }
    }
}

// Handle AJAX requests for file content
if (!empty($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'file' => $file,
        'content' => $content,
        'encoded' => $encoded
    ]);
    exit;
}

// Detect language from file extension
$ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : 'txt';
$langMap = [
    'php' => 'php', 'js' => 'javascript', 'ts' => 'typescript', 'json' => 'json',
    'html' => 'html', 'htm' => 'html', 'css' => 'css', 'scss' => 'scss',
    'md' => 'markdown', 'sql' => 'sql', 'xml' => 'xml', 'yaml' => 'yaml', 'yml' => 'yaml',
    'sh' => 'shell', 'bash' => 'shell', 'py' => 'python', 'rb' => 'ruby'
];
$language = $langMap[$ext] ?? 'plaintext';

$pageTitle = 'Code Editor - Playground';

// Generate CSRF token
function generatePlaygroundCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generatePlaygroundCsrfToken();
?>
<?php include __DIR__ . '/parts/head.php'; ?>

<!-- Meta tags for chat integration -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
<?php
// Expose server-side admin detection to client JS. `window.DETECTED_IS_ADMIN`
// is preferred by client logic to avoid ambiguity when session shapes vary.
$detected_flag = !empty($isSessionAdmin) ? true : false;
?>
<script>
    window.CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
    window.IS_SESSION_ADMIN = <?= json_encode(!empty($isSessionAdmin)) ?>;
    window.DETECTED_IS_ADMIN = <?= json_encode($detected_flag) ?>;
    window.editorRootFromServer = <?= json_encode(realpath($editorRoot) ?: rtrim($editorRoot, '/')) ?>;
    // Expose a convenient repo root and session user to the client so the
    // Working Environment UI can suggest exact remediation commands when a
    // one-shot root install is required.
    window.REPO_ROOT = <?= json_encode(realpath(dirname(__DIR__, 3)) ?: null) ?>;
    window.WE_SESSION_USER = <?= json_encode($_SESSION['username'] ?? ($_SESSION['user']['username'] ?? null)) ?>;
</script>
<!-- Hidden assistant thoughts template: customize this HTML as your model 'thoughts' template. -->
<div id="assistant-thought-template" style="display:none;">
    <div class="sai-thinking" role="status" aria-live="polite">
        <strong>Assistant thoughts</strong>
        <div>Planning steps and analyzing repository...</div>
        <ul>
            <li class="todo-item task-current">Analyzing open file</li>
            <li class="todo-item pending">Searching for relevant functions</li>
            <li class="todo-item pending">Preparing edits</li>
        </ul>
    </div>
</div>
<?php if ($file): ?>
<meta name="repo-file" content="<?= htmlspecialchars($file) ?>">
<?php endif; ?>

<?php
// If this is a non-admin sandbox session, determine whether the corresponding
// working environment (sandbox machine) is present. The client will show a
// guided modal to install the Working Environment when the user first opens
// the editor and no machine exists yet.
$clientSandboxExists = false;
$clientSandboxId = null;
try {
    if (empty($isAdminSession) && !empty($sandboxId)) {
        $clientSandboxId = $sandboxId;
        $clientSandboxExists = \Ginto\Helpers\SandboxManager::sandboxExists($sandboxId);
    }
} catch (\Throwable $_) { $clientSandboxExists = false; }
?>

<!-- Working Environment install modal (shown only to non-admin users when no sandbox exists) -->
<div id="we-install-modal" style="display:none;">
    <div class="input-dialog-overlay" role="dialog" aria-modal="true">
        <div class="input-dialog we-dialog">
            <h3 id="we-title">Working Environment</h3>
            <div id="we-body">
                <p>This editor can automatically prepare a personal Working Environment where your files will appear at <code id="we-path">/home/.../</code>. This process will set up an isolated environment for your session.</p>
                <ol>
                    <li>Create the environment on the host.</li>
                    <li>Start the environment and mount your editor folder.</li>
                    <li>Confirm and open the editor when ready.</li>
                </ol>
                <pre id="we-log" class="we-log" aria-hidden="true"></pre>
                <div id="we-files" class="we-files" aria-hidden="true"></div>
                
            </div>
            <div class="input-dialog-buttons">
                <button id="we-cancel">Close</button>
                <button id="we-start" class="primary">Install Working Environment</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Working Environment initializer (wizard removed).
    // Keep a minimal flag object for compatibility; the install wizard
    // and its JS are intentionally not included on this page anymore.
    window.__we_init = {
        isAdmin: <?= json_encode(!empty($isSessionAdmin)) ?>,
        hasEnv: true,
        sandboxId: <?= json_encode($clientSandboxId) ?>
    };
</script>

<style>
/* Editor-specific styles */
.playground-editor-shell {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px - 2rem);
    min-height: 500px;
    background: var(--editor-bg-primary);
    border-radius: 0.375rem;
    overflow: hidden;
    border: 1px solid var(--editor-border-color);
    transition: margin-left 0.3s ease;
}

/* Adjust for sidebar state */
.main-content {
    transition: margin-left 0.3s ease, padding-left 0.3s ease;
}

/* Light mode defaults */
:root {
    --editor-bg-primary: #ffffff;
    --editor-bg-secondary: #f3f3f3;
    --editor-border-color: #e5e5e5;
    --editor-text-primary: #1e1e1e;
    --editor-text-secondary: #6e6e6e;
}

/* Dark mode overrides */
.dark {
    --editor-bg-primary: #1e1e1e;
    --editor-bg-secondary: #252526;
    --editor-border-color: #3c3c3c;
    --editor-text-primary: #cccccc;
    --editor-text-secondary: #858585;
}

.editor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 1rem;
    background: var(--editor-bg-secondary);
    border-bottom: 1px solid var(--editor-border-color);
    flex-shrink: 0;
}

.editor-main {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.file-tree {
    width: var(--file-tree-width, 260px);
    min-width: 150px;
    max-width: 50%;
    flex-shrink: 0;
    background: var(--editor-bg-secondary);
    border-right: none;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Resizer handle */
.editor-resizer {
    width: 1px; /* visually thin */
    background: transparent; /* line drawn by pseudo */
    cursor: col-resize;
    flex-shrink: 0;
    transition: background-color 0.15s;
    position: relative;
    flex: 0 0 1px;
}

.editor-resizer::before {
    content: '';
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
    width: 1px; height: 100%; background: var(--editor-border-color); opacity: 0.6; border-radius:1px;
}
.editor-resizer::after {
    /* larger invisible hover/hit surface so 1px divider is still easy to interact with */
    content: '';
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
    width: 28px; height: 100%; background: transparent; opacity: 0; pointer-events:auto; border-radius:2px;
}
.editor-resizer:hover::after { opacity: 1; background: rgba(139,92,246,0.06); }
.editor-resizer:hover::before, .editor-resizer.dragging::before { opacity: 0.95; width:2px; height:40px; background: #8b5cf6; }

.file-tree-header {
    padding: 0.75rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--editor-text-secondary);
    border-bottom: 1px solid var(--editor-border-color);
    position: sticky;
    top: 0;
    background: var(--editor-bg-secondary);
    z-index: 10;
}

.file-item:hover, .folder-item:hover {
    background: rgba(128, 128, 128, 0.1);
}

.file-item.active {
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
}

.file-item svg, .folder-item svg {
    width: 14px;
    height: 14px;
    margin-right: 4px;
    flex-shrink: 0;
}

.folder-children {
    padding-left: 0;
    position: relative;
}

.folder-item.collapsed + .folder-children {
    display: none;
}

/* Tree indent guide lines */
.tree-indent {
    display: inline-flex;
    align-items: stretch;
    flex-shrink: 0;
    align-self: stretch;
    margin-top: -2px;
    margin-bottom: -2px;
    padding-top: 2px;
    padding-bottom: 2px;
}

.tree-indent-guide {
    width: 12px;
    position: relative;
    flex-shrink: 0;
}

.tree-indent-guide::before {
    content: '';
    position: absolute;
    left: 5px;
    top: 0;
    bottom: 0;
    width: 1px;
    background: var(--editor-border-color);
}

.file-item:hover .tree-indent-guide::before,
.folder-item:hover .tree-indent-guide::before {
    background: var(--editor-text-secondary);
}

/* Ensure file/folder items stretch for indent guides */
.file-item, .folder-item {
    display: flex;
    align-items: stretch;
    padding: 0 8px;
    cursor: pointer;
    font-size: 14.0px;
    color: var(--editor-text-primary);
    transition: background-color 0.15s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 22px;
    min-height: 22px;
}

.file-item > span:last-child,
.folder-item > span:last-child {
    display: flex;
    align-items: center;
}

.file-item > svg,
.folder-item > svg,
.folder-item > .folder-toggle {
    align-self: center;
}

/* Context Menu */
.context-menu {
    position: fixed;
    z-index: 10000;
    min-width: 180px;
    background: var(--editor-bg-secondary);
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15), 0 0 1px rgba(0,0,0,0.1);
    padding: 4px 0;
    display: none;
}
.context-menu.visible {
    display: block;
}
.context-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px;
    font-size: 13px;
    color: var(--editor-text-primary);
    cursor: pointer;
    transition: background 0.1s;
}
.context-menu-item:hover {
    background: rgba(139, 92, 246, 0.15);
}
.context-menu-item.disabled {
    opacity: 0.4;
    pointer-events: none;
}
.context-menu-item svg {
    width: 14px;
    height: 14px;
    opacity: 0.7;
}
.context-menu-separator {
    height: 1px;
    background: var(--editor-border-color);
    margin: 4px 8px;
}
.context-menu-item kbd {
    margin-left: auto;
    font-size: 11px;
    opacity: 0.5;
    font-family: inherit;
}

/* Input dialog overlay */
.input-dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
}
.input-dialog {
    background: var(--editor-bg-secondary);
    border: 1px solid var(--editor-border-color);
    border-radius: 8px;
    padding: 16px;
    min-width: 320px;
    box-shadow: 0 12px 48px rgba(11,15,25,0.5);
}
.input-dialog h3 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--editor-text-primary);
}
.input-dialog input {
    width: 100%;
    padding: 8px 10px;
    font-size: 13px;
    border: 1px solid var(--editor-border-color);
    border-radius: 4px;
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    outline: none;
}
.input-dialog input:focus {
    border-color: #8b5cf6;
}
.input-dialog-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}
.input-dialog-buttons button {
    padding: 6px 14px;
    font-size: 12px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid var(--editor-border-color);
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.input-dialog-buttons button.primary {
    background: linear-gradient(90deg,#7c3aed,#6d28d9);
    border-color: rgba(0,0,0,0.08);
    color: #fff;
    padding: 6px 14px;
    border-radius: 6px;
    height: 36px;
}
.input-dialog-buttons button.danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #ffffff;
}
.input-dialog-buttons button:hover {
    opacity: 0.9;
}

.input-dialog.confirm-dialog {
    max-width: 360px;
}

/* Working Environment custom styles */
.we-dialog { max-width: 640px; padding: 20px; }
.we-log {
    height: 180px;
    overflow: auto;
    background: linear-gradient(180deg,#0b0b0b,#151515);
    color: #e6eef8;
    padding: 12px;
    border-radius: 8px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, 'Courier New', monospace;
    font-size: 12px;
    display: none;
    white-space: pre-wrap;
    border: 1px solid rgba(255,255,255,0.03);
}
.we-files { display: none; margin-top: 10px; color: var(--editor-text-secondary); font-size:13px; }
.we-label { display:none; }
.we-label input { width:16px; height:16px; }
.input-dialog-buttons button { min-width: 84px; }

.confirm-dialog p {
    margin: 0;
    font-size: 13px;
    color: var(--editor-text-secondary);
    line-height: 1.4;
}

/* Checkpoint Dialog */
.checkpoint-dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.checkpoint-dialog {
    background: var(--editor-bg-secondary);
    border: 1px solid var(--editor-border-color);
    border-radius: 8px;
    padding: 20px;
    min-width: 400px;
    max-width: 500px;
    max-height: 70vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.checkpoint-dialog h3 {
    margin: 0 0 16px;
    font-size: 16px;
    color: var(--editor-text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.checkpoint-dialog h3 svg {
    width: 20px;
    height: 20px;
    color: #8b5cf6;
}
.checkpoint-list {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 16px;
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    max-height: 300px;
}
.checkpoint-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--editor-border-color);
    cursor: pointer;
    transition: background 0.1s;
}
.checkpoint-item:last-child {
    border-bottom: none;
}
.checkpoint-item:hover {
    background: rgba(139, 92, 246, 0.1);
}
.checkpoint-item.selected {
    background: rgba(139, 92, 246, 0.2);
}
.checkpoint-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(139, 92, 246, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    flex-shrink: 0;
}
.checkpoint-icon svg {
    width: 16px;
    height: 16px;
}
.checkpoint-info {
    flex: 1;
    min-width: 0;
}
.checkpoint-name {
    font-weight: 500;
    color: var(--editor-text-primary);
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.checkpoint-time {
    font-size: 11px;
    color: var(--editor-text-secondary);
    margin-top: 2px;
}
.checkpoint-delete {
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    color: var(--editor-text-secondary);
    cursor: pointer;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.1s, background 0.1s;
}
.checkpoint-item:hover .checkpoint-delete {
    opacity: 1;
}
.checkpoint-delete:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}
.checkpoint-empty {
    padding: 24px;
    text-align: center;
    color: var(--editor-text-secondary);
    font-size: 13px;
}
.checkpoint-dialog-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}
.checkpoint-dialog-buttons button {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid var(--editor-border-color);
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    transition: all 0.15s;
}
.checkpoint-dialog-buttons button:hover {
    background: var(--editor-bg-secondary);
}
.checkpoint-dialog-buttons button.primary {
    background: #8b5cf6;
    border-color: #8b5cf6;
    color: white;
}
.checkpoint-dialog-buttons button.primary:hover {
    background: #7c3aed;
}
.checkpoint-dialog-buttons button.primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Code Block Styling with Apply Button */
.code-block-wrapper {
    position: relative;
    margin: 8px 0;
    border-radius: 6px;
    overflow: hidden;
    background: var(--editor-bg-primary);
    border: 1px solid var(--editor-border-color);
}
.code-block-wrapper .code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 10px;
    background: var(--editor-bg-secondary);
    border-bottom: 1px solid var(--editor-border-color);
    font-size: 11px;
}
.code-block-wrapper .code-lang {
    color: var(--editor-text-secondary);
    text-transform: uppercase;
    font-weight: 500;
}
.code-block-wrapper .apply-code-btn {
    padding: 3px 8px;
    font-size: 10px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid #10b981;
    background: transparent;
    color: #10b981;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 4px;
}
.code-block-wrapper .apply-code-btn:hover {
    background: #10b981;
    color: white;
}
.code-block-wrapper pre {
    margin: 0;
    padding: 10px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.5;
}
.code-block-wrapper code {
    font-family: 'Fira Code', 'Consolas', monospace;
}

/* File Write Preview Panel */
.file-write-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 10002;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.file-write-panel {
    background: var(--editor-bg-secondary);
    border: 1px solid var(--editor-border-color);
    border-radius: 8px;
    width: 90%;
    max-width: 900px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 12px 48px rgba(0,0,0,0.4);
}
.file-write-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--editor-border-color);
}
.file-write-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--editor-text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.file-write-header .status {
    font-size: 12px;
    font-weight: 400;
    color: var(--editor-text-secondary);
}
.file-write-header .streaming {
    color: #10b981;
}
.file-write-content {
    flex: 1;
    overflow: auto;
    position: relative;
}
.file-write-diff {
    display: flex;
    height: 100%;
    min-height: 300px;
}
.file-write-diff .diff-pane {
    flex: 1;
    overflow: auto;
    padding: 8px;
    font-family: 'Fira Code', 'Consolas', monospace;
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
}
.file-write-diff .diff-pane.original {
    background: rgba(239, 68, 68, 0.05);
    border-right: 1px solid var(--editor-border-color);
}
.file-write-diff .diff-pane.modified {
    background: rgba(16, 185, 129, 0.05);
}
.file-write-diff .diff-header {
    font-weight: 600;
    padding: 4px 8px;
    background: var(--editor-bg-secondary);
    border-bottom: 1px solid var(--editor-border-color);
    position: sticky;
    top: 0;
    color: var(--editor-text-secondary);
    font-size: 11px;
    text-transform: uppercase;
}
.file-write-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid var(--editor-border-color);
}
.file-write-footer button {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid var(--editor-border-color);
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    transition: all 0.15s;
}
.file-write-footer button:hover {
    background: var(--editor-bg-secondary);
}
.file-write-footer button.accept {
    background: #10b981;
    border-color: #10b981;
    color: white;
}
.file-write-footer button.accept:hover {
    background: #059669;
}
.file-write-footer button.reject {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}
.file-write-footer button.reject:hover {
    background: #dc2626;
}
/* Streaming cursor animation */
@keyframes streaming-cursor {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
.streaming-cursor {
    display: inline-block;
    width: 8px;
    height: 14px;
    background: #10b981;
    animation: streaming-cursor 0.8s ease-in-out infinite;
    vertical-align: middle;
    margin-left: 2px;
}

.editor-workspace {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
    background: var(--editor-bg-primary);
}

#monaco-editor {
    flex: 1;
    min-height: 0;
}

#editor-content {
    flex: 1;
    width: 100%;
    border: none;
    resize: none;
    padding: 1rem;
    font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    line-height: 1.5;
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    outline: none;
}

.editor-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.25rem 1rem;
    background: var(--editor-bg-secondary);
    border-top: 1px solid var(--editor-border-color);
    font-size: 0.75rem;
    color: var(--editor-text-secondary);
    flex-shrink: 0;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* File tree folder toggle */
.folder-toggle {
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 2px;
    transition: transform 0.15s;
    flex-shrink: 0;
}

.folder-toggle svg {
    width: 12px !important;
    height: 12px !important;
    margin-right: 0 !important;
}

.folder-item.collapsed .folder-toggle {
    transform: rotate(-90deg);
}

/* Action buttons */
.editor-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 500;
    border-radius: 0.375rem;
    transition: all 0.15s;
    cursor: pointer;
    border: none;
}

.editor-btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.editor-btn-primary:hover {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
}

.editor-btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.editor-btn-secondary:hover {
    background: rgba(128, 128, 128, 0.15);
}

/* File selector dropdown */
.file-select {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    max-width: 300px;
    cursor: pointer;
}

/* Toast notification */
.editor-toast {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    padding: 0.75rem 1.5rem;
    background: #10b981;
    color: white;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 9999;
}

.editor-toast.show {
    transform: translateY(0);
    opacity: 1;
}

.editor-toast.error {
    background: #ef4444;
}

/* Right pane / Chat panel */
.editor-right-pane {
    width: var(--right-pane-width, 320px);
    min-width: 200px;
    max-width: 50%;
    flex-shrink: 0;
    background: var(--editor-bg-secondary);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Override assistant-pane styles to match editor theme */
.editor-right-pane .assistant-pane {
    background: var(--editor-bg-secondary) !important;
    color: var(--editor-text-primary) !important;
}

.editor-right-pane .assistant-header {
    background: var(--editor-bg-secondary) !important;
    border-color: var(--editor-border-color) !important;
}

.editor-right-pane .assistant-body {
    background: var(--editor-bg-secondary) !important;
    color: var(--editor-text-primary) !important;
}

.editor-right-pane .assistant-footer,
.editor-right-pane .assistant-composer--vscode {
    
}

.editor-right-pane .assistant-footer {
    padding: 8px !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
}

/* Progress Tracker - full width to match composer */
.editor-right-pane .chat-progress-tracker {
    width: 100% !important;
    box-sizing: border-box !important;
    margin-bottom: 0 !important;
}

/* Rounded input container - VS Code uniform style */

.editor-right-pane .assistant-composer--vscode {
    position: relative;
    /* unified composer background so it reads as one box */
    background: var(--editor-bg-primary, #0f1724) !important;
    border: var(--assistant-token-border) !important;
    border-top-left-radius: var(--assistant-token-radius-top) !important;
    border-top-right-radius: var(--assistant-token-radius-top) !important;
    border-bottom-left-radius: var(--assistant-token-radius-bottom) !important;
    border-bottom-right-radius: var(--assistant-token-radius-bottom) !important;
    overflow: hidden !important; /* ensure neat clipped corners */
    display: flex !important;
    flex-direction: column !important;
    min-height: 20px !important;
    box-sizing: border-box !important;
    padding: var(--assistant-token-padding) !important;
    transition: border-color 0.12s, box-shadow 0.12s;
}

.editor-right-pane .assistant-composer--vscode:focus-within {
    border-color: #007acc !important;
    box-shadow: 0 0 0 2px #007acc33 !important;
}

.editor-right-pane .assistant-input {
    /* textarea is the scrollable body; min-height is one line */
    position: relative !important;
    background: transparent !important;
    color: var(--editor-text-primary, #cfcfcf) !important;
    border: none !important;
    border-radius: 0 !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 12px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    min-height: calc(var(--assistant-token-font-size) * var(--assistant-token-line-height)) !important; /* one line */
    /* let parent grow with textarea; no forced max-height */
    display: block !important;
    /* disable resizing (VS Code-like) */
    resize: none !important;
    overflow-y: auto !important;
}

.editor-right-pane .assistant-input:focus {
    outline: none !important;
    box-shadow: none !important;
}

.editor-right-pane .assistant-input::placeholder {
    color: #6e6e6e !important;
}

.editor-right-pane .assistant-input.with-overlay { padding-top: 12px !important; }

.editor-right-pane .attached-overlay {
    position: relative !important;
    background: transparent !important;
    border-bottom: none !important;
    border-radius: 8px 8px 0 0 !important;
    z-index: 30 !important;
    /* ensure overlay doesn't add top rounding */
    border-radius: 0 !important;
    z-index: 30 !important;
}

/* TEMPORARY OVERRIDE: force the composer visuals so we can verify styles are applied
   This block is intentionally high-specificity and uses !important. After verification
   we'll remove these debug markers and keep only the final tokens in CSS. */
#assistant-pane .assistant-composer--vscode {
    background: var(--editor-bg-primary, #0f1724) !important;
    border: 1px solid rgba(11, 93, 255, 1) !important;
    border-top-left-radius: 0 !important;
    border-top-right-radius: 0 !important;
    border-bottom-left-radius: 3px !important;
    border-bottom-right-radius: 3px !important;
    padding: 0px 8px !important;
    overflow: hidden !important;
    box-shadow: none !important;
}
#assistant-pane .assistant-composer--vscode .assistant-input {
    background: transparent !important;
    min-height: calc(var(--assistant-token-font-size) * var(--assistant-token-line-height)) !important;
    max-height: var(--assistant-token-maxheight) !important;
    resize: none !important;
    padding: 10px 12px !important;
}

.editor-right-pane .attach-icon {
    color: #858585 !important;
    flex-shrink: 0 !important;
}

.editor-right-pane .assistant-footer-overlay {
    position: relative !important;
    background: transparent !important;
    padding: 0px 5px !important;
    border-top: none !important;
    border-radius: 0 0 var(--assistant-token-radius-bottom) var(--assistant-token-radius-bottom) !important;
    z-index: 30 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    margin:0px !important;
}

.editor-right-pane .assistant-empty {
    color: var(--editor-text-secondary) !important;
}

/* VS Code style file attachment chip */
.editor-right-pane .attached-file {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    background: transparent !important;
    border: 1px solid rgba(255,255,255,0.06) !important;
    border-radius: var(--assistant-token-chip-radius) !important;
    padding: 6px 10px !important;
    height: var(--assistant-token-chip-height) !important;
    font-size: 13px !important;
    line-height: 1.2 !important;
}

.editor-right-pane .attached-file .file-tag {
    background: transparent !important;
    color: #c9a917 !important;
    padding: 0 6px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0 !important;
    border-radius: 4px !important;
    width: 26px !important;
    height: calc(var(--assistant-token-chip-height) - 6px) !important; /* visual balance */
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.editor-right-pane .attached-file .file-name {
    color: #cccccc !important;
    font-size: 12px !important;
    max-width: 200px !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

.editor-right-pane .as-attach {
    width: 22px !important;
    height: 22px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: transparent !important;
    border: 1px solid #444 !important;
    border-radius: 4px !important;
    font-size: 16px !important;
    font-weight: 300 !important;
    color: #858585 !important;
    cursor: pointer !important;
    transition: all 0.15s !important;
}

.editor-right-pane .as-attach:hover {
    border-color: #858585 !important;
    color: #cccccc !important;
}

.editor-right-pane .as-btn {
    color: var(--editor-text-secondary) !important;
}

.editor-right-pane .as-btn:hover {
    color: var(--editor-text-primary) !important;
    background: rgba(128, 128, 128, 0.15) !important;
}

.editor-right-pane .assistant-tab {
    color: var(--editor-text-primary) !important;
}

/* Right resizer */
.editor-resizer-right {
    width: 1px; /* thin divider visually */
    background: transparent;
    cursor: col-resize;
    flex-shrink: 0;
    transition: background-color 0.15s;
    position: relative;
    flex: 0 0 1px;
}
.editor-resizer-right::before {
    content: '';
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
    width: 1px; height: 100%; background: var(--editor-border-color); opacity: 0.6; border-radius:1px;
}
.editor-resizer-right::after {
    content: '';
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
    width: 28px; height: 100%; background: transparent; opacity: 0; pointer-events:auto; border-radius:2px;
}
.editor-resizer-right:hover::after { opacity: 1; background: rgba(139,92,246,0.06); }
.editor-resizer-right:hover::before, .editor-resizer-right.dragging::before { opacity: 0.95; width:2px; height:40px; background: #8b5cf6; }
</style>

<!-- Editor Chat CSS -->
<link href="/assets/css/editor-chat.css" rel="stylesheet">

<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    
    <?php include __DIR__ . '/parts/header.php'; ?>
    
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="main-content pt-14 min-h-screen transition-all duration-300">
        <div class="p-3 lg:p-4">
            
            <!-- Editor Shell -->
            <div class="playground-editor-shell">
                
                <!-- Header -->
                <div class="editor-header">
                    <div class="flex items-center gap-4">
                        <select id="file-select" class="file-select">
                            <option value="">Select a file...</option>
                            <?php if ($file): ?>
                            <option value="<?= htmlspecialchars($encoded) ?>" selected><?= htmlspecialchars($file) ?></option>
                            <?php endif; ?>
                        </select>
                        <span class="text-sm text-gray-500 dark:text-gray-400" id="file-path">
                            <?= $file ? htmlspecialchars($file) : 'No file selected' ?>
                        </span>
                        <?php if (!empty($sandboxId)): ?>
                            <span class="admin-sandbox-badge ml-3 text-xs px-2 py-1 rounded-md bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Sandbox • <strong><?= htmlspecialchars($sandboxId) ?></strong> — <a href="/editor">Open</a></span>
                        <?php endif; ?>
                    </div>
                        <div class="flex items-center gap-2">
                        <!-- Added small icon buttons beside the existing fullscreen (zoom) control -->
                        <div class="editor-views-controls" style="display:flex;align-items:center;gap:6px;margin-right:6px;">
                            <!-- Prominent admin sandbox control: large pill + quick 'Switch to repo'. Only shown for admins. -->
                                <div id="editor-debug-root" style="margin-left:6px;font-size:12px;color:var(--editor-text-secondary);"><?= $sandboxId ? 'Sandbox: ' . htmlspecialchars($sandboxId) : 'Root: ' . htmlspecialchars(realpath($editorRoot) ?: ROOT_PATH) ?></div>
                                <?php if (true): ?>
                                    <button id="admin-sandbox-action" class="editor-btn editor-btn-secondary" title="Toggle admin sandbox mode">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                
                                <!-- end admin control (rendered for all sessions, visually disabled when not an admin) -->
                            <button id="toggle-editor-btn" class="editor-btn editor-btn-secondary" title="Return to code view" aria-label="Return to code view">&lt;/&gt;</button>
                            
                            <button id="views-btn" type="button" class="editor-btn editor-btn-secondary" title="Open Views pane (overlay)" aria-label="Open views pane">
                                <i class="fa far fa-eye" aria-hidden="true"></i>
                            </button>
                            
                            <button id="views-debug-btn" class="editor-btn editor-btn-secondary" title="Toggle views debug" aria-label="Toggle views debug">
                                <i class="fa fa-bug" aria-hidden="true"></i>
                            </button>
                            <button id="fullscreen-btn" class="editor-btn editor-btn-secondary" title="Toggle fullscreen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                                </svg>
                            </button>
                                
                        </div>
                        <!-- Session debug panel (populated by the bug/debug button) -->
                        <pre id="session-debug-panel" style="display:none;position:absolute;right:12px;top:68px;width:360px;max-height:60vh;overflow:auto;background:var(--editor-bg-secondary);color:var(--editor-text-primary);border:1px solid var(--editor-border-color);padding:10px;border-radius:6px;z-index:1200;font-size:12px;line-height:1.25;box-shadow:0 6px 18px rgba(0,0,0,0.45);">Session debug output</pre>
                        
                        <button id="save-btn" class="editor-btn editor-btn-primary" title="Save file (Ctrl+S)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            Save
                        </button>
                    </div>
                </div>
                
                <!-- Main editor area -->
                <div class="editor-main">
                    <!-- File tree -->
                    <div class="file-tree" id="file-tree">
                        <div class="file-tree-header">Explorer</div>
                        <div id="tree-content" class="py-2">
                            <!-- Tree will be rendered by JS -->
                        </div>
                    </div>
                    
                    <!-- Context Menu -->
                    <div id="file-context-menu" class="context-menu">
                        <div class="context-menu-item" data-action="new-file">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            New File
                        </div>
                        <div class="context-menu-item" data-action="new-folder">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                            New Folder
                        </div>
                        <div class="context-menu-separator"></div>
                        <div class="context-menu-item" data-action="cut">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"/></svg>
                            Cut<kbd>Ctrl+X</kbd>
                        </div>
                        <div class="context-menu-item" data-action="copy">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Copy<kbd>Ctrl+C</kbd>
                        </div>
                        <div class="context-menu-item" data-action="paste">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Paste<kbd>Ctrl+V</kbd>
                        </div>
                        <div class="context-menu-separator"></div>
                        <div class="context-menu-item" data-action="rename">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Rename<kbd>F2</kbd>
                        </div>
                        <div class="context-menu-item" data-action="delete">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete<kbd>Del</kbd>
                        </div>
                        <div class="context-menu-separator"></div>
                        <div class="context-menu-item" data-action="copy-path">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            Copy Path
                        </div>
                        <div class="context-menu-separator"></div>
                        <?php if ($isAdminSession): ?>
                        <div class="context-menu-item" data-action="create-checkpoint">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                            Create Checkpoint
                        </div>
                        <div class="context-menu-item" data-action="restore-checkpoint">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/></svg>
                            Restore Checkpoint...
                        </div>
                        <?php endif; ?>
                        <div class="context-menu-separator"></div>
                        <div class="context-menu-item" data-action="refresh">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Refresh
                        </div>
                    </div>
                    
                    <!-- Resizer -->
                    <div class="editor-resizer" id="editor-resizer"></div>
                    
                    <!-- Editor workspace -->
                    <div class="editor-workspace">
                        <div id="monaco-editor"></div>
                        <textarea id="editor-content" spellcheck="false" style="display:none;"><?= htmlspecialchars($content) ?></textarea>
                    </div>
                    
                    <!-- Right Resizer -->
                    <div class="editor-resizer editor-resizer-right" id="editor-resizer-right"></div>
                    
                    <!-- Right Pane / Chat -->
                    <div class="editor-right-pane" id="editor-right-pane">
                        <div id="assistant-pane" class="assistant-pane" role="complementary">
                            <div class="assistant-header">
                                <div class="assistant-title-row">
                                    <div class="assistant-tabs" id="chat-tabs-container">
                                        <button class="assistant-tab active" data-tab-id="1" aria-pressed="true"><span class="tab-label">Chat 1</span><span class="tab-close" title="Close tab">×</span></button>
                                    </div>
                                    <div class="assistant-controls" role="toolbar" aria-label="Assistant controls">
                                        <button class="as-btn" id="editor-tts-toggle" title="Toggle TTS">🔊</button>
                                        <button class="as-btn" id="editor-stt-toggle" title="Start/Stop STT">🎤</button>
                                        <button class="as-btn" id="new-chat-tab-btn" title="New conversation">＋</button>
                                        <button class="as-btn" id="clear-chat-btn" title="Clear current chat">🗑</button>
                                        <button class="as-btn" title="History">⤺</button>
                                        <button class="as-btn" title="Settings">⚙</button>
                                        <div class="as-divider" aria-hidden="true"></div>
                                        <button class="as-btn as-close" title="Close pane">✕</button>
                                    </div>
                                </div>
                                <!-- TTS/STT status bar -->
                                <div class="assistant-audio-status" id="assistant-audio-status" style="display:flex;gap:8px;padding:4px 10px;font-size:11px;color:var(--editor-text-secondary);align-items:center;">
                                    <span id="editor-tts-state" title="TTS status">TTS: off</span>
                                    <span>|</span>
                                    <span id="editor-stt-state" title="STT status">STT: idle</span>
                                    <label style="margin-left:auto;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                        <input type="checkbox" id="editor-auto-run" style="width:12px;height:12px;">
                                        <span>Auto-run tools</span>
                                    </label>
                                </div>
                                <div class="assistant-divider"></div>
                            </div>

                            <div class="assistant-body" id="assistant-body" aria-live="polite">
                                <div class="assistant-empty">No conversation yet — ask me about the open file.</div>
                            </div>
                            
                            <!-- File Write Preview Panel (hidden by default) -->
                            <div class="file-write-preview" id="file-write-preview" style="display:none;">
                                <div class="file-write-header">
                                    <span class="file-write-title">📝 Writing to: <span id="file-write-path"></span></span>
                                    <div class="file-write-actions">
                                        <button class="file-write-btn file-write-apply" id="file-write-apply" title="Apply changes">✓ Apply</button>
                                        <button class="file-write-btn file-write-cancel" id="file-write-cancel" title="Cancel">✕ Cancel</button>
                                    </div>
                                </div>
                                <div class="file-write-content" id="file-write-content"></div>
                                <div class="file-write-progress" id="file-write-progress">
                                    <div class="file-write-progress-bar"></div>
                                </div>
                            </div>

                            <div class="assistant-footer">
                                <!-- Progress Tracker (VS Code style) -->
                                <div class="chat-progress-tracker" id="chat-progress-tracker" style="display:none;">
                                    <div class="tracker-section tracker-todos" id="tracker-todos">
                                        <div class="tracker-row">
                                            <button class="tracker-header" id="tracker-todos-toggle" aria-expanded="false">
                                                <svg class="tracker-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                    <path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span class="tracker-label">Todos</span>
                                                <span class="tracker-count" id="tracker-todos-count">(0/0)</span>
                                            </button>
                                        </div>
                                        <div class="tracker-content" id="tracker-todos-content" style="display:none;">
                                            <ul class="tracker-list" id="tracker-todos-list"></ul>
                                        </div>
                                    </div>
                                    <div class="tracker-section tracker-files" id="tracker-files">
                                        <div class="tracker-row">
                                            <button class="tracker-header" id="tracker-files-toggle" aria-expanded="false">
                                                <svg class="tracker-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                    <path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span class="tracker-label">Files changed</span>
                                                <span class="tracker-stats" id="tracker-files-stats"></span>
                                            </button>
                                            <div class="tracker-actions" id="tracker-files-actions" style="display:none;">
                                                <button class="tracker-action-btn tracker-keep" id="tracker-keep-btn" title="Keep all changes">Keep</button>
                                                <button class="tracker-action-btn tracker-undo" id="tracker-undo-btn" title="Undo all changes">Undo</button>
                                            </div>
                                        </div>
                                        <div class="tracker-content" id="tracker-files-content" style="display:none;">
                                            <ul class="tracker-list" id="tracker-files-list"></ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Composer input area -->
                                <div class="assistant-composer--vscode">
                                    <div class="assistant-composer-header">
                                        <div class="assistant-composer-header-left">
                                            <div class="attached-overlay" aria-hidden="false">
                                        <svg class="attach-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M13.5 7.5L7.5 13.5C6.12 14.88 3.88 14.88 2.5 13.5C1.12 12.12 1.12 9.88 2.5 8.5L9 2C9.93 1.07 11.43 1.07 12.36 2C13.29 2.93 13.29 4.43 12.36 5.36L6.36 11.36C5.97 11.75 5.34 11.75 4.95 11.36C4.56 10.97 4.56 10.34 4.95 9.95L10.45 4.45" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                        </svg>
                                        <div class="attached-file" data-path="<?= htmlspecialchars($file) ?>">
                                            <span class="file-tag"><?= strtoupper($ext) ?></span>
                                            <span class="file-name"><?= $file ? basename($file) : 'No file' ?></span>
                                        </div>
                                        <button id="assistant-attach" class="as-btn as-attach" title="Attach another file">+</button>
                                            </div>
                                        </div>
                                        <div class="assistant-composer-header-right">
                                            <!-- header-right reserved for future controls (left-aligned layout requires a right container for spacing only) -->
                                        </div>
                                    </div>

                                    <textarea id="assistant-input" class="assistant-input with-overlay" placeholder="Describe what to build next" style="min-height:calc(var(--assistant-token-font-size) * var(--assistant-token-line-height));max-height:var(--assistant-token-maxheight);resize:none;"></textarea>

                                    <div class="assistant-footer-overlay" aria-hidden="false">
                                        <div class="assistant-left-tools">
                                            <div class="assistant-tools">
                                                <button class="as-fab" title="Options">⚒</button>
                                            </div>
                                        </div>
                                        <div class="assistant-right-tools">
                                            <button id="assistant-send" class="assistant-send small" aria-label="Send message" title="Send message" type="button">
                                                <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                                    <path fill="currentColor" d="M2 21L23 12L2 3v7l13 2-13 2v7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status bar -->
                <div class="editor-status-bar">
                    <div class="status-item">
                        <span>Ln <span id="line-num">1</span>, Col <span id="col-num">1</span></span>
                        <span class="mx-2">|</span>
                        <span id="lang-display"><?= strtoupper($language) ?></span>
                    </div>
                    <div class="status-item">
                        <span id="editor-status">Ready</span>
                    </div>
                </div>
            </div>
            
        </div>
    </main>
    
    <!-- Toast notification -->
    <div id="editor-toast" class="editor-toast"></div>
    
    <!-- Hidden form for saving -->
    <form id="save-form" method="post" action="/playground/editor/save" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="file" id="save-file" value="<?= htmlspecialchars($encoded) ?>">
        <input type="hidden" name="content" id="save-content" value="">
    </form>
    
    <?php include __DIR__ . '/parts/footer.php'; ?>
    
    <script>
        // Make repo tree available to JS
        window.playgroundRepoTree = <?= json_encode($__playground_repo_tree, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        window.currentFile = <?= json_encode($file) ?>;
        window.currentEncoded = <?= json_encode($encoded) ?>;
        window.currentLanguage = <?= json_encode($language) ?>;
    </script>
    
    <script>
    // --- WebSocket for live streaming ---
    const RATChet_PORT = 31827;
    let ws = null;
    let wsConnected = false;
    let wsReconnectTimeout = null;
    // Poll fallback when WebSocket is unavailable
    let _wsPollTimer = null;
    const WS_POLL_INTERVAL = 3000; // ms - how often to poll tree when WS is down
    let _wsReconnectDelay = 2000; // starting reconnect delay
    const WS_RECONNECT_MAX = 30000; // cap backoff at 30s
    // Debounced tree refresh to avoid excessive AJAX calls when multiple file events arrive
    let _refreshTreeTimer = null;
    function refreshTreeDebounced(delay = 400) {
        if (_refreshTreeTimer) clearTimeout(_refreshTreeTimer);
        _refreshTreeTimer = setTimeout(async () => {
            try {
                await refreshTree();
                try { showToast('Explorer updated'); } catch(_) {}
            } catch (_) {}
        }, delay);
    }

    function connectWebSocket() {
        // Connect to the Ratchet file-write streamer endpoint using the
        // current page hostname to avoid issues where 'localhost' resolves
        // to IPv6 (::1) but Ratchet is only listening on IPv4 (127.0.0.1).
        let host = window.location.hostname || '';
        if (!host || host === '0.0.0.0' || host === '::' || host === '::1') host = '127.0.0.1';
        // If the page was opened via an IP address like 0.0.0.0 (dev server),
        // prefer connecting to localhost/127.0.0.1 where Ratchet listens.
        const proto = (location.protocol === 'https:') ? 'wss' : 'ws';
        ws = new WebSocket(`${proto}://${host}:${RATChet_PORT}/stream`);
        ws.onopen = function() {
            wsConnected = true;
            console.log('Ratchet WebSocket connected');
            // stop any polling fallback once connected
            stopWsPoll();
            // reset reconnect backoff
            _wsReconnectDelay = 2000;
        };
        ws.onclose = function() {
            wsConnected = false;
            console.log('Ratchet WebSocket disconnected');
            // Start polling fallback so explorer remains responsive
            startWsPoll();
            // Try to reconnect with exponential backoff
            if (wsReconnectTimeout) clearTimeout(wsReconnectTimeout);
            wsReconnectTimeout = setTimeout(function(){ connectWebSocket(); }, _wsReconnectDelay);
            _wsReconnectDelay = Math.min(_wsReconnectDelay * 2, WS_RECONNECT_MAX);
        };
        ws.onerror = function(e) {
            console.error('Ratchet WebSocket error:', e);
            // ensure we fall back to polling when errors occur
            wsConnected = false;
            startWsPoll();
        };
        ws.onmessage = function(event) {
            // Expect JSON: { type: 'stream', file, content, ... }
            try {
                const msg = JSON.parse(event.data);
                if (msg.type === 'stream') {
                    // If the stream targets the currently-open file, show preview
                    if (msg.file === window.currentFile) {
                        const preview = document.getElementById('file-write-preview');
                        const previewContent = document.getElementById('file-write-content');
                        const previewPath = document.getElementById('file-write-path');
                        if (preview && previewContent && previewPath) {
                            preview.style.display = 'block';
                            previewPath.textContent = msg.file;
                            previewContent.textContent = msg.content;
                        }
                    } else {
                        // For other files (created/updated in sandbox), refresh the explorer tree
                        try { refreshTreeDebounced(); } catch (_) {}
                    }
                }
            } catch (e) {
                console.error('WebSocket message parse error:', e);
            }
        };
    }
    connectWebSocket();

    // Start a periodic poll to refresh the tree when WebSocket is not available.
    function startWsPoll() {
        try {
            if (_wsPollTimer) return;
            // Run an immediate refresh to pick up any recent changes
            refreshTreeDebounced(50);
            _wsPollTimer = setInterval(() => {
                try { if (!wsConnected) refreshTreeDebounced(); } catch(e) { console.debug('wsPoll error', e); }
            }, WS_POLL_INTERVAL);
            console.log('Started WS fallback polling every', WS_POLL_INTERVAL, 'ms');
        } catch (e) { console.debug('startWsPoll failed', e); }
    }

    function stopWsPoll() {
        try {
            if (_wsPollTimer) { clearInterval(_wsPollTimer); _wsPollTimer = null; console.log('Stopped WS fallback polling'); }
        } catch (e) { console.debug('stopWsPoll failed', e); }
    }

    // Kick off polling if no successful connection within a short window
    setTimeout(() => { if (!wsConnected) startWsPoll(); }, 1200);

    // Busy indicator helpers used during transitions (toggle sandbox, refresh tree)
    const editorStatus = document.getElementById('editor-status');
    function setBusy(msg) {
        try { if (editorStatus) editorStatus.textContent = msg || 'Working...'; } catch(_){}
        try { if (adminAction) adminAction.disabled = true; } catch(_){}
    }
    function clearBusy() {
        try { if (editorStatus) editorStatus.textContent = 'Ready'; } catch(_){}
        try { if (adminAction) adminAction.disabled = false; } catch(_){}
    }

    // Prevent accidental full-page navigation when clicking anchor links
    // that only contain a hash or point back to the same editor URL with
    // a fragment. In some cases these were causing the editor to be loaded
    // inside itself (nested view). We allow links with an explicit
    // target (e.g. _blank) to function normally.
    document.addEventListener('click', function (ev) {
        try {
            const a = ev.target.closest && ev.target.closest('a');
            if (!a) return;
            const href = a.getAttribute('href');
            const target = a.getAttribute('target');
            if (!href) return;
            if (target && target !== '_self') return; // allow _blank etc
            // plain '#' anchors
            if (href === '#') { ev.preventDefault(); ev.stopPropagation(); return; }
            // same-page fragment links pointing to this editor URL
            const locBase = window.location.href.split('#')[0];
            if (href.indexOf('#') !== -1) {
                // absolute or relative same-page fragment
                if (href === locBase + href.substring(href.indexOf('#')) || href.startsWith(window.location.pathname + '#') || href.startsWith('#')) {
                    ev.preventDefault(); ev.stopPropagation(); return;
                }
            }
        } catch (e) { /* ignore */ }
    }, true);

    (function() {
        const textarea = document.getElementById('editor-content');
        const monacoContainer = document.getElementById('monaco-editor');
        const saveBtn = document.getElementById('save-btn');
        const fullscreenBtn = document.getElementById('fullscreen-btn');
        const fileSelect = document.getElementById('file-select');
        const filePath = document.getElementById('file-path');
        const lineNum = document.getElementById('line-num');
        const colNum = document.getElementById('col-num');
        const langDisplay = document.getElementById('lang-display');
        const editorStatus = document.getElementById('editor-status');
        const toast = document.getElementById('editor-toast');
        const treeContent = document.getElementById('tree-content');
        const fileTree = document.getElementById('file-tree');
        const resizer = document.getElementById('editor-resizer');
        
        let monacoEditor = null;
        let monacoReady = false;
        let isDirty = false;

        const AUTO_SAVE_DELAY = 8000;
        let autoSaveTimer = null;
        let autoSaveInProgress = false;
        let lastSavedContent = textarea.value || '';
        
        // ========== Resizable sidebar ==========
        const SIDEBAR_WIDTH_KEY = 'playground-editor-sidebar-width';
        let isResizing = false;
        
        // Restore saved width
        const savedWidth = localStorage.getItem(SIDEBAR_WIDTH_KEY);
        if (savedWidth) {
            fileTree.style.width = savedWidth + 'px';
        }
        
        // Use PointerEvents + pointer capture for robust drag behaviour
        // (prevents iframes/overlays from stealing the pointer during a drag)
        (function(){
            let dragging = false, startX = 0, startWidth = 0, activePointerId = null;
            const usingPointer = !!window.PointerEvent;
            function setSidebarWidth(w) {
                const parentRect = fileTree.parentElement.getBoundingClientRect();
                const clamped = Math.max(150, Math.min(w, parentRect.width * 0.5));
                fileTree.style.width = clamped + 'px';
                if (fileTree.style.flex !== undefined) fileTree.style.flex = '0 0 ' + Math.round(clamped) + 'px';
                try { if (monacoEditor && typeof monacoEditor.layout === 'function') monacoEditor.layout(); } catch(e){}
            }

            function onPointerMove(e){
                if (!dragging) return;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const delta = clientX - startX;
                setSidebarWidth(Math.round(startWidth + delta));
            }

            function stopDrag(e){
                if (!dragging) return;
                dragging = false; resizer.classList.remove('dragging'); fileTree.classList.remove('resizing');
                try { localStorage.setItem(SIDEBAR_WIDTH_KEY, parseInt(fileTree.style.width)); } catch(e){}
                try { if (activePointerId && resizer.releasePointerCapture) resizer.releasePointerCapture(activePointerId); } catch(e){}
                activePointerId = null;
                try { document.removeEventListener('pointermove', onPointerMove); } catch(e){}
                try { document.removeEventListener('pointerup', stopDrag); } catch(e){}
                try { document.removeEventListener('mousemove', onPointerMove); } catch(e){}
                try { document.removeEventListener('mouseup', stopDrag); } catch(e){}
            }

            if (usingPointer) {
                resizer.addEventListener('pointerdown', function(ev){
                    try {
                        if (ev.isPrimary === false) return;
                        dragging = true; startX = ev.clientX; startWidth = Math.round(fileTree.getBoundingClientRect().width);
                        activePointerId = ev.pointerId; try { if (resizer.setPointerCapture) resizer.setPointerCapture(activePointerId); } catch(e){}
                        resizer.classList.add('dragging'); fileTree.classList.add('resizing');
                        document.addEventListener('pointermove', onPointerMove);
                        document.addEventListener('pointerup', stopDrag);
                        ev.preventDefault();
                    } catch(e){}
                });
            } else {
                resizer.addEventListener('mousedown', function(ev){
                    dragging = true; startX = ev.clientX; startWidth = Math.round(fileTree.getBoundingClientRect().width);
                    resizer.classList.add('dragging'); fileTree.classList.add('resizing');
                    document.addEventListener('mousemove', onPointerMove);
                    document.addEventListener('mouseup', stopDrag);
                    ev.preventDefault();
                });
            }

            // Touch support
            resizer.addEventListener('touchstart', function(ev){
                dragging = true; startX = ev.touches[0].clientX; startWidth = Math.round(fileTree.getBoundingClientRect().width);
                resizer.classList.add('dragging'); fileTree.classList.add('resizing');
                document.addEventListener('touchmove', onPointerMove, { passive: false });
                document.addEventListener('touchend', stopDrag);
                ev.preventDefault();
            }, { passive: false });
        })();
        
        // ========== Resizable right pane (Chat) ==========
        const RIGHT_PANE_WIDTH_KEY = 'playground-editor-right-pane-width';
        const rightPane = document.getElementById('editor-right-pane');
        const rightResizer = document.getElementById('editor-resizer-right');
        let isResizingRight = false;
        
        // Restore saved right pane width
        const savedRightWidth = localStorage.getItem(RIGHT_PANE_WIDTH_KEY);
        if (savedRightWidth && rightPane) {
            rightPane.style.width = savedRightWidth + 'px';
        }
        
        if (rightResizer && rightPane) {
            // Use pointer capture for right resizer (mirrors admin approach)
            (function(){
                let draggingR = false, startXR = 0, startWidthR = 0, activePointerIdR = null;
                const usingPointerR = !!window.PointerEvent;
                function setRightWidth(w) {
                    const containerRect = rightPane.parentElement.getBoundingClientRect();
                    const clamped = Math.max(200, Math.min(w, containerRect.width * 0.5));
                    rightPane.style.width = clamped + 'px';
                    rightPane.style.flex = '0 0 ' + Math.round(clamped) + 'px';
                    try { if (monacoEditor && typeof monacoEditor.layout === 'function') monacoEditor.layout(); } catch(e){}
                }

                function onPointerMoveR(e){
                    if (!draggingR) return;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const delta = clientX - startXR;
                    const newWidth = Math.round(startWidthR - delta);
                    setRightWidth(newWidth);
                }

                function stopDragR(e){
                    if (!draggingR) return; draggingR = false;
                    rightResizer.classList.remove('dragging'); rightPane.classList.remove('resizing');
                    try { localStorage.setItem(RIGHT_PANE_WIDTH_KEY, Math.round(rightPane.getBoundingClientRect().width)); } catch(e){}
                    try { if (activePointerIdR && rightResizer.releasePointerCapture) rightResizer.releasePointerCapture(activePointerIdR); } catch(e){}
                    activePointerIdR = null;
                    try { document.removeEventListener('pointermove', onPointerMoveR); } catch(e){}
                    try { document.removeEventListener('pointerup', stopDragR); } catch(e){}
                    try { document.removeEventListener('mousemove', onPointerMoveR); } catch(e){}
                    try { document.removeEventListener('mouseup', stopDragR); } catch(e){}
                }

                if (usingPointerR) {
                    rightResizer.addEventListener('pointerdown', function(ev){
                        try {
                            if (ev.isPrimary === false) return;
                            draggingR = true; startXR = ev.clientX; startWidthR = Math.round(rightPane.getBoundingClientRect().width);
                            activePointerIdR = ev.pointerId; try { if (rightResizer.setPointerCapture) rightResizer.setPointerCapture(activePointerIdR); } catch(e){}
                            rightResizer.classList.add('dragging'); rightPane.classList.add('resizing');
                            document.addEventListener('pointermove', onPointerMoveR);
                            document.addEventListener('pointerup', stopDragR);
                            ev.preventDefault();
                        } catch(e){}
                    });
                } else {
                    rightResizer.addEventListener('mousedown', function(ev){
                        draggingR = true; startXR = ev.clientX; startWidthR = Math.round(rightPane.getBoundingClientRect().width);
                        rightResizer.classList.add('dragging'); rightPane.classList.add('resizing');
                        document.addEventListener('mousemove', onPointerMoveR);
                        document.addEventListener('mouseup', stopDragR);
                        ev.preventDefault();
                    });
                }

                rightResizer.addEventListener('touchstart', function(ev){
                    draggingR = true; startXR = ev.touches[0].clientX; startWidthR = Math.round(rightPane.getBoundingClientRect().width);
                    rightResizer.classList.add('dragging'); rightPane.classList.add('resizing');
                    document.addEventListener('touchmove', onPointerMoveR, { passive: false });
                    document.addEventListener('touchend', stopDragR);
                    ev.preventDefault();
                }, { passive: false });

            })();
        }

        // --- Views overlay logic (copied / adapted from admin edit_script preview) ---
        const viewsBtn = document.getElementById('views-btn');
        const viewsIsolationToggle = document.getElementById('views-isolation-toggle');
        const viewsDebugBtn = document.getElementById('views-debug-btn');
        const codeViewBtn = document.getElementById('toggle-editor-btn');

        function positionViewsOverlay(overlay, isFullscreen) {
            const workspace = document.querySelector('.editor-workspace');
            const canvas = document.getElementById('editor-canvas');
            const target = workspace || canvas || document.body;
            if (overlay.parentElement !== target) target.appendChild(overlay);
            try { target.style.position = target.style.position || 'relative'; } catch(e) {}
            overlay.style.position = 'absolute';
            overlay.style.inset = '0';
            overlay.style.zIndex = '50';
            overlay.style.overflow = 'auto';
        }

        // Open-only behavior: views button opens overlay; the code view or clicking editor hides it.
        function openViewsOverlay() {
            try {
                const overlayId = 'editor-views-overlay';
                const isFS = !!document.fullscreenElement || document.body.classList.contains('editor-fullscreen');
                let overlay = document.getElementById(overlayId);

                const isLive = (viewsIsolationToggle && viewsIsolationToggle.dataset && viewsIsolationToggle.dataset.mode) ? viewsIsolationToggle.dataset.mode === 'live' : true;

                // If overlay exists and is hidden, show and refresh content. If visible, do nothing (open only)
                if (overlay) {
                    if (overlay.style.display === 'none' || getComputedStyle(overlay).display === 'none') {
                        const f = overlay.querySelector('iframe');
                        overlay.style.display = 'flex';
                        overlay.style.background = '#ffffff';
                        overlay.style.pointerEvents = 'auto';
                        if (f) {
                            // update src/srcdoc to current content
                            try { f.srcdoc = getEditorContent(); } catch(e) { }
                            f.style.pointerEvents = 'auto';
                        }
                        positionViewsOverlay(overlay, isFS);
                    }
                    return;
                }

                // Create overlay
                overlay = document.createElement('div');
                overlay.id = overlayId;
                overlay.style.display = 'flex';
                overlay.style.flexDirection = 'column';
                overlay.style.background = '#ffffff';
                overlay.style.color = '#000';
                overlay.style.pointerEvents = 'auto';
                positionViewsOverlay(overlay, isFS);

                const iframe = document.createElement('iframe');
                // For views we render srcdoc of the current editor content (useful for quick previews)
                try {
                    if (!isLive) iframe.sandbox = 'allow-scripts';
                    iframe.srcdoc = getEditorContent();
                } catch(e) { iframe.srcdoc = getEditorContent(); }

                iframe.style.width = '100%'; iframe.style.height = '100%'; iframe.style.border = '0'; iframe.style.flex = '1';
                iframe.style.pointerEvents = 'auto'; iframe.style.touchAction = 'manipulation';

                overlay.appendChild(iframe);

                // append overlay to the workspace (positionViewsOverlay ensures proper parent)
                // clicking inside the workspace (code area) should hide the overlay — user requested code click returns to code view
                try {
                    const ws = document.querySelector('.editor-workspace');
                    if (ws) {
                        const closeOnClick = function(ev){
                            try {
                                const el = document.getElementById(overlayId);
                                if (el && el.style.display !== 'none') {
                                    // Only close when click target is inside workspace and not on overlay itself
                                    if (!el.contains(ev.target)) return; // if overlay contains target, ignore (iframe interaction)
                                }
                                // if click occurs on editor workspace (not overlay), hide the overlay
                                const ov = document.getElementById(overlayId);
                                if (ov) ov.style.display = 'none';
                            } catch(e){}
                        };
                        // Use an explicit handler on the workspace so clicks into the editor area (monaco/textarea)
                        // will close the overlay when they occur.
                        ws.addEventListener('pointerdown', function evh(e){
                            // If the overlay is visible and event target is inside workspace (editor), close overlay
                            const ov = document.getElementById(overlayId);
                            if (ov && ov.style.display !== 'none') {
                                // ensure the click isn't on the overlay itself
                                if (ov.contains(e.target)) return;
                                ov.style.display = 'none';
                            }
                        });
                    }
                } catch(e) {}

            } catch (e) { console.warn('opening views overlay failed', e); }
        }

        // Views debug toggle (no-op placeholder, preserved service parity with admin preview)
        let viewsDebugEnabled = false;
        function setViewsDebugState(on) {
            viewsDebugEnabled = !!on;
            try { localStorage.setItem('ginto.views.debug', viewsDebugEnabled ? '1' : '0'); } catch(e){}
            if (viewsDebugBtn) {
                viewsDebugBtn.style.background = viewsDebugEnabled ? 'rgba(255,240,200,0.06)' : '';
                viewsDebugBtn.setAttribute('aria-pressed', viewsDebugEnabled ? 'true' : 'false');
            }
        }

        // Hide overlay helper
        function hideViewsOverlay() {
            try { const o = document.getElementById('editor-views-overlay'); if (o) o.style.display = 'none'; } catch(e){}
        }

        if (viewsBtn) viewsBtn.addEventListener('click', openViewsOverlay);
        if (codeViewBtn) codeViewBtn.addEventListener('click', hideViewsOverlay);
        if (viewsDebugBtn) viewsDebugBtn.addEventListener('click', function(){ setViewsDebugState(!viewsDebugEnabled); });
        if (viewsIsolationToggle) {
            viewsIsolationToggle.addEventListener('click', function(){
                try {
                    const el = this;
                    const current = el.dataset.mode || 'live';
                    const next = (current === 'live') ? 'isolated' : 'live';
                    el.dataset.mode = next;
                    el.setAttribute('title', 'Views mode: ' + (next === 'live' ? 'Live (no sandbox)' : 'Isolated (sandbox)'));
                    el.setAttribute('aria-pressed', next === 'isolated' ? 'true' : 'false');
                    // If overlay exists update iframe sandbox accordingly
                    const overlay = document.getElementById('editor-views-overlay');
                    if (overlay && overlay.style.display !== 'none') {
                        const f = overlay.querySelector('iframe');
                        if (f) {
                            if (next === 'live') {
                                try { f.removeAttribute('sandbox'); } catch(e){}
                            } else {
                                try { f.sandbox = 'allow-scripts'; } catch(e){}
                            }
                        }
                    }
                } catch(e){}
            });
        }

        // Hide overlay on file change (if we ever implement file switching) — keep behavior consistent with admin
        if (fileSelect) fileSelect.addEventListener('change', function(){ hideViewsOverlay(); });
        
        // Language detection
        const extToLang = {
            php: 'php', js: 'javascript', ts: 'typescript', json: 'json',
            html: 'html', htm: 'html', css: 'css', scss: 'scss',
            md: 'markdown', sql: 'sql', xml: 'xml', yaml: 'yaml', yml: 'yaml',
            sh: 'shell', bash: 'shell', py: 'python', rb: 'ruby', txt: 'plaintext'
        };
        
        function getLanguage(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            return extToLang[ext] || 'plaintext';
        }
        
        // Toast notifications
        function showToast(msg, isError = false) {
            toast.textContent = msg;
            toast.className = 'editor-toast show' + (isError ? ' error' : '');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
        
        // Update cursor position
        function updateCursor() {
            if (monacoReady && monacoEditor) {
                const pos = monacoEditor.getPosition();
                lineNum.textContent = pos.lineNumber;
                colNum.textContent = pos.column;
            } else {
                const pos = textarea.selectionStart || 0;
                const before = textarea.value.slice(0, pos);
                const lines = before.split('\n');
                lineNum.textContent = lines.length;
                colNum.textContent = (lines[lines.length - 1] || '').length + 1;
            }
        }

        function getEditorContent() {
            return monacoReady && monacoEditor ? monacoEditor.getValue() : textarea.value;
        }

        function normalizeRepoPathLocal(path) {
            if (!path || typeof path !== 'string') return '';
            return path
                .replace(/\\/g, '/').replace(/^\.\/+/, '').replace(/\/{2,}/g, '/');
        }

        function getInlineFileWriteState() {
            try {
                if (window.__gintoFileWrite && typeof window.__gintoFileWrite.getState === 'function') {
                    return window.__gintoFileWrite.getState();
                }
            } catch (e) {}
            return null;
        }

        function isInlineTypingBusy(state) {
            if (!state) return false;
            if (state.inlineTypingActive) return true;
            if (state.inlineTypingTimer) return true;
            if (state.inlineTypingQueue && state.inlineTypingQueue.length) return true;
            return false;
        }

        function waitForInlineTypingToFinish(targetPathNormalized) {
            return new Promise((resolve) => {
                const check = () => {
                    const state = getInlineFileWriteState();
                    if (!state) return resolve();
                    const normalized = state.pathNormalized || normalizeRepoPathLocal(state.path);
                    if (!isInlineTypingBusy(state) || !targetPathNormalized || normalized !== targetPathNormalized) {
                        resolve();
                    } else {
                        requestAnimationFrame(check);
                    }
                };
                requestAnimationFrame(check);
            });
        }

        function clearAutoSaveTimer() {
            if (autoSaveTimer) {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = null;
            }
        }

        function scheduleAutoSave() {
            if (!window.currentEncoded) return;
            if (!isDirty) return;
            clearAutoSaveTimer();
            autoSaveTimer = setTimeout(() => {
                autoSaveTimer = null;
                runAutoSave();
            }, AUTO_SAVE_DELAY);
        }

        async function runAutoSave() {
            if (autoSaveInProgress) return;
            if (!isDirty) return;
            const inlineState = getInlineFileWriteState();
            const currentNormalized = normalizeRepoPathLocal(window.currentFile);
            const inlineNormalized = inlineState ? (inlineState.pathNormalized || normalizeRepoPathLocal(inlineState.path)) : '';

            if (
                inlineState &&
                inlineState.mode === 'inline' &&
                inlineNormalized &&
                inlineNormalized === currentNormalized &&
                isInlineTypingBusy(inlineState)
            ) {
                await waitForInlineTypingToFinish(inlineNormalized);
                if (isDirty) {
                    scheduleAutoSave();
                }
                return;
            }

            await performSave({ silent: true, reason: 'auto' });
        }

        async function ensurePendingChangesSaved() {
            if (!isDirty) return true;

            const inlineState = getInlineFileWriteState();
            const currentNormalized = normalizeRepoPathLocal(window.currentFile);
            const inlineNormalized = inlineState ? (inlineState.pathNormalized || normalizeRepoPathLocal(inlineState.path)) : '';

            if (
                inlineState &&
                inlineState.mode === 'inline' &&
                inlineNormalized &&
                inlineNormalized === currentNormalized &&
                isInlineTypingBusy(inlineState)
            ) {
                await waitForInlineTypingToFinish(inlineNormalized);
                clearAutoSaveTimer();
                return true;
            }

            clearAutoSaveTimer();

            if (autoSaveInProgress) {
                await new Promise((resolve) => {
                    const check = () => {
                        if (!autoSaveInProgress) {
                            resolve();
                        } else {
                            requestAnimationFrame(check);
                        }
                    };
                    requestAnimationFrame(check);
                });
            }

            const saved = await performSave({ silent: true, reason: 'auto' });
            return saved;
        }
        
        // Get expanded folders from localStorage
        const EXPANDED_KEY = 'playground-editor-expanded-folders';
        let expandedFolders = new Set();
        try {
            const stored = localStorage.getItem(EXPANDED_KEY);
            if (stored) expandedFolders = new Set(JSON.parse(stored));
        } catch (e) {}
        
        function saveExpandedFolders() {
            try {
                localStorage.setItem(EXPANDED_KEY, JSON.stringify([...expandedFolders]));
            } catch (e) {}
        }
        
        // Render file tree
        function renderTree(tree, container, level = 0, pathPrefix = '') {
            const sorted = Object.entries(tree).sort((a, b) => {
                const aIsDir = a[1].type === 'dir' || a[1].children;
                const bIsDir = b[1].type === 'dir' || b[1].children;
                if (aIsDir && !bIsDir) return -1;
                if (!aIsDir && bIsDir) return 1;
                return a[0].localeCompare(b[0]);
            });
            
            // Generate indent guides HTML
            function getIndentGuides(lvl) {
                if (lvl === 0) return '';
                let guides = '<span class="tree-indent">';
                for (let i = 0; i < lvl; i++) {
                    guides += '<span class="tree-indent-guide"></span>';
                }
                guides += '</span>';
                return guides;
            }
            
            sorted.forEach(([name, item]) => {
                const fullPath = pathPrefix ? `${pathPrefix}/${name}` : name;
                
                if (item.type === 'dir' || item.children) {
                    const folder = document.createElement('div');
                    const isExpanded = expandedFolders.has(fullPath);
                    folder.className = 'folder-item' + (isExpanded ? '' : ' collapsed');
                    folder.dataset.path = fullPath;
                    folder.style.paddingLeft = '8px';
                    folder.innerHTML = `
                        ${getIndentGuides(level)}
                        <span class="folder-toggle">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                        <svg class="text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                        <span>${name}</span>
                    `;
                    folder.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const isNowCollapsed = folder.classList.toggle('collapsed');
                        if (isNowCollapsed) {
                            expandedFolders.delete(fullPath);
                        } else {
                            expandedFolders.add(fullPath);
                        }
                        saveExpandedFolders();
                    });
                    container.appendChild(folder);
                    
                    const children = document.createElement('div');
                    children.className = 'folder-children';
                    renderTree(item.children || item, children, level + 1, fullPath);
                    container.appendChild(children);
                } else if (item.type === 'file') {
                    const file = document.createElement('div');
                    file.className = 'file-item';
                    file.style.paddingLeft = '8px';
                    file.dataset.encoded = item.encoded;
                    file.dataset.path = item.path;
                    
                    if (item.path === window.currentFile) {
                        file.classList.add('active');
                    }
                    
                    const ext = name.split('.').pop().toLowerCase();
                    let iconColor = 'text-gray-400';
                    if (['php'].includes(ext)) iconColor = 'text-purple-500';
                    else if (['js', 'ts'].includes(ext)) iconColor = 'text-yellow-500';
                    else if (['html', 'htm'].includes(ext)) iconColor = 'text-orange-500';
                    else if (['css', 'scss'].includes(ext)) iconColor = 'text-blue-500';
                    else if (['json'].includes(ext)) iconColor = 'text-green-500';
                    else if (['md'].includes(ext)) iconColor = 'text-cyan-500';
                    
                    file.innerHTML = `
                        ${getIndentGuides(level)}
                        <svg class="${iconColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>${name}</span>
                    `;
                    
                    file.addEventListener('click', () => loadFile(item.encoded, item.path));
                    container.appendChild(file);
                }
            });
        }
        
        // Load file
        async function loadFile(encoded, path) {
            if (isDirty) {
                const saved = await ensurePendingChangesSaved();
                if (!saved && isDirty) {
                    showToast('Autosave failed; fix issues before switching files.', true);
                    return;
                }
            }
            
            try {
                editorStatus.textContent = 'Loading...';
                const res = await fetch(`/playground/editor?file=${encodeURIComponent(encoded)}&ajax=1`);
                const data = await res.json();
                
                window.currentFile = data.file;
                window.currentEncoded = data.encoded;
                
                filePath.textContent = data.file || 'No file selected';
                document.getElementById('save-file').value = data.encoded;
                
                const lang = getLanguage(data.file);
                window.currentLanguage = lang;
                langDisplay.textContent = lang.toUpperCase();
                
                if (monacoReady && monacoEditor) {
                    monaco.editor.setModelLanguage(monacoEditor.getModel(), lang);
                    monacoEditor.setValue(data.content || '');
                } else {
                    textarea.value = data.content || '';
                }
                
                // Update active state in tree
                document.querySelectorAll('.file-item').forEach(el => el.classList.remove('active'));
                document.querySelector(`.file-item[data-path="${data.file}"]`)?.classList.add('active');
                
                isDirty = false;
                editorStatus.textContent = 'Ready';
                lastSavedContent = getEditorContent();
                clearAutoSaveTimer();
                
                // Update URL without reload
                history.pushState({}, '', `/playground/editor?file=${encodeURIComponent(encoded)}`);
                
                // Update attached file in chat pane
                const attachedFile = document.querySelector('.attached-file');
                if (attachedFile && data.file) {
                    const ext = data.file.split('.').pop().toUpperCase();
                    const fileTag = attachedFile.querySelector('.file-tag');
                    const fileName = attachedFile.querySelector('.file-name');
                    if (fileTag) fileTag.textContent = ext;
                    if (fileName) fileName.textContent = data.file.split('/').pop();
                    attachedFile.dataset.path = data.file;
                }
                
                // If the Views overlay is open, update its iframe to show the newly-loaded file
                try {
                    const overlay = document.getElementById('editor-views-overlay');
                    const isLive = (viewsIsolationToggle && viewsIsolationToggle.dataset && viewsIsolationToggle.dataset.mode) ? viewsIsolationToggle.dataset.mode === 'live' : true;
                    if (overlay && overlay.style.display !== 'none') {
                        const f = overlay.querySelector('iframe');
                        if (f) {
                            try {
                                // Use the file content for preview (srcdoc) so unsaved edits are visible
                                f.srcdoc = data.content || '';
                                if (isLive) {
                                    try { f.removeAttribute('sandbox'); } catch(_){}
                                } else {
                                    try { f.sandbox = 'allow-scripts'; } catch(_){}
                                }
                            } catch(e) { console.warn('Failed to update views iframe', e); }
                        }
                    }
                } catch(e) { console.warn('views overlay update failed', e); }
            } catch (e) {
                console.error('Load failed:', e);
                showToast('Failed to load file', true);
                editorStatus.textContent = 'Error';
            }
        }
        
        async function performSave(options = {}) {
            const { silent = false, reason = 'manual' } = options;
            const isAuto = reason === 'auto';

            if (!window.currentEncoded) {
                if (!silent) showToast('No file selected', true);
                return false;
            }

            const content = getEditorContent();

            if (isAuto) {
                if (!isDirty && content === lastSavedContent) {
                    return true;
                }
                if (autoSaveInProgress) {
                    return false;
                }
                
                autoSaveInProgress = true;
            }

            const statusMessage = isAuto ? 'Autosaving...' : 'Saving...';
            const errorStatus = isAuto ? 'Autosave failed' : 'Error';

            try {
                editorStatus.textContent = statusMessage;
                const res = await fetch('/playground/editor/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: document.querySelector('input[name="csrf_token"]').value,
                        file: window.currentEncoded,
                        content: content
                    })
                });

                if (!res.ok) {
                    throw new Error('Save failed');
                }

                isDirty = false;
                lastSavedContent = content;
                clearAutoSaveTimer();

                if (!silent) {
                    showToast('File saved successfully!');
                }

                editorStatus.textContent = isAuto ? 'Autosaved' : 'Saved';
                setTimeout(() => {
                    if (!isDirty) editorStatus.textContent = 'Ready';
                }, 2000);
                return true;
            } catch (e) {
                console.error('Save failed:', e);
                if (!silent) {
                    showToast('Failed to save file', true);
                }
                editorStatus.textContent = errorStatus;
                if (isAuto) {
                    setTimeout(() => {
                        if (isDirty) {
                            editorStatus.textContent = 'Modified';
                            scheduleAutoSave();
                        }
                    }, 2500);
                } else {
                    setTimeout(() => {
                        if (isDirty) {
                            editorStatus.textContent = 'Modified';
                            scheduleAutoSave();
                        }
                    }, 2000);
                }
                return false;
            } finally {
                if (isAuto) {
                    autoSaveInProgress = false;
                }
            }
        }

        // Save file
        async function saveFile(evt) {
            if (evt && typeof evt.preventDefault === 'function') {
                evt.preventDefault();
            }
            clearAutoSaveTimer();
            await performSave({ silent: false, reason: 'manual' });
        }
        
        // Initialize Monaco
        function initMonaco() {
            if (typeof require === 'undefined') {
                const loader = document.createElement('script');
                loader.src = '/assets/vendor/monaco-editor/min/vs/loader.js';
                loader.onload = bootstrapMonaco;
                loader.onerror = () => {
                    // Try CDN fallback
                    const cdn = document.createElement('script');
                    cdn.src = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.39.0/min/vs/loader.js';
                    cdn.onload = bootstrapMonaco;
                    cdn.onerror = () => {
                        textarea.style.display = 'block';
                        monacoContainer.style.display = 'none';
                    };
                    document.head.appendChild(cdn);
                };
                document.head.appendChild(loader);
            } else {
                bootstrapMonaco();
            }
        }
        
        function bootstrapMonaco() {
            try {
                require.config({ paths: { vs: '/assets/vendor/monaco-editor/min/vs' } });
            } catch (e) {
                try {
                    require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.39.0/min/vs' } });
                } catch (e2) {}
            }
            
            // Get current theme from playground localStorage or HTML class
            function getCurrentTheme() {
                // Check localStorage first (playground uses 'playground-theme')
                const stored = localStorage.getItem('playground-theme');
                if (stored === 'dark') return 'dark';
                if (stored === 'light') return 'light';
                // Fall back to HTML class
                if (document.documentElement.classList.contains('dark')) return 'dark';
                // Fall back to system preference
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
                return 'light';
            }
            
            function syncMonacoTheme() {
                if (window.monaco && window.monaco.editor) {
                    const theme = getCurrentTheme();
                    monaco.editor.setTheme(theme === 'dark' ? 'vs-dark' : 'vs');
                }
            }
            
            require(['vs/editor/editor.main'], function() {
                const isDark = getCurrentTheme() === 'dark';
                
                monacoEditor = monaco.editor.create(monacoContainer, {
                    value: textarea.value,
                    language: window.currentLanguage || 'plaintext',
                    theme: isDark ? 'vs-dark' : 'vs',
                    automaticLayout: true,
                    minimap: { enabled: true, side: 'right' },
                    scrollBeyondLastLine: false,
                    fontSize: 13,
                    wordWrap: 'off',
                    renderLineHighlight: 'all',
                    lineNumbers: 'on',
                    folding: true,
                    tabSize: 4,
                    insertSpaces: true,
                    // Enable Monaco's built-in sticky scroll for function/class scope visibility
                    stickyScroll: {
                        enabled: true,
                        maxLineCount: 5,
                        defaultModel: 'outlineModel'
                    }
                });
                
                textarea.style.display = 'none';
                monacoContainer.style.display = 'block';
                monacoReady = true;
                
                // Track changes
                monacoEditor.onDidChangeModelContent(() => {
                    isDirty = true;
                    editorStatus.textContent = 'Modified';
                    scheduleAutoSave();
                });
                
                monacoEditor.onDidChangeCursorPosition(updateCursor);
                
                // Ctrl+S to save
                monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, saveFile);
                
                // Sync theme with system preference changes
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncMonacoTheme);
                
                // Watch for class changes on documentElement (theme toggle)
                const observer = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        if (mutation.attributeName === 'class') {
                            syncMonacoTheme();
                        }
                    }
                });
                observer.observe(document.documentElement, { attributes: true });
                
                // Also poll localStorage for theme changes (backup method)
                let lastTheme = getCurrentTheme();
                setInterval(() => {
                    const currentTheme = getCurrentTheme();
                    if (currentTheme !== lastTheme) {
                        lastTheme = currentTheme;
                        syncMonacoTheme();
                    }
                }, 300);
                
                updateCursor();
            });
        }
        
        // Fullscreen toggle
        fullscreenBtn.addEventListener('click', () => {
            const shell = document.querySelector('.playground-editor-shell');
            if (!document.fullscreenElement) {
                shell.requestFullscreen?.() || shell.webkitRequestFullscreen?.();
            } else {
                document.exitFullscreen?.() || document.webkitExitFullscreen?.();
            }
        });
        
        // Save button
        saveBtn.addEventListener('click', saveFile);

        // Admin sandbox UI wiring (big pill + switch-to-repo)
        try {
            const adminAction = document.getElementById('admin-sandbox-action');
            const pillLabel = document.getElementById('admin-sandbox-action-label');
            const pillDot = document.getElementById('admin-sandbox-action-dot');
            const viewsDebugBtn = document.getElementById('views-debug-btn');
            const sessionDebugPanel = document.getElementById('session-debug-panel');

            // Prefer server-computed `DETECTED_IS_ADMIN` when available so
            // the client UI matches the server's classification.
            const isSessionAdmin = !!(typeof window.DETECTED_IS_ADMIN !== 'undefined' ? window.DETECTED_IS_ADMIN : window.IS_SESSION_ADMIN);
            let currentUseSandbox = <?= $adminForcedSandbox ? 'true' : 'false' ?>;

            function setAdminUI(useSandbox, sandboxId) {
                currentUseSandbox = useSandbox;
                if (adminAction) adminAction.style.color = useSandbox ? '#16a34a' : '#6b7280';
                // update debug overlay - with LXD containers, show "Sandbox: {id}" instead of path
                try { const dbg = document.getElementById('editor-debug-root'); if (dbg) dbg.textContent = (useSandbox && sandboxId ? 'Sandbox: ' + sandboxId : 'Root: ' + (typeof editorRootFromServer !== 'undefined' ? editorRootFromServer : 'Project root')); } catch(_){}
                // update header badge
                try {
                    const header = document.querySelector('.editor-header .flex.items-center.gap-4');
                    if (header) {
                        const existing = header.querySelector('.admin-sandbox-badge');
                        if (existing) existing.remove();
                        if (useSandbox && sandboxId) {
                            const badge = document.createElement('span');
                            badge.className = 'admin-sandbox-badge ml-3 text-xs px-2 py-1 rounded-md bg-yellow-100 text-yellow-800';
                            // Use /clients/ route (proxied via port 1800, sandbox from session)
                            const clientUrl = '/clients/';
                            badge.innerHTML = 'Sandbox • <strong>' + escapeHtml(sandboxId) + '</strong> — <a href="/editor">Open</a>';
                            header.appendChild(badge);
                        }
                    }
                } catch (e) { console.warn('updateAdminUI header failed', e); }
            }

            // initial state
            (async function initAdminUI() {
                try {
                    const initialUse = <?= $adminForcedSandbox ? 'true' : 'false' ?>;
                    // get sandbox id from server endpoint by asking current tree root
                    let sid = <?= json_encode($sandboxId ?: null) ?>;
                    setAdminUI(initialUse, sid);
                    // If the persisted state is UNSANDBOXED (admin view), ensure
                    // we fetch a fresh tree from the server so the explorer is
                    // populated immediately (same as the second-click behavior).
                    try {
                        if (!initialUse) {
                            await refreshTree();
                        }
                    } catch (_err) {
                        // ignore refresh errors during init
                    }
                } catch (_) {}
            })();

                // Wire the existing bug/debug button to toggle and fetch a session debug view
                if (viewsDebugBtn && sessionDebugPanel) {
                    viewsDebugBtn.addEventListener('click', async function (e) {
                        e.preventDefault();
                        try {
                            if (sessionDebugPanel.style.display === 'block') {
                                sessionDebugPanel.style.display = 'none';
                                return;
                            }
                            sessionDebugPanel.textContent = 'Loading session...';
                            sessionDebugPanel.style.display = 'block';
                            const res = await fetch('/playground/editor/session_debug?ajax=1', { credentials: 'same-origin' });
                            if (!res.ok) {
                                sessionDebugPanel.textContent = 'Failed to load session: ' + res.status;
                                return;
                            }
                            const j = await res.json().catch(() => null);
                            if (!j || !j.success) {
                                sessionDebugPanel.textContent = 'Failed to parse session response';
                                return;
                            }
                            sessionDebugPanel.textContent = JSON.stringify(j.session, null, 2);
                        } catch (err) {
                            sessionDebugPanel.textContent = 'Error fetching session: ' + (err.message || String(err));
                        }
                    });
                }

            if (adminAction) {
                adminAction.addEventListener('click', async (e) => {
                    e.preventDefault();
                    try {
                        setBusy('Switching workspace...');
                        // Determine next desired state (toggle)
                        const next = currentUseSandbox ? '0' : '1';
                        const res = await fetch('/playground/editor/toggle_sandbox', {
                            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ csrf_token: window.CSRF_TOKEN || document.querySelector('input[name="csrf_token"]')?.value || '', use_sandbox: next })
                        });
                        const j = await res.json().catch(()=>({ success: false, error: 'invalid-json' }));
                        if (!res.ok || !j.success || !j.csrf_ok) throw new Error(j.error || 'toggle_failed_or_unapproved');
                        // Update CSRF token if returned
                        if (j.csrf_token) { window.CSRF_TOKEN = j.csrf_token; try { const meta = document.querySelector('meta[name="csrf-token"]'); if (meta) meta.setAttribute('content', j.csrf_token); } catch(_){}
                        }
                        // Update UI based on server response
                        setAdminUI(!!j.use_sandbox, j.sandbox_id || null);
                        // refresh tree (and clear busy indicator afterwards)
                        try {
                            await (async () => {
                                const treeRes = await fetch('/playground/editor/tree', { credentials: 'same-origin' });
                                if (treeRes.ok) {
                                    const treeJson = await treeRes.json();
                                    window.playgroundRepoTree = treeJson.tree || {};
                                    const container = document.getElementById('tree-content');
                                    if (container) { container.innerHTML = ''; renderTree(window.playgroundRepoTree, container); }
                                }
                            })();
                        } catch (_){
                            // ignore tree refresh errors but still clear busy
                        } finally {
                            clearBusy();
                        }
                    } catch (err) {
                        console.error('admin action toggle failed', err);
                        clearBusy();
                        showToast('Failed to toggle admin sandbox', true);
                    }
                });
            }
        } catch (e) { console.warn('admin UI init error', e); }
        
        // Keyboard shortcut for textarea fallback
        textarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveFile();
            }
        });
        
        textarea.addEventListener('input', () => {
            isDirty = true;
            editorStatus.textContent = 'Modified';
            scheduleAutoSave();
            updateCursor();
        });
        
        textarea.addEventListener('click', updateCursor);
        textarea.addEventListener('keyup', updateCursor);
        
        // Render file tree (initially empty). Lazy-load the full tree via AJAX
        // to prevent blocking PHP on large repositories or client sandboxes.
        renderTree(window.playgroundRepoTree, treeContent);

        // Fetch the full tree asynchronously and render when available.
        (async function(){
            try {
                const treeRes = await fetch('/playground/editor/tree', { credentials: 'same-origin' });
                if (treeRes.ok) {
                    const treeJson = await treeRes.json().catch(()=>null);
                    if (treeJson && treeJson.tree) {
                        window.playgroundRepoTree = treeJson.tree || {};
                        const container = document.getElementById('tree-content');
                        if (container) { container.innerHTML = ''; renderTree(window.playgroundRepoTree, container); }
                    }
                }
            } catch (e) {
                console.warn('Failed to lazy-load playground tree', e);
            }
        })();
        
        // Initialize Monaco
        initMonaco();
        
        // Update attached file display when file changes
        function updateAttachedFile() {
            const attachedFile = document.querySelector('.attached-file');
            if (attachedFile && window.currentFile) {
                const ext = window.currentFile.split('.').pop().toUpperCase();
                const fileTag = attachedFile.querySelector('.file-tag');
                const fileName = attachedFile.querySelector('.file-name');
                if (fileTag) fileTag.textContent = ext;
                if (fileName) fileName.textContent = window.currentFile.split('/').pop();
                attachedFile.dataset.path = window.currentFile;
            }
        }
        
        // Call on init
        updateAttachedFile();
        
        // ========== Context Menu ==========
        const contextMenu = document.getElementById('file-context-menu');
        let contextTarget = null; // The file/folder item that was right-clicked
        let clipboard = { path: null, action: null }; // For cut/copy operations
        
        function hideContextMenu() {
            contextMenu.classList.remove('visible');
            contextTarget = null;
        }
        
        function showContextMenu(e, target) {
            e.preventDefault();
            contextTarget = target;
            
            // Position menu
            const x = Math.min(e.clientX, window.innerWidth - 200);
            const y = Math.min(e.clientY, window.innerHeight - 350);
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            
            // Determine if we have a valid file/folder selected
            const hasSelection = target && target.type !== 'root' && target.path;
            
            // Update paste state
            const pasteItem = contextMenu.querySelector('[data-action="paste"]');
            if (pasteItem) {
                pasteItem.classList.toggle('disabled', !clipboard.path);
            }
            
            // Disable actions that require a selected file/folder when nothing is selected
            ['cut', 'copy', 'rename', 'delete', 'copy-path'].forEach(action => {
                const item = contextMenu.querySelector(`[data-action="${action}"]`);
                if (item) {
                    item.classList.toggle('disabled', !hasSelection);
                }
            });
            
            contextMenu.classList.add('visible');
        }
        
        // Right-click on file tree pane (entire pane including header and content)
        document.getElementById('file-tree').addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const item = e.target.closest('.file-item, .folder-item');
            if (item) {
                // Clicked on a file or folder
                const isFolder = item.classList.contains('folder-item');
                showContextMenu(e, { 
                    type: isFolder ? 'folder' : 'file', 
                    path: item.dataset.path 
                });
            } else {
                // Clicked on empty space or header - root context
                showContextMenu(e, { type: 'root', path: '' });
            }
        });
        
        // Hide on click outside
        document.addEventListener('click', (e) => {
            if (!contextMenu.contains(e.target)) {
                hideContextMenu();
            }
        });
        
        // Hide on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') hideContextMenu();
        });
        
        // Input dialog helper
        function showInputDialog(title, defaultValue = '') {
            return new Promise((resolve) => {
                const overlay = document.createElement('div');
                overlay.className = 'input-dialog-overlay';
                overlay.innerHTML = `
                    <div class="input-dialog">
                        <h3>${title}</h3>
                        <input type="text" value="${defaultValue}" autofocus>
                        <div class="input-dialog-buttons">
                            <button class="cancel">Cancel</button>
                            <button class="primary confirm">OK</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);
                
                const input = overlay.querySelector('input');
                const confirm = overlay.querySelector('.confirm');
                const cancel = overlay.querySelector('.cancel');
                
                input.select();
                
                function close(value) {
                    document.body.removeChild(overlay);
                    resolve(value);
                }
                
                confirm.onclick = () => close(input.value.trim());
                cancel.onclick = () => close(null);
                overlay.onclick = (e) => { if (e.target === overlay) close(null); };
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') close(input.value.trim());
                    if (e.key === 'Escape') close(null);
                };
            });
        }

        function showDeleteConfirmDialog(options = {}) {
            const {
                title = 'Delete Item',
                message = 'Delete this item? This cannot be undone.',
                confirmText = 'Delete',
                cancelText = 'Cancel'
            } = options;

            return new Promise((resolve) => {
                const overlay = document.createElement('div');
                overlay.className = 'input-dialog-overlay';

                const dialog = document.createElement('div');
                dialog.className = 'input-dialog confirm-dialog';
                dialog.setAttribute('role', 'alertdialog');
                dialog.setAttribute('aria-modal', 'true');

                const heading = document.createElement('h3');
                heading.textContent = title;
                dialog.appendChild(heading);

                if (message) {
                    const description = document.createElement('p');
                    description.textContent = message;
                    dialog.appendChild(description);
                }

                const buttons = document.createElement('div');
                buttons.className = 'input-dialog-buttons';

                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'cancel';
                cancelBtn.textContent = cancelText;

                const confirmBtn = document.createElement('button');
                confirmBtn.className = 'confirm';
                confirmBtn.classList.add('danger');
                confirmBtn.textContent = confirmText;

                buttons.appendChild(cancelBtn);
                buttons.appendChild(confirmBtn);

                dialog.appendChild(buttons);
                overlay.appendChild(dialog);
                document.body.appendChild(overlay);

                function cleanup() {
                    document.removeEventListener('keydown', keyHandler, true);
                }

                function close(result) {
                    cleanup();
                    overlay.remove();
                    resolve(result);
                }

                function keyHandler(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        close(false);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        close(true);
                    }
                }

                cancelBtn.addEventListener('click', () => close(false));
                confirmBtn.addEventListener('click', () => close(true));
                overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
                document.addEventListener('keydown', keyHandler, true);

                setTimeout(() => confirmBtn.focus(), 0);
            });
        }
        
        // File operations
        async function createNewFile(parentPath) {
            const name = await showInputDialog('New File Name:');
            if (!name) return;
            
            const path = parentPath ? `${parentPath}/${name}` : name;
            try {
                const res = await fetch('/playground/editor/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: document.querySelector('input[name="csrf_token"]').value,
                        path: path,
                        type: 'file'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Created ${name}`);
                    refreshTree();
                    if (data.encoded) loadFile(data.encoded, path);
                } else {
                    showToast(data.error || 'Failed to create file', true);
                }
            } catch (e) {
                showToast('Failed to create file', true);
            }
        }
        
        async function createNewFolder(parentPath) {
            const name = await showInputDialog('New Folder Name:');
            if (!name) return;
            
            const path = parentPath ? `${parentPath}/${name}` : name;
            try {
                const res = await fetch('/playground/editor/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: document.querySelector('input[name="csrf_token"]').value,
                        path: path,
                        type: 'folder'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Created folder ${name}`);
                    refreshTree();
                } else {
                    showToast(data.error || 'Failed to create folder', true);
                }
            } catch (e) {
                showToast('Failed to create folder', true);
            }
        }
        
        async function renameItem(path, isFolder) {
            const oldName = path.split('/').pop();
            const newName = await showInputDialog('Rename to:', oldName);
            if (!newName || newName === oldName) return;
            
            const parentPath = path.substring(0, path.lastIndexOf('/'));
            const newPath = parentPath ? `${parentPath}/${newName}` : newName;
            
            try {
                const res = await fetch('/playground/editor/rename', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: document.querySelector('input[name="csrf_token"]').value,
                        oldPath: path,
                        newPath: newPath
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Renamed to ${newName}`);
                    refreshTree();
                } else {
                    showToast(data.error || 'Failed to rename', true);
                }
            } catch (e) {
                showToast('Failed to rename', true);
            }
        }
        
        async function deleteItem(path, isFolder) {
            const name = path.split('/').pop();
            const confirmed = await showDeleteConfirmDialog({
                title: isFolder ? 'Delete Folder' : 'Delete File',
                message: `Delete "${name}"? This cannot be undone.`,
                confirmText: 'Delete',
                cancelText: 'Cancel'
            });
            if (!confirmed) return;
            
            try {
                const res = await fetch('/playground/editor/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: document.querySelector('input[name="csrf_token"]').value,
                        path: path
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Deleted ${name}`);
                    refreshTree();
                    // Clear current file if it was deleted
                    if (window.currentFile === path || window.currentFile?.startsWith(path + '/')) {
                        window.currentFile = null;
                        window.currentEncoded = null;
                        if (monacoEditor) monacoEditor.setValue('');
                        filePath.textContent = 'No file selected';
                    }
                } else {
                    showToast(data.error || 'Failed to delete', true);
                }
            } catch (e) {
                showToast('Failed to delete', true);
            }
        }
        
        async function pasteItem(destPath) {
            if (!clipboard.path) return;
            
            try {
                const res = await fetch('/playground/editor/paste', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: document.querySelector('input[name="csrf_token"]').value,
                        source: clipboard.path,
                        destination: destPath,
                        action: clipboard.action
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(clipboard.action === 'cut' ? 'Moved successfully' : 'Copied successfully');
                    if (clipboard.action === 'cut') clipboard = { path: null, action: null };
                    refreshTree();
                } else {
                    showToast(data.error || 'Failed to paste', true);
                }
            } catch (e) {
                showToast('Failed to paste', true);
            }
        }
        
        async function refreshTree() {
            try {
                const res = await fetch('/playground/editor/tree?ajax=1', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.tree) {
                    window.playgroundRepoTree = data.tree;
                    treeContent.innerHTML = '';
                    renderTree(data.tree, treeContent);
                }
            } catch (e) {
                console.error('Failed to refresh tree:', e);
            }
        }
        
        // Context menu action handlers
        contextMenu.addEventListener('click', async (e) => {
            const item = e.target.closest('.context-menu-item');
            if (!item || item.classList.contains('disabled')) return;
            
            const action = item.dataset.action;
            const isFolder = contextTarget?.type === 'folder' || contextTarget?.type === 'root';
            const path = contextTarget?.path || '';
            
            hideContextMenu();
            
            switch (action) {
                case 'new-file':
                    await createNewFile(isFolder ? path : path.substring(0, path.lastIndexOf('/')));
                    break;
                case 'new-folder':
                    await createNewFolder(isFolder ? path : path.substring(0, path.lastIndexOf('/')));
                    break;
                case 'cut':
                    clipboard = { path: path, action: 'cut' };
                    showToast('Ready to move');
                    break;
                case 'copy':
                    clipboard = { path: path, action: 'copy' };
                    showToast('Copied to clipboard');
                    break;
                case 'paste':
                    await pasteItem(isFolder ? path : path.substring(0, path.lastIndexOf('/')));
                    break;
                case 'rename':
                    if (path) await renameItem(path, isFolder);
                    break;
                case 'delete':
                    if (path) await deleteItem(path, isFolder);
                    break;
                case 'copy-path':
                    if (path) {
                        navigator.clipboard.writeText(path);
                        showToast('Path copied');
                    }
                    break;
                case 'create-checkpoint':
                    await createCheckpoint();
                    break;
                case 'restore-checkpoint':
                    showCheckpointDialog();
                    break;
                case 'refresh':
                    await refreshTree();
                    showToast('Refreshed');
                    break;
            }
        });
        
        // ========== Checkpoint System ==========
        const CHECKPOINT_KEY = 'playground-editor-checkpoints';
        
        function getCheckpoints() {
            try {
                const stored = localStorage.getItem(CHECKPOINT_KEY);
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        }
        
        function saveCheckpoints(checkpoints) {
            try {
                localStorage.setItem(CHECKPOINT_KEY, JSON.stringify(checkpoints));
            } catch (e) {
                console.error('Failed to save checkpoints:', e);
            }
        }
        
        async function createCheckpoint() {
            const name = await showInputDialog('Checkpoint Name', `Checkpoint ${new Date().toLocaleString()}`);
            if (!name) return;
            
            // Collect current state
            const checkpoint = {
                id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
                name: name,
                timestamp: Date.now(),
                currentFile: window.currentFile,
                currentEncoded: window.currentEncoded,
                fileContent: monacoReady && monacoEditor ? monacoEditor.getValue() : textarea.value,
                isDirty: isDirty
            };
            
            const checkpoints = getCheckpoints();
            checkpoints.unshift(checkpoint); // Add to beginning
            
            // Keep only last 20 checkpoints to avoid localStorage limits
            if (checkpoints.length > 20) {
                checkpoints.length = 20;
            }
            
            saveCheckpoints(checkpoints);
            showToast('Checkpoint created: ' + name);
        }
        
        function showCheckpointDialog() {
            const checkpoints = getCheckpoints();
            
            const overlay = document.createElement('div');
            overlay.className = 'checkpoint-dialog-overlay';
            overlay.innerHTML = `
                <div class="checkpoint-dialog">
                    <h3>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/></svg>
                        Restore Checkpoint
                    </h3>
                    <div class="checkpoint-list">
                        ${checkpoints.length === 0 ? 
                            '<div class="checkpoint-empty">No checkpoints yet.<br>Create one from the context menu.</div>' :
                            checkpoints.map(cp => `
                                <div class="checkpoint-item" data-id="${cp.id}">
                                    <div class="checkpoint-icon">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                    </div>
                                    <div class="checkpoint-info">
                                        <div class="checkpoint-name">${escapeHtml(cp.name)}</div>
                                        <div class="checkpoint-time">${new Date(cp.timestamp).toLocaleString()}${cp.currentFile ? ' • ' + cp.currentFile.split('/').pop() : ''}</div>
                                    </div>
                                    <button class="checkpoint-delete" data-id="${cp.id}" title="Delete checkpoint">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            `).join('')
                        }
                    </div>
                    <div class="checkpoint-dialog-buttons">
                        <button class="cancel">Cancel</button>
                        <button class="primary restore" ${checkpoints.length === 0 ? 'disabled' : ''}>Restore Selected</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            
            let selectedId = null;
            const list = overlay.querySelector('.checkpoint-list');
            const restoreBtn = overlay.querySelector('.restore');
            const cancelBtn = overlay.querySelector('.cancel');
            
            // Select checkpoint on click
            list.addEventListener('click', (e) => {
                const item = e.target.closest('.checkpoint-item');
                const deleteBtn = e.target.closest('.checkpoint-delete');
                
                if (deleteBtn) {
                    e.stopPropagation();
                    const id = deleteBtn.dataset.id;
                    deleteCheckpoint(id);
                    const itemEl = overlay.querySelector(`.checkpoint-item[data-id="${id}"]`);
                    if (itemEl) itemEl.remove();
                    if (selectedId === id) {
                        selectedId = null;
                        restoreBtn.disabled = true;
                    }
                    if (list.querySelectorAll('.checkpoint-item').length === 0) {
                        list.innerHTML = '<div class="checkpoint-empty">No checkpoints yet.<br>Create one from the context menu.</div>';
                    }
                    return;
                }
                
                if (item) {
                    list.querySelectorAll('.checkpoint-item').forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    selectedId = item.dataset.id;
                    restoreBtn.disabled = false;
                }
            });
            
            // Double-click to restore immediately
            list.addEventListener('dblclick', (e) => {
                const item = e.target.closest('.checkpoint-item');
                if (item && !e.target.closest('.checkpoint-delete')) {
                    selectedId = item.dataset.id;
                    restoreCheckpoint(selectedId);
                    overlay.remove();
                }
            });
            
            cancelBtn.addEventListener('click', () => overlay.remove());
            overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
            
            restoreBtn.addEventListener('click', () => {
                if (selectedId) {
                    restoreCheckpoint(selectedId);
                    overlay.remove();
                }
            });
            
            document.addEventListener('keydown', function handler(e) {
                if (e.key === 'Escape') {
                    overlay.remove();
                    document.removeEventListener('keydown', handler);
                }
            });
        }
        
        function deleteCheckpoint(id) {
            const checkpoints = getCheckpoints().filter(cp => cp.id !== id);
            saveCheckpoints(checkpoints);
            showToast('Checkpoint deleted');
        }
        
        function restoreCheckpoint(id) {
            const checkpoints = getCheckpoints();
            const checkpoint = checkpoints.find(cp => cp.id === id);
            if (!checkpoint) {
                showToast('Checkpoint not found', true);
                return;
            }
            
            // Restore file state
            if (checkpoint.currentFile && checkpoint.currentEncoded) {
                window.currentFile = checkpoint.currentFile;
                window.currentEncoded = checkpoint.currentEncoded;
                filePath.textContent = checkpoint.currentFile || 'No file selected';
                
                const lang = getLanguage(checkpoint.currentFile);
                window.currentLanguage = lang;
                langDisplay.textContent = lang.toUpperCase();
                
                if (monacoReady && monacoEditor) {
                    monaco.editor.setModelLanguage(monacoEditor.getModel(), lang);
                }
            }
            
            // Restore content
            if (monacoReady && monacoEditor) {
                monacoEditor.setValue(checkpoint.fileContent || '');
            } else {
                textarea.value = checkpoint.fileContent || '';
            }
            
            isDirty = checkpoint.isDirty || true; // Mark as dirty since we restored
            editorStatus.textContent = 'Restored';
            setTimeout(() => { editorStatus.textContent = isDirty ? 'Modified' : 'Ready'; }, 2000);
            
            showToast('Restored: ' + checkpoint.name);
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        
        // ========== Expose editor APIs for chat panel ==========
        window.playgroundEditor = {
            getEditor: () => monacoEditor,
            isReady: () => monacoReady,
            getValue: () => monacoReady && monacoEditor ? monacoEditor.getValue() : textarea.value,
            setValue: (value) => {
                if (monacoReady && monacoEditor) {
                    monacoEditor.setValue(value);
                } else {
                    textarea.value = value;
                }
            },
            setDirty: (dirty) => {
                isDirty = dirty;
                editorStatus.textContent = dirty ? 'Modified' : 'Ready';
                if (dirty) {
                    scheduleAutoSave();
                } else {
                    clearAutoSaveTimer();
                    lastSavedContent = getEditorContent();
                }
            },
            getCurrentFile: () => window.currentFile,
            showToast: showToast,
            loadFile: loadFile,
            sendStream: (data) => {
                // Send a message to the Ratchet server
                if (wsConnected && ws) {
                    ws.send(JSON.stringify(data));
                }
            }
        };
    })();
    </script>
    
    <!-- Editor Chat JS -->
    <script src="/assets/js/editor-chat.js"></script>
    
    <?php include __DIR__ . '/parts/scripts.php'; ?>
</body>
</html>
