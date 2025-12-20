<?php
/**
 * My Files - Standalone Editor
 * 
 * VISITOR SANDBOX LIFECYCLE (non-logged-in users):
 * - Sandbox created ONLY when "My Files" is clicked
 * - 1 hour lifetime - sandbox + session expire together
 * - On expiration: container, DB, Redis, directory, session ALL cleaned up
 * - Next "My Files" click = fresh sandbox
 * 
 * LOGGED-IN USERS:
 * - Sandboxes persist and are tied to their account
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Determine if user is logged in
$isLoggedIn = !empty($_SESSION['user_id']);
$isVisitor = !$isLoggedIn;

// VISITOR SESSION EXPIRATION CHECK
// Visitors have 1-hour sessions. If expired, destroy everything silently.
if ($isVisitor) {
    $sessionCreatedAt = $_SESSION['session_created_at'] ?? null;
    $sessionLifetime = 3600; // 1 hour
    
    if ($sessionCreatedAt && (time() - $sessionCreatedAt) >= $sessionLifetime) {
        // Session expired - clean up sandbox completely
        $expiredSandboxId = $_SESSION['sandbox_id'] ?? null;
        if ($expiredSandboxId) {
            try {
                $db = \Ginto\Core\Database::getInstance();
                \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($expiredSandboxId, $db);
                error_log("[editor.php] Visitor session expired - cleaned up sandbox: {$expiredSandboxId}");
            } catch (\Throwable $e) {
                error_log("[editor.php] Error cleaning expired sandbox: " . $e->getMessage());
            }
        }
        
        // Destroy the entire session
        session_unset();
        session_destroy();
        session_start();
        
        // Set fresh session timestamp
        $_SESSION['session_created_at'] = time();
    }
    
    // Initialize session timestamp if not set
    if (empty($_SESSION['session_created_at'])) {
        $_SESSION['session_created_at'] = time();
    }
}

// SECURITY: Only admins can view specific sandboxes via URL parameter
// Visitors must NEVER be able to access another sandbox
$isAdminForSandboxAccess = !empty($_SESSION['is_admin']) ||
    (!empty($_SESSION['role_id']) && in_array((int)$_SESSION['role_id'], [1,2], true)) ||
    (!empty($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']);

if ($isAdminForSandboxAccess && !empty($_GET['sandbox'])) {
    $sid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['sandbox']);
    if ($sid !== '') {
        $_SESSION['sandbox_id'] = $sid;
    }
}

$editorRoot = '';
$sandboxId = null;
$isAdminSession = false;
$file = '';
$content = '';
$encoded = '';
$needsSetup = false;
$autoCreatedSandbox = false;
$sandboxExpiresAt = null; // For visitor expiration warning

try {
    $db = null;
    try { $db = \Ginto\Core\Database::getInstance(); } catch (\Throwable $_) {}
    
    // Check if user is admin
    $isAdminSession = !empty($_SESSION['is_admin']) ||
        (!empty($_SESSION['role_id']) && in_array((int)$_SESSION['role_id'], [1,2], true)) ||
        (!empty($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) ||
        (!empty($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') ||
        (!empty($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin');
    
    // Check for explicit sandbox mode
    $forceSandbox = !empty($_SESSION['playground_use_sandbox']) || !empty($_SESSION['playground_admin_sandbox']);
    
    if ($isAdminSession && !$forceSandbox) {
        // Admin without forced sandbox - use project root
        $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $sandboxId = null;
    } else {
        // =====================================================================
        // SANDBOX MANAGEMENT
        // =====================================================================
        
        $existingSandboxId = $_SESSION['sandbox_id'] ?? null;
        $sandboxValid = false;
        
        // Validate existing sandbox if present
        if (!empty($existingSandboxId)) {
            $sandboxValid = \Ginto\Helpers\ClientSandboxHelper::validateSandboxExists($existingSandboxId, $db);
            
            if (!$sandboxValid) {
                // STALE/BROKEN SANDBOX - Clean it up completely
                error_log("[editor.php] Stale sandbox detected: {$existingSandboxId} - cleaning up");
                \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($existingSandboxId, $db);
                unset($_SESSION['sandbox_id']);
                $existingSandboxId = null;
            }
        }
        
        // Create sandbox if none exists (this is when "My Files" is clicked)
        if (empty($existingSandboxId) || !$sandboxValid) {
            // Generate a new sandbox ID
            $newSandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($db, $_SESSION);
            
            if (!empty($newSandboxId)) {
                // Create the LXD container
                $createResult = \Ginto\Helpers\LxdSandboxManager::createSandbox($newSandboxId);
                
                if ($createResult['success']) {
                    \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($newSandboxId);
                    $_SESSION['sandbox_id'] = $newSandboxId;
                    $sandboxId = $newSandboxId;
                    $editorRoot = 'sandbox';
                    $autoCreatedSandbox = true;
                    
                    // For visitors, record when sandbox was created for expiration tracking
                    if ($isVisitor) {
                        $_SESSION['sandbox_created_at'] = time();
                    }
                    
                    error_log("[editor.php] Created fresh sandbox: {$newSandboxId}" . ($isVisitor ? " (visitor - 1hr lifetime)" : ""));
                } else {
                    $needsSetup = true;
                    $editorRoot = '';
                    error_log("[editor.php] Failed to create sandbox: " . json_encode($createResult));
                }
            } else {
                $needsSetup = true;
                $editorRoot = '';
            }
        } else {
            // Valid sandbox exists - ensure it's running
            $sandboxId = $existingSandboxId;
            \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId);
            $editorRoot = 'sandbox';
        }
        
        // Calculate expiration time for visitors (for warning dialog)
        if ($isVisitor && !empty($_SESSION['session_created_at'])) {
            $sandboxExpiresAt = $_SESSION['session_created_at'] + 3600; // 1 hour from session start
        }
    }
} catch (\Throwable $e) {
    error_log("[editor.php] Error during sandbox setup: " . $e->getMessage());
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $isAdminSession = true;
    $sandboxId = null;
}

// Handle file parameter
if (!empty($_GET['file'])) {
    $encoded = $_GET['file'];
    $decoded = base64_decode(rawurldecode($encoded));
    if ($decoded) {
        $safePath = str_replace(['..', '\\'], ['', '/'], $decoded);
        $fullPath = rtrim($editorRoot, '/') . '/' . $safePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            $file = $safePath;
            $content = file_get_contents($fullPath);
        }
    }
}

// Language detection
$ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : 'txt';
$langMap = [
    'php' => 'php', 'js' => 'javascript', 'ts' => 'typescript', 'json' => 'json',
    'html' => 'html', 'htm' => 'html', 'css' => 'css', 'scss' => 'scss',
    'md' => 'markdown', 'sql' => 'sql', 'xml' => 'xml', 'yaml' => 'yaml', 'yml' => 'yaml',
    'sh' => 'shell', 'bash' => 'shell', 'py' => 'python', 'rb' => 'ruby'
];
$language = $langMap[$ext] ?? 'plaintext';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title>My Files</title>
    
    <!-- Favicons -->
    <link rel="icon" type="image/png" href="/assets/images/ginto.png">
    <link rel="shortcut icon" href="/assets/images/ginto.png">
    <link rel="apple-touch-icon" href="/assets/images/ginto.png">
    <meta name="theme-color" content="#1f2937">
    
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link href="/assets/css/editor-chat.css" rel="stylesheet">
    
    <!-- Theme detection - check ginto-theme (parent chat), editor-theme, or playground-theme -->
    <script>
    (function() {
        const stored = localStorage.getItem('ginto-theme') || localStorage.getItem('editor-theme') || localStorage.getItem('playground-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const shouldBeDark = stored === 'dark' || (!stored && prefersDark);
        if (shouldBeDark) document.documentElement.classList.add('dark');
        window.__initialTheme = shouldBeDark ? 'dark' : 'light';
    })();
    </script>
    
    <script>
        window.CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
        window.currentFile = <?= json_encode($file) ?>;
        window.currentEncoded = <?= json_encode($encoded) ?>;
        window.currentLanguage = <?= json_encode($language) ?>;
        window.editorRootFromServer = <?= json_encode(realpath($editorRoot) ?: rtrim($editorRoot, '/')) ?>;
        window.editorConfig = {
            isAdmin: <?= json_encode($isAdminSession) ?>,
            sandboxId: <?= json_encode($sandboxId ?: null) ?>,
            sandboxRoot: <?= json_encode($editorRoot) ?>,
            csrfToken: <?= json_encode($csrf_token) ?>,
            needsSetup: <?= json_encode($needsSetup) ?>,
            autoCreatedSandbox: <?= json_encode($autoCreatedSandbox ?? false) ?>,
            // Visitor expiration tracking
            isVisitor: <?= json_encode($isVisitor) ?>,
            sandboxExpiresAt: <?= json_encode($sandboxExpiresAt) ?>,
            sessionCreatedAt: <?= json_encode($_SESSION['session_created_at'] ?? null) ?>
        };
        
        // VISITOR SANDBOX EXPIRATION WARNING
        (function() {
            if (!window.editorConfig.isVisitor || !window.editorConfig.sandboxExpiresAt) return;
            
            const expiresAt = window.editorConfig.sandboxExpiresAt * 1000; // Convert to ms
            const now = Date.now();
            const timeLeft = expiresAt - now;
            
            if (timeLeft <= 0) {
                // Already expired - will be cleaned up on next page load
                return;
            }
            
            // Show warning 5 minutes before expiration
            const warningTime = timeLeft - (5 * 60 * 1000);
            if (warningTime > 0) {
                setTimeout(showExpirationWarning, warningTime);
            } else if (timeLeft > 0) {
                // Less than 5 minutes left - show warning immediately
                setTimeout(showExpirationWarning, 100);
            }
            
            // Auto-cleanup when expired
            setTimeout(function() {
                // Silently redirect to /chat - session will be cleaned on next editor load
                window.location.href = '/chat';
            }, timeLeft);
            
            function showExpirationWarning() {
                const minutes = Math.ceil((expiresAt - Date.now()) / 60000);
                const modal = document.createElement('div');
                modal.id = 'visitor-expiration-modal';
                modal.innerHTML = `
                    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
                        <div style="background:#1e1e1e;border-radius:12px;padding:24px;max-width:400px;text-align:center;color:#fff;border:1px solid #7c3aed;">
                            <div style="font-size:48px;margin-bottom:16px;">⏰</div>
                            <h3 style="font-size:18px;font-weight:600;margin-bottom:12px;">Sandbox Expiring Soon</h3>
                            <p style="color:#a0a0a0;margin-bottom:20px;">
                                Your demo sandbox will expire in <strong style="color:#f59e0b;">${minutes} minute${minutes !== 1 ? 's' : ''}</strong>.<br>
                                Log in to save your work and keep your sandbox permanently.
                            </p>
                            <div style="display:flex;gap:12px;justify-content:center;">
                                <a href="/login?redirect=/editor" style="background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:500;">Log In</a>
                                <a href="/register?redirect=/editor" style="background:#059669;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:500;">Register</a>
                                <button onclick="this.closest('#visitor-expiration-modal').remove()" style="background:#374151;color:#fff;padding:10px 20px;border-radius:8px;border:none;cursor:pointer;">Continue</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
        })();
    </script>

<style>
/* VS Code inspired editor styles */
:root {
    --editor-bg-primary: #ffffff;
    --editor-bg-secondary: #f3f3f3;
    --editor-border-color: #e5e5e5;
    --editor-text-primary: #1e1e1e;
    --editor-text-secondary: #6e6e6e;
    --editor-accent: #7c3aed;
    --editor-accent-hover: #6d28d9;
    --file-tree-width: 260px;
    --right-pane-width: 380px;
}

.dark {
    --editor-bg-primary: #1e1e1e;
    --editor-bg-secondary: #252526;
    --editor-border-color: #3c3c3c;
    --editor-text-primary: #cccccc;
    --editor-text-secondary: #858585;
}

/* Modern scrollbar styles */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(128, 128, 128, 0.4);
    border-radius: 5px;
    border: 2px solid transparent;
    background-clip: padding-box;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(128, 128, 128, 0.6);
    border: 2px solid transparent;
    background-clip: padding-box;
}

::-webkit-scrollbar-corner {
    background: transparent;
}

/* Firefox scrollbar */
* {
    scrollbar-width: thin;
    scrollbar-color: rgba(128, 128, 128, 0.4) transparent;
}

*, *::before, *::after { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
}

/* Main Shell */
.editor-shell {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: var(--editor-bg-primary);
}

/* Header */
.editor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    background: var(--editor-bg-secondary);
    border-bottom: 1px solid var(--editor-border-color);
    flex-shrink: 0;
    min-height: 52px;
    gap: 16px;
}

.editor-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}

/* File select dropdown */
.file-select {
    background: var(--editor-bg-primary);
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    padding: 6px 12px;
    padding-right: 28px;
    font-size: 13px;
    color: var(--editor-text-primary);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    min-width: 160px;
    max-width: 280px;
}

.file-select:hover {
    border-color: var(--editor-accent);
}

.file-select:focus {
    outline: none;
    border-color: var(--editor-accent);
    box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.2);
}

.file-path-display {
    font-size: 12px;
    color: var(--editor-text-secondary);
    padding: 4px 10px;
    background: var(--editor-bg-primary);
    border-radius: 4px;
    border: 1px solid var(--editor-border-color);
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.editor-header-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

/* Button styles */
.editor-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
    border: 1px solid var(--editor-border-color);
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
}

.editor-btn:hover {
    background: var(--editor-bg-secondary);
}

.editor-btn-primary {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    border-color: transparent;
    color: #fff;
}

.editor-btn-primary:hover {
    background: linear-gradient(135deg, #6d28d9, #5b21b6);
}

.editor-btn svg {
    width: 14px;
    height: 14px;
}

.editor-btn-secondary {
    background: var(--editor-bg-primary);
    border: 1px solid var(--editor-border-color);
}

.editor-btn-secondary:hover {
    background: var(--editor-bg-secondary);
    border-color: var(--editor-text-secondary);
}

/* File select dropdown */
.file-select {
    padding: 4px 8px;
    font-size: 12px;
    background: var(--editor-bg-primary);
    border: 1px solid var(--editor-border-color);
    border-radius: 4px;
    color: var(--editor-text-primary);
    cursor: pointer;
    min-width: 150px;
}

.file-select:focus {
    outline: none;
    border-color: var(--editor-accent);
}

/* Sandbox badge */
.sandbox-text-badge {
    font-size: 11px;
    padding: 4px 10px;
    background: rgba(234, 179, 8, 0.15);
    color: #ca8a04;
    border-radius: 4px;
}

.dark .sandbox-text-badge {
    background: rgba(234, 179, 8, 0.2);
    color: #fbbf24;
}

.sandbox-text-badge a {
    color: inherit;
    text-decoration: underline;
}

/* Root path display */
.root-display {
    font-size: 12px;
    color: var(--editor-text-secondary);
    margin-right: 8px;
}

/* Debug panel */
.debug-panel {
    display: none;
    position: absolute;
    right: 12px;
    top: 56px;
    width: 360px;
    max-height: 60vh;
    overflow: auto;
    background: var(--editor-bg-secondary);
    color: var(--editor-text-primary);
    border: 1px solid var(--editor-border-color);
    padding: 12px;
    border-radius: 6px;
    z-index: 1200;
    font-size: 12px;
    line-height: 1.4;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.3);
}

.debug-panel.visible {
    display: block;
}

/* Main Area */
.editor-main {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* File Tree */
.file-tree {
    width: var(--file-tree-width);
    min-width: 180px;
    max-width: 50%;
    flex-shrink: 0;
    background: var(--editor-bg-secondary);
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
}

.file-tree-header {
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--editor-text-secondary);
    border-bottom: 1px solid var(--editor-border-color);
    position: sticky;
    top: 0;
    background: var(--editor-bg-secondary);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.file-tree-header button {
    background: none;
    border: none;
    color: var(--editor-text-secondary);
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-tree-header button:hover {
    background: rgba(128, 128, 128, 0.15);
    color: var(--editor-text-primary);
}

#tree-content {
    flex: 1;
    padding: 4px 0;
}

/* Tree items - file and folder styling */
.file-item, .folder-item {
    display: flex;
    align-items: stretch;
    padding: 0 8px;
    cursor: pointer;
    font-size: 14px;
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

.file-item:hover, .folder-item:hover {
    background: rgba(128, 128, 128, 0.1);
}

.file-item.active {
    background: rgba(124, 58, 237, 0.15);
    color: var(--editor-accent);
}

.file-item svg, .folder-item svg {
    width: 14px;
    height: 14px;
    margin-right: 4px;
    flex-shrink: 0;
}

.folder-toggle {
    width: 16px;
    height: 16px;
    margin-right: 2px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s;
}

.folder-toggle svg {
    width: 12px !important;
    height: 12px !important;
    margin-right: 0 !important;
}

.folder-item.collapsed .folder-toggle {
    transform: rotate(-90deg);
}

/* Drag and drop styles */
.file-item[draggable="true"],
.folder-item[draggable="true"] {
    cursor: grab;
}

.file-item.dragging,
.folder-item.dragging {
    opacity: 0.5;
    cursor: grabbing;
}

.file-item.drag-over,
.folder-item.drag-over,
.folder-children.drag-over {
    background: rgba(124, 58, 237, 0.2);
    outline: 2px dashed var(--editor-accent);
    outline-offset: -2px;
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

/* Resizer */
.editor-resizer {
    width: 4px;
    background: transparent;
    cursor: col-resize;
    flex-shrink: 0;
    position: relative;
    transition: background 0.15s;
}

.editor-resizer::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 1px;
    background: var(--editor-border-color);
    transform: translateX(-50%);
}

.editor-resizer:hover::before,
.editor-resizer.dragging::before {
    width: 2px;
    background: var(--editor-accent);
}

/* Editor Workspace */
.editor-workspace {
    flex: 1;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background: var(--editor-bg-primary);
    position: relative;
}

#monaco-editor {
    flex: 1;
    min-height: 0;
}

/* CodeMirror Mobile Editor Styles */
#codemirror-editor {
    flex: 1;
    min-height: 0;
    height: 100%;
}

.CodeMirror {
    height: 100% !important;
    font-family: 'Fira Code', 'JetBrains Mono', 'Monaco', 'Consolas', monospace;
    font-size: 14px;
    line-height: 1.5;
}

/* Light mode - ensure proper styling when not using theme */
.CodeMirror:not(.cm-s-dracula) {
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
}

.CodeMirror:not(.cm-s-dracula) .CodeMirror-gutters {
    background: var(--editor-bg-secondary);
    border-right: 1px solid var(--editor-border-color);
}

/* Dark theme - ensure CodeMirror container also gets dark bg */
.dark .CodeMirror {
    background: #282a36 !important; /* Dracula bg */
    color: #f8f8f2;
}

.dark .CodeMirror-gutters {
    background: #282a36 !important;
    border-right: 1px solid #44475a !important;
}

.dark .CodeMirror-linenumber {
    color: #6272a4 !important;
}

.dark .CodeMirror-cursor {
    border-left-color: #f8f8f2 !important;
}

/* Mobile-specific styles */
.mobile-editor .editor-workspace {
    min-width: 100%;
}

.mobile-editor .file-tree-pane {
    display: none;
}

.mobile-editor .editor-right-pane {
    display: none;
}

.mobile-editor .editor-resizer {
    display: none;
}

.mobile-editor .editor-main-content {
    flex-direction: column;
}

/* ============================================
   MOBILE RESPONSIVE STYLES
   ============================================ */
@media (max-width: 768px) {
    /* Header restructuring for mobile */
    .editor-header {
        flex-wrap: wrap;
        padding: 8px;
        gap: 8px;
        min-height: auto;
    }
    
    .editor-header-left {
        width: 100%;
        order: 1;
        justify-content: space-between;
        gap: 8px;
    }
    
    .editor-header-right {
        width: 100%;
        order: 2;
        justify-content: flex-end;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    /* File select takes available space */
    .file-select {
        flex: 1;
        min-width: 100px;
        max-width: none;
        font-size: 12px;
        padding: 8px 10px;
    }
    
    /* Sandbox badge - more compact */
    .sandbox-text-badge {
        font-size: 10px !important;
        padding: 4px 8px !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
    }
    
    /* Hide sandbox text, show abbreviated */
    .sandbox-text-badge strong {
        display: inline-block;
        max-width: 60px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: bottom;
    }
    
    /* Destroy button - icon only on mobile */
    #destroy-sandbox-btn {
        padding: 6px !important;
        font-size: 0 !important;
    }
    
    #destroy-sandbox-btn svg {
        width: 16px !important;
        height: 16px !important;
    }
    
    /* Hide root path display on mobile */
    .root-display {
        display: none !important;
    }
    
    /* Buttons - icon only, compact */
    .editor-btn {
        padding: 8px;
        font-size: 0;
        min-width: 36px;
        height: 36px;
    }
    
    .editor-btn svg,
    .editor-btn i {
        width: 18px;
        height: 18px;
        font-size: 14px;
    }
    
    /* Save button keeps text */
    #save-btn {
        font-size: 12px;
        padding: 8px 12px;
    }
    
    /* Hide some non-essential buttons on mobile */
    #toggle-editor-btn,
    #views-debug-btn,
    #admin-sandbox-action {
        display: none !important;
    }
    
    /* File tree - slide-out panel on mobile */
    .file-tree {
        position: fixed !important;
        left: -280px;
        top: 0;
        bottom: 0;
        width: 280px !important;
        z-index: 1000;
        transition: left 0.3s ease;
        box-shadow: 4px 0 20px rgba(0,0,0,0.3);
    }
    
    .file-tree.mobile-open {
        left: 0;
    }
    
    /* Mobile file tree overlay */
    .mobile-tree-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
    
    .mobile-tree-overlay.active {
        display: block;
    }
    
    /* Editor resizers hidden on mobile */
    .editor-resizer {
        display: none !important;
    }
    
    /* Editor workspace full width */
    .editor-workspace {
        min-width: 100% !important;
        flex: 1;
    }
    
    /* Right pane as slide-up panel on mobile */
    .editor-right-pane {
        display: none;
        position: fixed !important;
        left: 0;
        right: 0;
        bottom: 0;
        top: auto !important;
        height: 70vh;
        max-height: 70vh;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 100% !important;
        z-index: 1001;
        border-radius: 16px 16px 0 0;
        border-left: none !important;
        border-top: 1px solid var(--editor-border-color);
        box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        transform: translateY(100%);
        transition: transform 0.3s ease;
    }
    
    .editor-right-pane.mobile-chat-open {
        display: flex !important;
        transform: translateY(0);
    }
    
    /* Mobile chat overlay */
    .mobile-chat-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
    }
    
    .mobile-chat-overlay.active {
        display: block;
    }
    
    /* Floating chat button */
    .mobile-chat-fab {
        display: flex !important;
        position: fixed;
        bottom: 40px;
        right: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.4);
        cursor: pointer;
        z-index: 999;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .mobile-chat-fab:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(124, 58, 237, 0.5);
    }
    
    .mobile-chat-fab:active {
        transform: scale(0.95);
    }
    
    .mobile-chat-fab svg {
        width: 28px;
        height: 28px;
    }
    
    /* Hide FAB when chat is open */
    .mobile-chat-fab.hidden {
        display: none !important;
    }
    
    /* Adjust assistant pane for mobile */
    .assistant-pane {
        height: 100%;
    }
    
    .assistant-header {
        padding: 12px !important;
    }
    
    .assistant-tabs {
        max-width: 60%;
        overflow-x: auto;
    }
    
    .assistant-controls {
        gap: 4px !important;
    }
    
    .as-btn {
        padding: 6px !important;
        font-size: 14px !important;
    }
    
    /* Editor main area */
    .editor-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    /* CodeMirror adjustments */
    .CodeMirror {
        font-size: 14px !important;
    }
    
    .CodeMirror-linenumbers {
        min-width: 35px;
    }
    
    /* Status bar mobile */
    .editor-status-bar {
        flex-wrap: wrap;
        padding: 4px 8px;
        font-size: 10px;
        gap: 8px;
    }
    
    .status-section {
        gap: 6px;
    }
    
    /* Mobile menu button */
    .mobile-menu-btn {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: var(--editor-bg-primary);
        border: 1px solid var(--editor-border-color);
        border-radius: 6px;
        cursor: pointer;
        order: -1;
    }
    
    .mobile-menu-btn svg {
        width: 20px;
        height: 20px;
        stroke: var(--editor-text-primary);
    }
}

/* Hide mobile elements on desktop */
.mobile-menu-btn {
    display: none;
}

.mobile-chat-fab {
    display: none;
}

.mobile-chat-overlay {
    display: none;
}

/* Larger phones in landscape */
@media (max-width: 768px) and (orientation: landscape) {
    .editor-header {
        padding: 4px 8px;
    }
    
    .editor-btn {
        min-width: 32px;
        height: 32px;
    }
}

/* Very small screens */
@media (max-width: 400px) {
    .sandbox-text-badge {
        display: none !important;
    }
    
    .file-select {
        font-size: 11px;
    }
    
    .editor-btn {
        min-width: 32px;
        height: 32px;
        padding: 6px;
    }
}

/* Right Pane */
.editor-right-pane {
    width: var(--right-pane-width);
    min-width: 280px;
    max-width: 50%;
    flex-shrink: 0;
    background: var(--editor-bg-secondary);
    border-left: 1px solid var(--editor-border-color);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Assistant Pane */
.assistant-pane {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.assistant-header {
    padding: 8px 12px;
    border-bottom: 1px solid var(--editor-border-color);
    background: var(--editor-bg-secondary);
}

.assistant-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.assistant-tabs {
    display: flex;
    gap: 4px;
}

.assistant-tab {
    padding: 6px 12px;
    font-size: 12px;
    background: transparent;
    border: none;
    color: var(--editor-text-secondary);
    cursor: pointer;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.assistant-tab.active {
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
}

.assistant-tab .tab-close {
    font-size: 14px;
    opacity: 0.5;
    cursor: pointer;
}

.assistant-tab .tab-close:hover {
    opacity: 1;
}

.assistant-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.as-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--editor-text-secondary);
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
}

.as-btn:hover {
    background: rgba(128, 128, 128, 0.15);
    color: var(--editor-text-primary);
}

.as-divider {
    width: 1px;
    height: 20px;
    background: var(--editor-border-color);
    margin: 0 4px;
}

.assistant-audio-status {
    display: flex;
    gap: 8px;
    padding: 4px 0;
    font-size: 11px;
    color: var(--editor-text-secondary);
    align-items: center;
}

.assistant-divider {
    height: 1px;
    background: var(--editor-border-color);
    margin-top: 8px;
}

/* Assistant Body */
.assistant-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: var(--editor-bg-secondary);
}

.assistant-empty {
    color: var(--editor-text-secondary);
    font-size: 13px;
    text-align: center;
    padding: 40px 20px;
}

/* Assistant Footer / Composer */
.assistant-footer {
    border-top: 1px solid var(--editor-border-color);
    background: var(--editor-bg-secondary);
}

.assistant-composer {
    padding: 12px;
}

.assistant-composer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.attached-overlay {
    display: flex;
    align-items: center;
    gap: 8px;
}

.attach-icon {
    width: 16px;
    height: 16px;
    color: var(--editor-text-secondary);
}

.attached-file {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: 1px solid var(--editor-border-color);
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
}

.attached-file .file-tag {
    color: #c9a917;
    font-weight: 600;
    font-size: 11px;
}

.attached-file .file-name {
    color: var(--editor-text-secondary);
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.as-attach {
    width: 24px;
    height: 24px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--editor-border-color);
    border-radius: 4px;
    font-size: 14px;
    color: var(--editor-text-secondary);
    cursor: pointer;
}

.as-attach:hover {
    border-color: var(--editor-text-secondary);
    color: var(--editor-text-primary);
}

.assistant-input {
    width: 100%;
    min-height: 60px;
    max-height: 200px;
    padding: 10px 12px;
    font-size: 13px;
    line-height: 1.5;
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    resize: none;
    outline: none;
}

.assistant-input:focus {
    border-color: var(--editor-accent);
}

.assistant-footer-overlay {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 8px;
}

.assistant-left-tools, .assistant-right-tools {
    display: flex;
    align-items: center;
    gap: 6px;
}

.as-fab {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    color: var(--editor-text-secondary);
    cursor: pointer;
    font-size: 14px;
}

.as-fab:hover {
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
}

.assistant-send {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
}

.assistant-send:hover {
    background: linear-gradient(135deg, #6d28d9, #5b21b6);
}

/* Status Bar */
.editor-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 12px;
    background: var(--editor-bg-secondary);
    border-top: 1px solid var(--editor-border-color);
    font-size: 11px;
    color: var(--editor-text-secondary);
    flex-shrink: 0;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-file-path {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--editor-text-primary);
}

/* Context Menu */
.context-menu {
    position: fixed;
    z-index: 10000;
    min-width: 200px;
    background: var(--editor-bg-secondary);
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
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
    padding: 8px 14px;
    font-size: 13px;
    color: var(--editor-text-primary);
    cursor: pointer;
    transition: background 0.1s;
}

.context-menu-item:hover {
    background: rgba(124, 58, 237, 0.15);
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

/* Input Dialog */
.input-dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
}

.input-dialog {
    background: var(--editor-bg-secondary);
    border: 1px solid var(--editor-border-color);
    border-radius: 8px;
    padding: 20px;
    min-width: 360px;
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
}

.input-dialog h3 {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--editor-text-primary);
}

.input-dialog input {
    width: 100%;
    padding: 10px 12px;
    font-size: 13px;
    border: 1px solid var(--editor-border-color);
    border-radius: 4px;
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
    outline: none;
}

.input-dialog input:focus {
    border-color: var(--editor-accent);
}

.input-dialog-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}

.input-dialog-buttons button {
    padding: 8px 16px;
    font-size: 12px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid var(--editor-border-color);
    background: var(--editor-bg-primary);
    color: var(--editor-text-primary);
}

.input-dialog-buttons button.primary {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    border-color: transparent;
    color: #fff;
}

.input-dialog-buttons button.danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.input-dialog-buttons button:hover {
    opacity: 0.9;
}

/* Toast */
.editor-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    padding: 10px 20px;
    background: var(--editor-bg-secondary);
    color: var(--editor-text-primary);
    border: 1px solid var(--editor-border-color);
    border-radius: 6px;
    font-size: 13px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 9999;
}

.editor-toast.visible {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

/* Progress Tracker */
.chat-progress-tracker {
    padding: 8px 12px;
    border-bottom: 1px solid var(--editor-border-color);
}

.tracker-section {
    margin-bottom: 8px;
}

.tracker-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tracker-header {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    color: var(--editor-text-secondary);
    font-size: 12px;
    cursor: pointer;
    padding: 4px 0;
}

.tracker-header:hover {
    color: var(--editor-text-primary);
}

.tracker-icon {
    width: 12px;
    height: 12px;
    transition: transform 0.15s;
}

.tracker-header[aria-expanded="true"] .tracker-icon {
    transform: rotate(90deg);
}

.tracker-count, .tracker-stats {
    font-size: 11px;
    opacity: 0.7;
}

.tracker-content {
    padding-left: 18px;
}

.tracker-list {
    list-style: none;
    margin: 0;
    padding: 4px 0;
}

.tracker-list li {
    font-size: 12px;
    padding: 2px 0;
    color: var(--editor-text-secondary);
}

.tracker-actions {
    display: flex;
    gap: 6px;
}

.tracker-action-btn {
    padding: 2px 8px;
    font-size: 11px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid var(--editor-border-color);
    background: var(--editor-bg-primary);
    color: var(--editor-text-secondary);
}

.tracker-action-btn:hover {
    color: var(--editor-text-primary);
}
</style>
</head>
<body>
<?php if ($needsSetup): ?>
    <!-- Setup Wizard - shown when no LXD container is running -->
    <div class="editor-shell flex items-center justify-center" style="height: 100vh;">
        <div class="text-center p-8 max-w-lg">
            <div class="mb-6">
                <svg class="w-20 h-20 mx-auto text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold mb-4" style="color: var(--editor-text-primary);">Set Up Your Sandbox</h1>
            <p class="mb-6" style="color: var(--editor-text-secondary);">
                Your personal development environment is not running yet. Click below to create your sandbox container where you can build and run your projects.
            </p>
            <button id="setup-sandbox-btn" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Create Sandbox
            </button>
            <div id="setup-status" class="mt-4 hidden">
                <div class="flex items-center justify-center gap-2 text-purple-600 dark:text-purple-400">
                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="setup-status-text">Creating sandbox...</span>
                </div>
            </div>
            <div id="setup-error" class="mt-4 hidden text-red-600 dark:text-red-400"></div>
        </div>
    </div>
    <script>
    document.getElementById('setup-sandbox-btn').addEventListener('click', async function() {
        const btn = this;
        const statusDiv = document.getElementById('setup-status');
        const statusText = document.getElementById('setup-status-text');
        const errorDiv = document.getElementById('setup-error');
        
        btn.disabled = true;
        btn.style.opacity = '0.5';
        statusDiv.classList.remove('hidden');
        errorDiv.classList.add('hidden');
        
        try {
            statusText.textContent = 'Creating sandbox container...';
            
            const response = await fetch('/lxc/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: JSON.stringify({})
            });
            
            const data = await response.json();
            
            if (data.success || data.status === 'success') {
                statusText.textContent = 'Sandbox created! Refreshing...';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error(data.error || data.message || 'Failed to create sandbox');
            }
        } catch (err) {
            btn.disabled = false;
            btn.style.opacity = '1';
            statusDiv.classList.add('hidden');
            errorDiv.classList.remove('hidden');
            errorDiv.textContent = 'Error: ' + err.message;
        }
    });
    </script>
<?php else: ?>
    <div class="editor-shell">
        <!-- Mobile overlay for file tree -->
        <div class="mobile-tree-overlay" id="mobile-tree-overlay"></div>
        
        <!-- Header -->
        <div class="editor-header">
            <div class="editor-header-left">
                <!-- Mobile menu button (hamburger) -->
                <button class="mobile-menu-btn" id="mobile-menu-btn" title="Toggle file explorer">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                
                <select id="file-select" class="file-select">
                    <option value="" disabled selected>My Files</option>
                    <?php if ($file): ?>
                    <option value="<?= htmlspecialchars($encoded) ?>"><?= htmlspecialchars($file) ?></option>
                    <?php endif; ?>
                </select>
                <?php if (!empty($sandboxId)): ?>
                    <span class="sandbox-text-badge">
                        Sandbox • <strong><?= $sandboxId ?></strong>
                        — <a href="/editor">Open</a>
                    </span>
                    <button id="destroy-sandbox-btn" class="editor-btn editor-btn-danger" title="Destroy this sandbox permanently" style="margin-left:8px; background:#dc2626; color:#fff; display:flex; align-items:center; gap:4px; padding:4px 10px; border-radius:4px; font-size:12px;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Destroy Sandbox
                    </button>
                <?php endif; ?>
            </div>
            <div class="editor-header-right">
                <!-- Root path display -->
                <span id="editor-debug-root" class="root-display"><?= $sandboxId ? 'Sandbox: ' . htmlspecialchars($sandboxId) : 'Root: ' . htmlspecialchars(realpath($editorRoot) ?: ROOT_PATH) ?></span>
                
                <!-- Lock/Sandbox toggle -->
                <button id="admin-sandbox-action" class="editor-btn editor-btn-secondary" title="Toggle sandbox mode">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                
                <!-- Code view toggle -->
                <button id="toggle-editor-btn" class="editor-btn editor-btn-secondary" title="Return to code view">&lt;/&gt;</button>
                
                <!-- Views pane (preview) -->
                <button id="views-btn" class="editor-btn editor-btn-secondary" title="Preview file">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
                
                <!-- Open in new tab -->
                <button id="open-file-btn" class="editor-btn editor-btn-secondary" title="Open file in new tab">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </button>
                
                <!-- Debug toggle -->
                <button id="views-debug-btn" class="editor-btn editor-btn-secondary" title="Toggle debug info">
                    <i class="fas fa-bug"></i>
                </button>
                
                <!-- Fullscreen -->
                <button id="fullscreen-btn" class="editor-btn editor-btn-secondary" title="Toggle fullscreen">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                </button>
                
                <!-- Save button -->
                <button id="save-btn" class="editor-btn editor-btn-primary" title="Save file (Ctrl+S)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    Save
                </button>
            </div>
        </div>
        
        <!-- Session debug panel -->
        <pre id="session-debug-panel" class="debug-panel">Session debug output</pre>
        
        <!-- Main Editor Area -->
        <div class="editor-main">
            <!-- File Tree -->
            <div class="file-tree" id="file-tree">
                <div class="file-tree-header">
                    <span>Explorer</span>
                    <div style="display:flex;gap:4px;">
                        <button id="new-file-btn" title="New File">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </button>
                        <button id="new-folder-btn" title="New Folder">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            </svg>
                        </button>
                        <button id="refresh-tree-btn" title="Refresh">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="tree-content">
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
                    Cut <kbd>Ctrl+X</kbd>
                </div>
                <div class="context-menu-item" data-action="copy">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Copy <kbd>Ctrl+C</kbd>
                </div>
                <div class="context-menu-item" data-action="paste">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Paste <kbd>Ctrl+V</kbd>
                </div>
                <div class="context-menu-separator"></div>
                <div class="context-menu-item" data-action="rename">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Rename <kbd>F2</kbd>
                </div>
                <div class="context-menu-item" data-action="delete">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete <kbd>Del</kbd>
                </div>
                <div class="context-menu-separator"></div>
                <div class="context-menu-item" data-action="copy-path">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    Copy Path
                </div>
                <div class="context-menu-separator"></div>
                <div class="context-menu-item" data-action="refresh">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Refresh
                </div>
            </div>
            
            <!-- Resizer -->
            <div class="editor-resizer" id="editor-resizer"></div>
            
            <!-- Editor Workspace -->
            <div class="editor-workspace">
                <div id="monaco-editor"></div>
                <textarea id="editor-content" spellcheck="false" style="display:none;"><?= htmlspecialchars($content) ?></textarea>
            </div>
            
            <!-- Right Resizer -->
            <div class="editor-resizer" id="editor-resizer-right"></div>
            
            <!-- Mobile Chat Overlay -->
            <div class="mobile-chat-overlay" id="mobile-chat-overlay"></div>
            
            <!-- Floating Chat Button (Mobile) -->
            <button class="mobile-chat-fab" id="mobile-chat-fab" title="Chat with AI">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </button>
            
            <!-- Right Pane / Chat -->
            <div class="editor-right-pane" id="editor-right-pane">
                <div id="assistant-pane" class="assistant-pane">
                    <div class="assistant-header">
                        <div class="assistant-title-row">
                            <div class="assistant-tabs" id="chat-tabs-container">
                                <button class="assistant-tab active" data-tab-id="1" aria-pressed="true">
                                    <span class="tab-label">Chat 1</span>
                                    <span class="tab-close" title="Close tab">×</span>
                                </button>
                            </div>
                            <div class="assistant-controls">
                                <button class="as-btn" id="editor-tts-toggle" title="Toggle TTS">🔊</button>
                                <button class="as-btn" id="editor-stt-toggle" title="Start/Stop STT">🎤</button>
                                <button class="as-btn" id="new-chat-tab-btn" title="New conversation">＋</button>
                                <button class="as-btn" id="clear-chat-btn" title="Clear current chat">🗑</button>
                                <button class="as-btn" title="History">⤺</button>
                                <button class="as-btn" title="Settings">⚙</button>
                                <div class="as-divider"></div>
                                <button class="as-btn as-close" id="close-chat-pane" title="Close pane">✕</button>
                            </div>
                        </div>
                        <div class="assistant-audio-status" id="assistant-audio-status">
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
                    
                    <div class="assistant-body" id="assistant-body">
                        <div class="assistant-empty">No conversation yet — ask me about your files.</div>
                    </div>
                    
                    <div class="assistant-footer">
                        <!-- Progress Tracker -->
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
                        
                        <!-- Composer -->
                        <div class="assistant-composer assistant-composer--vscode">
                            <div class="assistant-composer-header">
                                <div class="attached-overlay">
                                    <svg class="attach-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M13.5 7.5L7.5 13.5C6.12 14.88 3.88 14.88 2.5 13.5C1.12 12.12 1.12 9.88 2.5 8.5L9 2C9.93 1.07 11.43 1.07 12.36 2C13.29 2.93 13.29 4.43 12.36 5.36L6.36 11.36C5.97 11.75 5.34 11.75 4.95 11.36C4.56 10.97 4.56 10.34 4.95 9.95L10.45 4.45" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                    </svg>
                                    <div class="attached-file" data-path="<?= htmlspecialchars($file) ?>">
                                        <span class="file-tag"><?= strtoupper($ext) ?></span>
                                        <span class="file-name"><?= $file ? basename($file) : 'No file' ?></span>
                                    </div>
                                    <button id="assistant-attach" class="as-attach" title="Attach another file">+</button>
                                </div>
                            </div>
                            
                            <textarea id="assistant-input" class="assistant-input" placeholder="Describe what to build next"></textarea>
                            
                            <div class="assistant-footer-overlay">
                                <div class="assistant-left-tools">
                                    <button class="as-fab" title="Options">⚒</button>
                                </div>
                                <div class="assistant-right-tools">
                                    <button id="assistant-send" class="assistant-send" title="Send message">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M2 21L23 12L2 3v7l13 2-13 2v7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Bar -->
        <div class="editor-status-bar">
            <div class="status-item">
                <span id="file-path" class="status-file-path" title="Current file"><?= $file ? htmlspecialchars($file) : 'No file' ?></span>
                <span style="margin:0 8px;">|</span>
                <span>Ln <span id="line-num">1</span>, Col <span id="col-num">1</span></span>
                <span style="margin:0 8px;">|</span>
                <span id="lang-display"><?= strtoupper($language) ?></span>
            </div>
            <div class="status-item">
                <span id="editor-status">Ready</span>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="editor-toast" class="editor-toast"></div>
    
    <!-- Hidden Save Form -->
    <form id="save-form" method="post" action="/playground/editor/save" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="file" id="save-file" value="<?= htmlspecialchars($encoded) ?>">
        <input type="hidden" name="content" id="save-content" value="">
    </form>
    
    <!-- Mobile Detection & Editor Selection -->
    <script>
    (function() {
        // Detect mobile/touch devices
        var hasTouchScreen = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        var isSmallScreen = window.innerWidth < 768;
        var isMobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        window.isMobileDevice = (hasTouchScreen && isSmallScreen) || (isMobileUA && isSmallScreen);
        
        // Add class to body for CSS targeting
        if (window.isMobileDevice) {
            document.body.classList.add('mobile-editor');
            console.log('[Editor] Mobile device detected, will use CodeMirror');
        } else {
            document.body.classList.add('desktop-editor');
            console.log('[Editor] Desktop device detected, will use Monaco');
        }
    })();
    </script>
    
    <!-- Load Mobile Editor (CodeMirror) for mobile devices - MUST load before editor-object.js -->
    <script>
    if (window.isMobileDevice) {
        document.write('<script src="/assets/js/editor-mobile.js"><\/script>');
    }
    </script>
    
    <!-- Monaco Editor (Desktop only) -->
    <script>
    if (!window.isMobileDevice) {
        document.write('<script src="/assets/vendor/monaco-editor/min/vs/loader.js"><\/script>');
    }
    </script>
    <script>
    // Configure Monaco (desktop only)
    if (!window.isMobileDevice && typeof require !== 'undefined' && require.config) {
        require.config({
            paths: { 'vs': '/assets/vendor/monaco-editor/min/vs' }
        });
    }
    
    // Load editor-object.js which handles all Monaco, file tree, and chat functionality
    </script>
    <script src="/assets/js/editor-object.js"></script>
    
    <script>
    // Additional page-specific initialization
    (function() {
        // ============================================
        // MOBILE MENU TOGGLE
        // ============================================
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileTreeOverlay = document.getElementById('mobile-tree-overlay');
        const fileTree = document.getElementById('file-tree');
        
        function openMobileMenu() {
            if (fileTree) fileTree.classList.add('mobile-open');
            if (mobileTreeOverlay) mobileTreeOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileMenu() {
            if (fileTree) fileTree.classList.remove('mobile-open');
            if (mobileTreeOverlay) mobileTreeOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                if (fileTree && fileTree.classList.contains('mobile-open')) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            });
        }
        
        if (mobileTreeOverlay) {
            mobileTreeOverlay.addEventListener('click', closeMobileMenu);
        }
        
        // Close mobile menu when file is selected
        document.addEventListener('click', (e) => {
            if (e.target.closest('.file-item') && window.innerWidth <= 768) {
                setTimeout(closeMobileMenu, 150);
            }
        });
        
        // Expose for other scripts
        window.closeMobileMenu = closeMobileMenu;
        window.openMobileMenu = openMobileMenu;
        
        // ============================================
        // MOBILE CHAT TOGGLE (Floating Action Button)
        const mobileChatFab = document.getElementById('mobile-chat-fab');
        const mobileChatOverlay = document.getElementById('mobile-chat-overlay');
        const rightPane = document.getElementById('editor-right-pane');
        const closeChatBtn = document.getElementById('close-chat-pane');
        
        function openMobileChat() {
            rightPane?.classList.add('mobile-chat-open');
            mobileChatOverlay?.classList.add('active');
            mobileChatFab?.classList.add('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileChat() {
            rightPane?.classList.remove('mobile-chat-open');
            mobileChatOverlay?.classList.remove('active');
            mobileChatFab?.classList.remove('hidden');
            document.body.style.overflow = '';
        }
        
        if (mobileChatFab) {
            mobileChatFab.addEventListener('click', openMobileChat);
        }
        
        if (mobileChatOverlay) {
            mobileChatOverlay.addEventListener('click', closeMobileChat);
        }
        
        if (closeChatBtn) {
            closeChatBtn.addEventListener('click', closeMobileChat);
        }
        
        // Expose for other scripts
        window.openMobileChat = openMobileChat;
        window.closeMobileChat = closeMobileChat;
        
        // ============================================
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const isDark = document.documentElement.classList.toggle('dark');
                // Sync with all theme keys including parent chat page
                localStorage.setItem('ginto-theme', isDark ? 'dark' : 'light');
                localStorage.setItem('editor-theme', isDark ? 'dark' : 'light');
                localStorage.setItem('playground-theme', isDark ? 'dark' : 'light');
                if (window.monaco && window.editor) {
                    monaco.editor.setTheme(isDark ? 'vs-dark' : 'vs');
                }
            });
        }
        
        // Fullscreen toggle
        const fullscreenBtn = document.getElementById('fullscreen-btn');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                } else {
                    document.exitFullscreen();
                }
            });
        }
        
        // Resizer logic
        function initResizer(resizerId, leftPaneId, direction = 'left') {
            const resizer = document.getElementById(resizerId);
            const leftPane = document.getElementById(leftPaneId);
            if (!resizer || !leftPane) return;
            
            let isResizing = false;
            let startX = 0;
            let startWidth = 0;
            
            resizer.addEventListener('mousedown', (e) => {
                isResizing = true;
                startX = e.clientX;
                startWidth = leftPane.offsetWidth;
                resizer.classList.add('dragging');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;
                const diff = direction === 'left' ? e.clientX - startX : startX - e.clientX;
                const newWidth = Math.max(180, Math.min(startWidth + diff, window.innerWidth * 0.5));
                leftPane.style.width = newWidth + 'px';
                if (direction === 'left') {
                    document.documentElement.style.setProperty('--file-tree-width', newWidth + 'px');
                } else {
                    document.documentElement.style.setProperty('--right-pane-width', newWidth + 'px');
                }
            });
            
            document.addEventListener('mouseup', () => {
                if (isResizing) {
                    isResizing = false;
                    resizer.classList.remove('dragging');
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                }
            });
        }
        
        initResizer('editor-resizer', 'file-tree', 'left');
        initResizer('editor-resizer-right', 'editor-right-pane', 'right');
        
        // Header new file/folder buttons
        // NOTE: Always add event listeners - functions are checked at click time, not parse time
        const newFileBtn = document.getElementById('new-file-btn');
        const newFolderBtn = document.getElementById('new-folder-btn');
        const refreshBtn = document.getElementById('refresh-tree-btn');
        
        if (newFileBtn) {
            newFileBtn.addEventListener('click', () => {
                if (window.createNewFile) {
                    window.createNewFile('');
                } else {
                    console.warn('createNewFile not available yet');
                }
            });
        }
        if (newFolderBtn) {
            newFolderBtn.addEventListener('click', () => {
                if (window.createNewFolder) {
                    window.createNewFolder('');
                } else {
                    console.warn('createNewFolder not available yet');
                }
            });
        }
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                if (window.refreshTree) {
                    window.refreshTree();
                } else {
                    console.warn('refreshTree not available yet');
                }
            });
        }
        
        // Debug panel toggle
        const debugBtn = document.getElementById('views-debug-btn');
        const debugPanel = document.getElementById('session-debug-panel');
        if (debugBtn && debugPanel) {
            debugBtn.addEventListener('click', () => {
                debugPanel.classList.toggle('visible');
                if (debugPanel.classList.contains('visible')) {
                    // Populate debug info
                    debugPanel.textContent = JSON.stringify({
                        sandboxId: <?= json_encode($sandboxId) ?>,
                        editorRoot: <?= json_encode(realpath($editorRoot) ?: $editorRoot) ?>,
                        isAdmin: <?= json_encode($isAdminSession) ?>,
                        currentFile: window.currentFile || null,
                        theme: localStorage.getItem('editor-theme') || 'auto'
                    }, null, 2);
                }
            });
        }
        
        // Views button - open preview overlay
        const viewsBtn = document.getElementById('views-btn');
        if (viewsBtn) {
            viewsBtn.addEventListener('click', () => {
                openViewsOverlay();
            });
        }
        
        // Views overlay logic
        function openViewsOverlay() {
            const overlayId = 'editor-views-overlay';
            let overlay = document.getElementById(overlayId);
            
            // Get current editor content
            function getEditorContent() {
                if (window.GintoEditor && window.GintoEditor.getContent) {
                    return window.GintoEditor.getContent();
                }
                const textarea = document.getElementById('editor-content');
                return textarea ? textarea.value : '';
            }
            
            // Get current file extension
            function getCurrentFileExtension() {
                const file = window.currentFile || '';
                const match = file.match(/\.([^.]+)$/);
                return match ? match[1].toLowerCase() : '';
            }
            
            // Check if file needs server-side execution (PHP, etc.)
            function needsServerExecution() {
                const ext = getCurrentFileExtension();
                return ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8'].includes(ext);
            }
            
            // Build sandbox proxy URL for preview - uses the CURRENT file being viewed
            function getSandboxPreviewUrl() {
                const sandboxId = window.editorConfig?.sandboxId;
                const currentFile = window.currentFile;
                if (sandboxId && currentFile) {
                    return `/sandbox-preview/${sandboxId}/${currentFile.replace(/^\//, '')}`;
                }
                return null;
            }
            
            // Update iframe content based on file type
            async function updatePreviewIframe(iframe) {
                const sandboxId = window.editorConfig?.sandboxId;
                const currentFile = window.currentFile || '';
                
                // For sandbox files: always use proxy (let the browser/server handle rendering)
                if (sandboxId && currentFile) {
                    if (window.GintoEditor && window.GintoEditor.save) {
                        await window.GintoEditor.save();
                    }
                    const previewUrl = getSandboxPreviewUrl();
                    if (previewUrl) {
                        iframe.src = previewUrl;
                        return;
                    }
                }
                
                // Fallback: render editor content directly
                iframe.srcdoc = getEditorContent();
            }
            
            // If overlay exists, always show it (no toggle - use </> or X to close)
            if (overlay) {
                overlay.style.display = 'flex';
                const f = overlay.querySelector('iframe');
                if (f) updatePreviewIframe(f);
                return;
            }
            
            // Create overlay
            overlay = document.createElement('div');
            overlay.id = overlayId;
            overlay.style.cssText = 'position:absolute;inset:0;z-index:50;display:flex;flex-direction:column;background:#fff;';
            
            // Close button
            const closeBar = document.createElement('div');
            closeBar.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#1f2937;color:#fff;font-size:12px;';
            closeBar.innerHTML = '<span>Preview</span><button id="close-views-overlay" style="background:none;border:none;color:#fff;cursor:pointer;font-size:16px;">✕</button>';
            overlay.appendChild(closeBar);
            
            // Iframe for preview
            const iframe = document.createElement('iframe');
            iframe.style.cssText = 'flex:1;width:100%;border:0;background:#fff;';
            updatePreviewIframe(iframe);
            overlay.appendChild(iframe);
            
            // Append to workspace
            const workspace = document.querySelector('.editor-workspace');
            if (workspace) {
                workspace.style.position = 'relative';
                workspace.appendChild(overlay);
            }
            
            // Close button handler
            document.getElementById('close-views-overlay').addEventListener('click', () => {
                overlay.style.display = 'none';
            });
        }
        
        // Code toggle button - focus editor and close views overlay
        const toggleEditorBtn = document.getElementById('toggle-editor-btn');
        if (toggleEditorBtn) {
            toggleEditorBtn.addEventListener('click', () => {
                // Close views overlay if open
                const overlay = document.getElementById('editor-views-overlay');
                if (overlay) overlay.style.display = 'none';
                // Focus editor
                if (window.GintoEditor && window.GintoEditor.getEditor()) {
                    window.GintoEditor.getEditor().focus();
                }
            });
        }
        
        // Destroy Sandbox button
        const destroyBtn = document.getElementById('destroy-sandbox-btn');
        if (destroyBtn) {
            destroyBtn.addEventListener('click', async () => {
                const confirmed = await showEditorModal({
                    type: 'danger',
                    title: '⚠️ Destroy Sandbox?',
                    message: 'This will permanently delete all your files, the container, and all data. This action cannot be undone!',
                    confirmText: 'Destroy',
                    cancelText: 'Cancel'
                });
                if (!confirmed) return;
                
                destroyBtn.disabled = true;
                destroyBtn.innerHTML = '⏳ Destroying...';
                
                try {
                    const res = await fetch('/sandbox/destroy', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ csrf_token: window.GINTO_AUTH?.csrfToken || window.editorConfig?.csrfToken })
                    });
                    const data = await res.json();
                    
                    if (data.success) {
                        await showEditorModal({
                            type: 'success',
                            title: '✅ Sandbox Destroyed',
                            message: 'Your sandbox has been destroyed successfully.',
                            confirmText: 'OK',
                            hideCancel: true
                        });
                        // Notify parent window (chat.php) to close the editor modal
                        if (window.parent && window.parent !== window) {
                            window.parent.postMessage({ type: 'sandbox_destroyed' }, '*');
                        } else {
                            window.location.href = '/chat';
                        }
                    } else {
                        await showEditorModal({
                            type: 'danger',
                            title: '❌ Failed to Destroy Sandbox',
                            message: data.error || 'Unknown error occurred',
                            confirmText: 'OK',
                            hideCancel: true
                        });
                        destroyBtn.disabled = false;
                        destroyBtn.innerHTML = '🛑 Destroy Sandbox';
                    }
                } catch (e) {
                    await showEditorModal({
                        type: 'danger',
                        title: '❌ Error',
                        message: e.message,
                        confirmText: 'OK',
                        hideCancel: true
                    });
                    destroyBtn.disabled = false;
                    destroyBtn.innerHTML = '🛑 Destroy Sandbox';
                }
            });
        }
        
        // Save button
        const saveBtn = document.getElementById('save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                if (window.GintoEditor && window.GintoEditor.save) {
                    window.GintoEditor.save();
                }
            });
        }
        
        // Open file button - open current file in new tab
        const openFileBtn = document.getElementById('open-file-btn');
        if (openFileBtn) {
            openFileBtn.addEventListener('click', () => {
                const sandboxId = window.editorConfig?.sandboxId;
                const currentFile = window.currentFile;
                if (sandboxId && currentFile) {
                    // Build URL to sandbox file - use /clients/ route (proxied via port 1800)
                    const clientUrl = '/clients/' + currentFile;
                    window.open(clientUrl, '_blank');
                } else if (currentFile) {
                    // Try to open file directly
                    const filePath = '/files/' + currentFile;
                    window.open(filePath, '_blank');
                } else {
                    if (window.showToast) window.showToast('No file selected', true);
                }
            });
        }
        
        // File select dropdown (removed, using file-path display instead)
    })();
    </script>
<?php endif; ?>

<!-- Universal Editor Modal -->
<div id="editor-modal" class="fixed inset-0 z-[200] hidden" style="position:fixed;inset:0;z-index:200;display:none;">
    <!-- Backdrop -->
    <div class="editor-modal-backdrop" onclick="closeEditorModal(false)" style="position:absolute;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);"></div>
    <!-- Modal Content -->
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:16px;">
        <div id="editor-modal-content" style="background:#1e1e1e;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);max-width:420px;width:100%;transform:scale(0.95);opacity:0;transition:transform 0.15s ease-out, opacity 0.15s ease-out;border:1px solid #3c3c3c;">
            <!-- Header -->
            <div style="padding:20px;border-bottom:1px solid #3c3c3c;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div id="editor-modal-icon" style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(220,38,38,0.2);">
                        <svg style="width:20px;height:20px;color:#f87171;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="editor-modal-title" style="font-size:18px;font-weight:600;color:#fff;margin:0;">Confirm Action</h3>
                        <p id="editor-modal-message" style="font-size:14px;color:#a0a0a0;margin:6px 0 0 0;line-height:1.5;">Are you sure you want to proceed?</p>
                    </div>
                </div>
            </div>
            <!-- Actions -->
            <div style="padding:16px;display:flex;justify-content:flex-end;gap:12px;background:#252526;border-radius:0 0 12px 12px;">
                <button id="editor-modal-cancel" onclick="closeEditorModal(false)" style="padding:8px 16px;font-size:14px;font-weight:500;color:#ccc;background:#374151;border:1px solid #4b5563;border-radius:8px;cursor:pointer;transition:background 0.15s;">
                    Cancel
                </button>
                <button id="editor-modal-action" onclick="closeEditorModal(true)" style="padding:8px 16px;font-size:14px;font-weight:500;color:#fff;background:#dc2626;border:none;border-radius:8px;cursor:pointer;transition:background 0.15s;">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Universal Editor Modal System
let editorModalResolve = null;

function showEditorModal(options = {}) {
    const modal = document.getElementById('editor-modal');
    const content = document.getElementById('editor-modal-content');
    const title = document.getElementById('editor-modal-title');
    const message = document.getElementById('editor-modal-message');
    const actionBtn = document.getElementById('editor-modal-action');
    const cancelBtn = document.getElementById('editor-modal-cancel');
    const iconContainer = document.getElementById('editor-modal-icon');
    
    if (!modal) return Promise.resolve(false);
    
    // Set content
    title.textContent = options.title || 'Confirm Action';
    message.textContent = options.message || 'Are you sure you want to proceed?';
    actionBtn.textContent = options.confirmText || 'Confirm';
    cancelBtn.textContent = options.cancelText || 'Cancel';
    
    // Show/hide cancel button
    cancelBtn.style.display = options.hideCancel ? 'none' : 'block';
    
    // Set button color based on type
    const type = options.type || 'danger';
    const colors = {
        danger: { bg: '#dc2626', hover: '#b91c1c', iconBg: 'rgba(220,38,38,0.2)', iconColor: '#f87171' },
        success: { bg: '#059669', hover: '#047857', iconBg: 'rgba(5,150,105,0.2)', iconColor: '#34d399' },
        warning: { bg: '#d97706', hover: '#b45309', iconBg: 'rgba(217,119,6,0.2)', iconColor: '#fbbf24' },
        info: { bg: '#7c3aed', hover: '#6d28d9', iconBg: 'rgba(124,58,237,0.2)', iconColor: '#a78bfa' }
    };
    const c = colors[type] || colors.danger;
    
    actionBtn.style.background = c.bg;
    actionBtn.onmouseenter = () => actionBtn.style.background = c.hover;
    actionBtn.onmouseleave = () => actionBtn.style.background = c.bg;
    
    iconContainer.style.background = c.iconBg;
    
    // Set icon based on type
    const icons = {
        danger: `<svg style="width:20px;height:20px;color:${c.iconColor};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`,
        success: `<svg style="width:20px;height:20px;color:${c.iconColor};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`,
        warning: `<svg style="width:20px;height:20px;color:${c.iconColor};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`,
        info: `<svg style="width:20px;height:20px;color:${c.iconColor};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
    };
    iconContainer.innerHTML = icons[type] || icons.danger;
    
    // Show modal
    modal.style.display = 'block';
    requestAnimationFrame(() => {
        content.style.transform = 'scale(1)';
        content.style.opacity = '1';
    });
    
    // Return promise that resolves when user makes a choice
    return new Promise((resolve) => {
        editorModalResolve = resolve;
    });
}

function closeEditorModal(result = false) {
    const modal = document.getElementById('editor-modal');
    const content = document.getElementById('editor-modal-content');
    
    if (!modal) return;
    
    content.style.transform = 'scale(0.95)';
    content.style.opacity = '0';
    
    setTimeout(() => {
        modal.style.display = 'none';
        if (editorModalResolve) {
            editorModalResolve(result);
            editorModalResolve = null;
        }
    }, 150);
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('editor-modal');
    if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
        closeEditorModal(false);
    }
});
</script>

</body>
</html>