<?php
// Start session if not already started - critical for accessing $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Ensure a CSRF token exists for forms/API calls and expose to this view
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // fallback if random_bytes fails
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// Check if user is logged in (using your existing logic)
$isUserLoggedIn = isset($_SESSION['user']);
$username = $isUserLoggedIn ? $_SESSION['user'] : null;
// Prefer the canonical username from the database when available instead
$userDisplay = $isUserLoggedIn ? $username : 'Guest';

// Attempt to enrich the view with DB-backed username and subscription plan
$db = null;
$userPlan = null;
$dbUsername = null;
if ($isUserLoggedIn && !empty($_SESSION['user_id'])) {
    try {
        if (class_exists('Ginto\\Core\\Database')) {
            $db = \Ginto\Core\Database::getInstance();
            $userRow = $db->get('users', '*', ['id' => (int)$_SESSION['user_id']]);
            if (is_array($userRow)) {
                if (!empty($userRow['username'])) {
                    $dbUsername = $userRow['username'];
                }
                // Look for common plan-related columns on users table
                $planCols = ['subscription_plan', 'plan', 'current_plan', 'tier', 'addon_plan', 'plan_id'];
                foreach ($planCols as $col) {
                    if (isset($userRow[$col]) && $userRow[$col] !== '') {
                        $userPlan = $userRow[$col];
                        break;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('code.view: user DB lookup failed: ' . $e->getMessage());
    }

    // Prefer DB username when present
    if (!empty($dbUsername)) {
        $userDisplay = $dbUsername;
    }
}

// $expiryTime is passed from HomeController::code() method ('YYYY-MM-DD HH:MM:SS' or null)
$expiryTimestampForJS = null;
$initialExpiryStatusMessage = "";
$initialExpiryStatusColorClass = "text-gray-400"; // Default/none
$initialStatusDotClass = "status-dot-none";

if ($isUserLoggedIn) {
    if (isset($expiryTime) && $expiryTime !== null) {
        $phpExpiryTimestamp = strtotime($expiryTime);
        if ($phpExpiryTimestamp !== false) { // Check if strtotime was successful
            if ($phpExpiryTimestamp > time()) {
                $expiryTimestampForJS = $phpExpiryTimestamp; // Pass to JS for live countdown
                $initialExpiryStatusMessage = "Loading timer..."; // JS will update this
                $initialExpiryStatusColorClass = "user-status-text-active";
                $initialStatusDotClass = "status-dot-active";
            } else {
                $initialExpiryStatusMessage = "Access Expired";
                $initialExpiryStatusColorClass = "user-status-text-expired";
                $initialStatusDotClass = "status-dot-expired";
            }
        } else {
            $initialExpiryStatusMessage = "Invalid Date Data"; // Error case
            $initialExpiryStatusColorClass = "user-status-text-expired";
            $initialStatusDotClass = "status-dot-expired";
        }
    } else {
        // If no expiry time is set, show the subscription plan label.
        $planLabel = 'Free';
        if (!empty($userPlan)) {
            $planLabel = $userPlan;
            // Treat explicit 'free' plan as the default (do not color the text)
            $normalizedPlan = strtolower(trim((string)$userPlan));
            if ($normalizedPlan !== 'free') {
                // Non-free plan => show active (green) dot and text
                $initialExpiryStatusColorClass = 'user-status-text-active';
                $initialStatusDotClass = 'status-dot-active';
            } else {
                // Explicitly 'free' — keep text muted but show neutral dot
                $initialExpiryStatusColorClass = 'user-status-text-none';
                $initialStatusDotClass = 'status-dot-none';
            }
        } else {
            // keep default gray styling for plan-only view
            $initialExpiryStatusColorClass = 'user-status-text-none';
            $initialStatusDotClass = 'status-dot-none';
        }
        $initialExpiryStatusMessage = 'Plan: ' . $planLabel;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark bg-[var(--bg-dark)] text-white">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
        <title>Sai Chat Pro</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Font Awesome is already included, which is good! -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
        <script src="https://www.paypal.com/sdk/js?client-id=AZgBWH2d8yEZqBxYOwpQhU_pD8M2R2InPsU80V97EGksIZzw-QzfvWcCbP3J3nKaQ6xQZ3ZkdurydxKo&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>
        <!-- SweetAlert2 for nicer modals -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&family=Inter:wght@400;500;600&display=swap");
            :root {
                --primary: #ec4899;
                --primary-hover: #db2777;
                --bg-dark: #0d1117;
                --bg-darker: #090c10;
                --border-dark: #1f2937;
                --text-muted: #9ca3af;
                --text-light: #e5e7eb;
                --green-active: #34d399;
                --green-text-active: #6ee7b7;
                --red-expired: #f87171;
                --red-text-expired: #fda4af;
                --gray-status-dot: #6b7280;
            }
            html,
            body {
                height: 100%;
                margin: 0;
                overflow: hidden;
                font-family: "Inter", sans-serif;
                -webkit-text-size-adjust: 100%;
            }
            @media (max-width: 639px) {
                html:not(.mobile-menu-open),
                html:not(.mobile-menu-open) body {
                    overflow: hidden;
                }
            }
            html.mobile-menu-open,
            html.mobile-menu-open body {
                overflow-y: auto !important;
            }

            #editor { width: 100%; height: 100%; }
            #dragbar { cursor: ew-resize; width: 6px; background-color: var(--border-dark); transition: background-color 0.2s; z-index: 50; }
            @media (min-width: 640px) { #dragbar { height: 100%; } }
            @media (max-width: 639px) { #dragbar { display:none; } }
            #dragbar:hover { background-color: var(--primary); }

            #prompt { resize: none; min-height: 120px; max-height: 300px; line-height: 1.5; overflow-y: auto; font-family: "Inter", sans-serif; }
            #prompt::-webkit-scrollbar { width: 8px; }
            #prompt::-webkit-scrollbar-thumb { background-color: #4b5563; border-radius: 4px; }
            #prompt::-webkit-scrollbar-track { background-color: #1f2937; }

            .monaco-editor { --vscode-editor-background: var(--bg-darker) !important; }

            /* Mobile: enable text selection inside Monaco/CodeMirror */
            @media (max-width: 768px) {
                .monaco-editor .view-line,
                .CodeMirror-line,
                .CodeMirror-code {
                    -webkit-user-select: text;
                    user-select: text;
                }
            }
            .splash-screen { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: var(--bg-darker); display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 1000; transition: opacity 0.5s ease-out; }
            .splash-logo { font-size: 2.5rem; font-weight: 600; margin-bottom: 2rem; background: linear-gradient(90deg, var(--primary), #8b5cf6); -webkit-background-clip: text; background-clip: text; color: transparent; }
            .loader { width: 64px; height: 64px; border: 5px solid var(--border-dark); border-bottom-color: var(--primary); border-radius: 50%; display: inline-block; box-sizing: border-box; animation: rotation 1s linear infinite; }
            @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .typing-indicator { display: flex; gap: 4px; margin-left: 8px; }
            .typing-dot { width: 8px; height: 8px; background-color: var(--text-muted); border-radius: 50%; animation: typingAnimation 1.4s infinite ease-in-out; }
            .typing-dot:nth-child(1) { animation-delay: 0s; }
            .typing-dot:nth-child(2) { animation-delay: 0.2s; }
            .typing-dot:nth-child(3) { animation-delay: 0.4s; }
            @keyframes typingAnimation { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-5px); } }
            .status-bar { font-family: "Fira Code", monospace; }
            #universalPremiumModal *, #universalPremiumModal *:before, #universalPremiumModal *:after { box-sizing: border-box; }
            @keyframes fadeInModal { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
            #universalPremiumModal .animate-fade-in { animation: fadeInModal 0.3s ease-out forwards; }
            #universalPremiumModal .feature-item:before { content: "✓"; color: var(--green-active); margin-right: 8px; }
            .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
            .status-dot-active { background-color: var(--green-active); }
            .status-dot-expired { background-color: var(--red-expired); }
            .status-dot-none { background-color: var(--gray-status-dot); }
            .user-status-text { font-size: 0.75rem; line-height: 1rem; vertical-align: middle; }
            .user-status-text-active { color: var(--green-text-active); }
            .user-status-text-expired { color: var(--red-text-expired); }
            .user-status-text-none { color: var(--text-muted); }

            /* CSS for Font Awesome fa-hand-point-down based animation */
            .pointing-hand-indicator {
                display: inline-block; /* For transform to work if needed and consistent layout */
                vertical-align: middle; /* Align better with text */
            }
            @keyframes nudge-hand-animation {
                0%, 100% {
                    transform: translateY(0px);
                }
                50% {
                    transform: translateY(3px); /* Nudge slightly downwards */
                }
            }
            .animate-nudge-hand .pointing-hand-indicator {
                animation: nudge-hand-animation 1.2s ease-in-out infinite;
            }
        </style>
        <script>
            window.APP_USERNAME = <?php echo json_encode($username); ?>;
            <?php if ($expiryTimestampForJS !== null): ?>
                window.CODE_ACCESS_EXPIRY_TIMESTAMP = <?php echo $expiryTimestampForJS; ?>;
            <?php else: ?>
                window.CODE_ACCESS_EXPIRY_TIMESTAMP = null;
            <?php endif; ?>
            window.IS_USER_LOGGED_IN = <?php echo json_encode($isUserLoggedIn); ?>;
            window.CODE_IS_PAID     = <?php echo json_encode(!empty($isPaid) || !empty($isAdmin)); ?>;
            window.CODE_PROMPTS_USED      = <?php echo (int)($promptsUsed ?? 0); ?>;
            window.CODE_PROMPTS_REMAINING = <?php echo (int)($promptsRemaining ?? \Ginto\Helpers\CodePromptLimiter::FREE_LIMIT); ?>;
            window.CODE_FREE_LIMIT        = <?php echo (int)(\Ginto\Helpers\CodePromptLimiter::FREE_LIMIT); ?>;
        </script>
    </head>
    <body class="flex flex-col h-screen bg-[var(--bg-dark)]">
        <div class="splash-screen">
            <div class="splash-logo">Sai Chat Pro</div>
            <div class="loader"></div>
        </div>

        <header class="h-16 px-4 bg-gray-900 border-b border-[var(--border-dark)] flex justify-between items-center shadow-lg sticky top-0 z-40 flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[var(--primary)] to-purple-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-code text-white text-base"></i>
                </div>
                <h1 class="text-lg sm:text-xl font-semibold text-gray-100">Sai Chat Pro</h1>
                <a href="/" title="Back to Home"
                   class="flex items-center px-3 py-1.5 text-sm rounded-md transition-colors duration-150
                          text-[var(--text-muted)] hover:text-white hover:bg-[var(--border-dark)]
                          focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:ring-opacity-50">
                    <i class="fas fa-home mr-0 sm:mr-2"></i>
                    <span class="hidden sm:inline">Home</span>
                </a>
            </div>
            <div class="flex-1 hidden sm:flex justify-center items-center px-1 sm:px-2 md:px-4">
                <?php if ($isUserLoggedIn): ?>
                <div class="flex items-center bg-[var(--bg-darker)] px-2 sm:px-3 py-1.5 rounded-lg border border-[var(--border-dark)] shadow-sm sm:min-w-[280px] justify-center">
                    <i class="fas fa-user-circle text-[var(--text-muted)] mr-1 sm:mr-2 text-xs sm:text-sm"></i>
                    <span class="text-xs sm:text-sm font-medium text-[var(--text-light)] mr-1 sm:mr-2 truncate max-w-[80px] sm:max-w-[100px] md:max-w-[150px]" title="<?php echo htmlspecialchars($username ?? ''); ?>"><?php echo htmlspecialchars($userDisplay); ?></span>
                    <span id="statusDot" class="status-dot <?php echo $initialStatusDotClass; ?>"></span>
                    <span id="codeAccessCountdown" class="user-status-text <?php echo $initialExpiryStatusColorClass; ?>">
                        <?php echo htmlspecialchars($initialExpiryStatusMessage); ?>
                    </span>
                        <!-- Removed duplicate plan label (already shown in the status message) -->
                </div>
                <?php else: ?>
                <div class="flex items-center text-xs sm:text-sm text-[var(--text-muted)] bg-[var(--bg-darker)] px-2 sm:px-3 py-1.5 rounded-lg border border-[var(--border-dark)] shadow-sm"><i class="fas fa-user-slash mr-1 sm:mr-2"></i> Guest Mode</div>
                <?php endif; ?>
            </div>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <button
                    id="runBtn"
                    title="Deploy on Sai Cloud (Feature Coming Soon)"
                    class="px-2 sm:px-3 py-1 sm:py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs rounded-md flex items-center gap-1 sm:gap-2 transition-colors duration-150 shadow focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
                >
                    <i class="fas fa-play text-xs"></i>
                    <span class="hidden sm:inline">Deploy</span> <span class="hidden md:inline">on Sai Cloud</span>
                </button>
                <span class="text-xs text-[var(--text-muted)] items-center hidden md:flex">
                    <i class="fas fa-bolt text-yellow-400 mr-1.5"></i>
                    By AI Connect Solutions
                </span>
                <button id="mobileMenuButton" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenuDrawer"
                        class="sm:hidden p-2 rounded-md text-[var(--text-muted)] hover:text-white hover:bg-[var(--border-dark)] focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>
        </header>

        <div id="mobileMenuDrawer" aria-hidden="true"
             class="hidden sm:hidden bg-gray-800 absolute top-16 left-0 right-0 z-30 p-4 border-b border-[var(--border-dark)] shadow-lg">
            <div class="mb-4">
                <?php if ($isUserLoggedIn): ?>
                <div class="flex flex-col items-center bg-[var(--bg-darker)] px-3 py-2 rounded-lg border border-[var(--border-dark)] shadow-sm w-full">
                    <div class="flex items-center mb-1">
                        <i class="fas fa-user-circle text-[var(--text-muted)] mr-2 text-sm"></i>
                        <span class="text-sm font-medium text-[var(--text-light)] truncate max-w-[180px]" title="<?php echo htmlspecialchars($username ?? ''); ?>"><?php echo htmlspecialchars($userDisplay); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span id="statusDotMobile" class="status-dot <?php echo $initialStatusDotClass; ?>"></span>
                        <span id="codeAccessCountdownMobile" class="user-status-text <?php echo $initialExpiryStatusColorClass; ?>">
                            <?php echo htmlspecialchars($initialExpiryStatusMessage); ?>
                        </span>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center justify-center text-sm text-[var(--text-muted)] bg-[var(--bg-darker)] px-3 py-2 rounded-lg border border-[var(--border-dark)] shadow-sm w-full"><i class="fas fa-user-slash mr-2"></i> Guest Mode</div>
                <?php endif; ?>
            </div>
            <button
                id="runBtnMobile"
                title="Deploy on Sai Cloud (Feature Coming Soon)"
                class="w-full mb-3 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-md flex items-center justify-center gap-2 transition-colors duration-150 shadow focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
            >
                <i class="fas fa-play text-xs"></i>
                <span>Deploy on Sai Cloud</span>
            </button>
            <div class="text-xs text-center text-[var(--text-muted)] pt-2 border-t border-[var(--border-dark)]">
                <i class="fas fa-bolt text-yellow-400 mr-1"></i>
                By AI Connect Solutions
            </div>
        </div>

        <div id="container" class="flex flex-col sm:flex-row flex-1 overflow-hidden">
            <div id="leftPane" class="w-full sm:w-[40%] flex flex-col sm:min-w-[200px] bg-[var(--bg-darker)] order-last sm:order-1
                                     h-[55vh] sm:h-full overflow-hidden">
                <div class="flex-1 relative overflow-x-hidden overflow-y-auto" id="editorContainer">
                    <div id="editor"></div>
                    <div id="typingIndicator" class="absolute bottom-4 left-4 bg-gray-800 px-3 py-2 rounded-md text-sm hidden">
                        <span class="text-gray-300">AI is generating...</span>
                        <div class="typing-indicator"> <div class="typing-dot"></div> <div class="typing-dot"></div> <div class="typing-dot"></div> </div>
                    </div>
                </div>
                <div id="composerFooter" class="p-3 border-t border-[var(--border-dark)] bg-[var(--bg-dark)] flex flex-col gap-2 flex-shrink-0 sticky bottom-0 z-20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-400 uppercase tracking-wider" for="prompt">AI Prompt</label>
                            <div class="animate-nudge-hand">
                                <!-- Using Font Awesome icon for pointing hand -->
                                <i class="fas fa-hand-point-down w-5 h-5 text-[var(--primary)] pointing-hand-indicator"></i>
                            </div>
                            <span class="text-xs text-gray-500 italic hidden sm:inline -ml-1">What to build?</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="hideComposerBtn" title="Hide composer" class="text-xs text-gray-400 hover:text-white"><i class="fas fa-chevron-down mr-1"></i>Hide</button>
                            <button id="clearPrompt" class="text-xs text-gray-400 hover:text-white"><i class="fas fa-times mr-1"></i>Clear</button>
                        </div>
                    </div>
                    <div class="relative">
                        <textarea
                            id="prompt"
                            placeholder="Ask AI to build UI (e.g. 'Create a responsive navbar with dark mode toggle')"
                            class="w-full bg-gray-800 text-white px-3 py-2 pr-14 text-sm border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--primary)]"
                        ></textarea>
                        <div class="absolute bottom-2 right-14 text-xs text-gray-500">Shift+Enter for new line</div>

                        <button id="send" aria-label="Send prompt" title="Send"
                                class="absolute bottom-2 right-2 w-10 h-10 bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white rounded-full flex items-center justify-center shadow focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:ring-opacity-50">
                            <i class="fas fa-paper-plane"></i>
                            <span class="sr-only">Send</span>
                        </button>
                    </div>

                    <div class="flex justify-between items-center mt-2">
                        <div class="text-xs text-gray-500 flex items-center gap-2">
                            <span class="status-bar"><span id="lineCount">1</span> lines</span><span>•</span><span id="charCount">0</span> chars
                        </div>
                        <div>
                            <!-- Prompt usage counter for non-paid users -->
                            <?php if (empty($isPaid) && empty($isAdmin)): ?>
                            <span id="code-prompts-counter" class="text-xs font-medium cursor-pointer hover:underline" title="Click to upgrade for unlimited prompts"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="dragbar" class="order-2"></div>
            <div id="rightPane" class="w-full sm:flex-1 bg-white relative pt-10
                                      h-[45vh] sm:h-full order-first sm:order-3 overflow-y-auto">
                <div id="previewToolbar" class="absolute top-0 left-0 right-0 h-10 bg-gray-100 border-b flex items-center px-3 gap-2 z-10 flex-shrink-0">
                    <button id="refreshPreview" class="p-1 text-gray-600 hover:text-gray-900"><i class="fas fa-sync-alt text-sm"></i></button>
                    <div class="text-xs text-gray-600">Preview</div>
                    <div class="ml-auto flex items-center gap-2">
                        <button id="toggleCodeViewBtn" title="Hide code view" aria-pressed="false" class="p-1 text-gray-600 hover:text-gray-900" style="display:inline-flex; align-items:center; justify-content:center;"><i class="fas fa-code text-sm"></i></button>
                        <button id="reloadPreviewFull" title="Reload page" class="p-1 text-gray-600 hover:text-gray-900"><i class="fas fa-redo-alt text-sm"></i></button>
                    </div>
                </div>
                <iframe id="previewFrame" class="w-full h-full border-none"></iframe>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
        <script>
            const SaiChatApp = {
                // --- STATE & CONFIGURATION ---
                monacoEditor: null,
                isGenerating: false,
                abortController: null,
                countdownIntervalId: null,
                countdownIntervalIdMobile: null,
                payPalButtonsRendered: false,

                appUsername: null,
                codeAccessExpiryTimestamp: null,
                isUserLoggedIn: false,

                // --- CORE METHODS ---
                formatRemainingTime: function(totalSeconds) {
                    if (totalSeconds <= 0) return "Expired";
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    if (document.documentElement.clientWidth < 640 && hours > 0) {
                        return `Exp: ${String(hours).padStart(1, "0")}h ${String(minutes).padStart(2, "0")}m`;
                    } else if (document.documentElement.clientWidth < 640) {
                        return `Exp: ${String(minutes).padStart(1, "0")}m ${String(seconds).padStart(2, "0")}s`;
                    }
                    return `Expires in: ${String(hours).padStart(2, "0")}h ${String(minutes).padStart(2, "0")}m ${String(seconds).padStart(2, "0")}s`;
                },
                
                deployToSaiCloud: async function() {
                    if (!this.monacoEditor) return alert('Editor not ready');
                    try {
                            const suggested = 'index.html';
                            const filenameInput = await this.showInputModal('Save to sandbox as (e.g. index.html):', suggested);
                            if (!filenameInput) return; // user cancelled
                        // basic sanitization: remove path traversal and weird chars
                        let safeFilename = filenameInput.replace(/\.\.+/g, '');
                        safeFilename = safeFilename.replace(/[^a-zA-Z0-9._\-\/]/g, '');
                        if (!safeFilename) return alert('Invalid filename.');

                        const content = this.monacoEditor.getValue();
                        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
                        const csrfHeader = metaCsrf ? metaCsrf.getAttribute('content') : '';

                        const runBtnEl = document.getElementById('runBtn');
                        if (runBtnEl) { runBtnEl.disabled = true; runBtnEl.classList.add('opacity-50'); }

                        const resp = await fetch('/site-save', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfHeader },
                            body: JSON.stringify({ filename: safeFilename, content }),
                        });

                        if (!resp.ok) {
                            const txt = await resp.text();
                            Swal.fire({
                                icon: 'error',
                                title: 'Save failed',
                                text: resp.status + ' - ' + (txt || resp.statusText),
                                confirmButtonText: 'OK'
                            });
                            return;
                        }

                        const data = await resp.json().catch(() => null);
                        if (data && data.success) {
                            const path = data.path || ("Websites/sai-code/" + safeFilename);
                            Swal.fire({
                                icon: 'success',
                                title: 'Saved',
                                text: 'Saved successfully to: ' + path,
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Save failed',
                                text: (data && data.error ? data.error : 'Unknown error'),
                                confirmButtonText: 'OK'
                            });
                        }
                    } catch (err) {
                        console.error('deployToSaiCloud error', err);
                        alert('Error saving to sandbox: ' + (err && err.message ? err.message : err));
                    } finally {
                        const runBtnEl2 = document.getElementById('runBtn');
                        if (runBtnEl2) { runBtnEl2.disabled = false; runBtnEl2.classList.remove('opacity-50'); }
                    }
                },
                
                showInputModal: function(message, defaultValue) {
                    return new Promise((resolve) => {
                        let modal = document.getElementById('universalInputModal');
                        if (!modal) return resolve(null);
                        const msgEl = modal.querySelector('.uim-message');
                        const inputEl = modal.querySelector('.uim-input');
                        const okBtn = modal.querySelector('.uim-ok');
                        const cancelBtn = modal.querySelector('.uim-cancel');

                        msgEl.textContent = message || '';
                        inputEl.value = defaultValue || '';
                        okBtn.disabled = !inputEl.value;
                        // show modal and trap focus
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                        setTimeout(() => {
                            inputEl.focus();
                            inputEl.select();
                        }, 50);

                        // enable/disable OK based on content
                        function onInput() { okBtn.disabled = inputEl.value.trim() === ''; }
                        inputEl.addEventListener('input', onInput);

                        function cleanup(value) {
                            modal.classList.add('hidden');
                            document.body.style.overflow = '';
                            okBtn.removeEventListener('click', onOk);
                            cancelBtn.removeEventListener('click', onCancel);
                            modal.removeEventListener('keydown', onKeydown);
                            inputEl.removeEventListener('input', onInput);
                            modal.removeEventListener('click', onOverlayClick);
                            resolve(value);
                        }

                        function onOk() { cleanup(inputEl.value); }
                        function onCancel() { cleanup(null); }
                        function onKeydown(e) {
                            if (e.key === 'Enter') { e.preventDefault(); onOk(); }
                            if (e.key === 'Escape') { e.preventDefault(); onCancel(); }
                        }

                        okBtn.addEventListener('click', onOk);
                        cancelBtn.addEventListener('click', onCancel);
                        modal.addEventListener('keydown', onKeydown);

                        function onOverlayClick(e) {
                            if (e.target === modal) onCancel();
                        }
                        modal.addEventListener('click', onOverlayClick);
                    });
                },
                updateCountdownDisplay: function(expiryTimestamp, countdownElId, statusDotElId) {
                    const countdownElement = document.getElementById(countdownElId);
                    const statusDotElement = document.getElementById(statusDotElId);
                    if (!countdownElement) return null;

                    const now = Math.floor(Date.now() / 1000);
                    const remainingSeconds = expiryTimestamp - now;

                    if (remainingSeconds > 0) {
                        countdownElement.textContent = this.formatRemainingTime(remainingSeconds);
                        countdownElement.className = "user-status-text user-status-text-active";
                        if (statusDotElement) statusDotElement.className = "status-dot status-dot-active";
                    } else {
                        countdownElement.textContent = "Access Expired";
                        countdownElement.className = "user-status-text user-status-text-expired";
                        if (statusDotElement) statusDotElement.className = "status-dot status-dot-expired";
                        return false;
                    }
                    return true;
                },

                startCodeAccessCountdown: function() {
                    if (this.isUserLoggedIn && typeof this.codeAccessExpiryTimestamp === "number") {
                        const expiryTimestamp = this.codeAccessExpiryTimestamp;

                        if (this.updateCountdownDisplay(expiryTimestamp, "codeAccessCountdown", "statusDot") !== null) {
                            if (expiryTimestamp > Math.floor(Date.now() / 1000)) {
                                if (this.countdownIntervalId) clearInterval(this.countdownIntervalId);
                                this.countdownIntervalId = setInterval(() => {
                                    if (!this.updateCountdownDisplay(expiryTimestamp, "codeAccessCountdown", "statusDot")) {
                                        clearInterval(this.countdownIntervalId);
                                    }
                                }, 1000);
                            }
                        }

                        if (document.getElementById("codeAccessCountdownMobile")) {
                            if (this.updateCountdownDisplay(expiryTimestamp, "codeAccessCountdownMobile", "statusDotMobile") !== null) {
                                if (expiryTimestamp > Math.floor(Date.now() / 1000)) {
                                    if (this.countdownIntervalIdMobile) clearInterval(this.countdownIntervalIdMobile);
                                    this.countdownIntervalIdMobile = setInterval(() => {
                                        if (!this.updateCountdownDisplay(expiryTimestamp, "codeAccessCountdownMobile", "statusDotMobile")) {
                                            clearInterval(this.countdownIntervalIdMobile);
                                        }
                                    }, 1000);
                                }
                            }
                        }
                    }
                },

                updateStats: function(content) {
                    const lines = content.split("\n").length;
                    const chars = content.length;
                    const lineCountEl = document.getElementById("lineCount");
                    const charCountEl = document.getElementById("charCount");
                    if (lineCountEl) lineCountEl.textContent = String(lines);
                    if (charCountEl) charCountEl.textContent = String(chars);
                },

                updatePreview: function(content) {
                    const frame = document.getElementById("previewFrame");
                    if (frame) frame.srcdoc = content;
                },

                showTypingIndicator: function() {
                    const indicator = document.getElementById("typingIndicator");
                    if (indicator) indicator.classList.remove("hidden");
                },

                hideTypingIndicator: function() {
                    const indicator = document.getElementById("typingIndicator");
                    if (indicator) indicator.classList.add("hidden");
                },

                sendStop: function() {
                    if (this.abortController) {
                        this.abortController.abort();
                        console.log("Abort signal sent.");
                    }
                },

                setGeneratingState: function(active) {
                    const sendBtn = document.getElementById("send");
                    if (!sendBtn) return;
                    const sendText = sendBtn.querySelector("span");
                    let iconElement = sendBtn.querySelector("i");
                    if (active) {
                        this.isGenerating = true;
                        this.abortController = new AbortController();
                        sendBtn.classList.remove("bg-[var(--primary)]", "hover:bg-[var(--primary-hover)]");
                        sendBtn.classList.add("bg-red-600", "hover:bg-red-700");
                        if (iconElement) {
                            iconElement.classList.remove("fa-paper-plane");
                            iconElement.classList.add("fa-stop");
                        }
                        if (sendText) sendText.textContent = "Stop";
                    } else {
                        this.isGenerating = false;
                        sendBtn.classList.remove("bg-red-600", "hover:bg-red-700");
                        sendBtn.classList.add("bg-[var(--primary)]", "hover:bg-[var(--primary-hover)]");
                        if (iconElement) {
                            iconElement.classList.remove("fa-stop");
                            iconElement.classList.add("fa-paper-plane");
                        }
                        if (sendText) sendText.textContent = "Generate";
                    }
                    sendBtn.disabled = false;
                    sendBtn.classList.remove("opacity-50");
                },

                sendPrompt: async function() {
                    const promptBox = document.getElementById("prompt");
                    if (!promptBox) { alert("Prompt box not found!"); return; }
                    const userInstruction = promptBox.value.trim();
                    if (!userInstruction) { alert("Please enter a prompt"); return; }
                    if (!this.monacoEditor) { alert("Editor not ready"); return; }

                    // Prompt limit gate for non-paid users
                    if (!window.CODE_IS_PAID && window.CODE_PROMPTS_REMAINING <= 0) {
                        this.showCodeUpgradeModal();
                        return;
                    }

                    const currentHtmlContent = this.monacoEditor.getValue();
                    const theUserID = this.appUsername || "guest";
                    if (theUserID === "guest") console.warn("Using 'guest' as userID for API call.");

                    this.setGeneratingState(true);
                    this.showTypingIndicator();

                    try {
                        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
                        const csrfHeader = metaCsrf ? metaCsrf.getAttribute('content') : '';
                        const response = await fetch("/api", {
                            method: "POST",
                            headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfHeader },
                            body: JSON.stringify({ prompt: userInstruction, html: currentHtmlContent, userID: theUserID }),
                            signal: this.abortController.signal,
                        });

                        if (!response.ok) {
                            if (this.abortController && this.abortController.signal.aborted) return;
                            const errorDataText = await response.text();
                            const previewFrame = document.getElementById("previewFrame");
                            if (response.headers.get("Content-Type")?.includes("text/html")) {
                                if (errorDataText.includes("SHOW_PREMIUM_MODAL") || errorDataText.toLowerCase().includes("premium") || errorDataText.includes("triggerParentModalHTML")) {
                                    this.showPremiumModal();
                                    if (promptBox) promptBox.value = "Daily limit reached or premium feature accessed.";
                                } else {
                                    if (previewFrame) previewFrame.srcdoc = errorDataText;
                                    else alert(`Server Error: ${response.status}.`);
                                }
                                return;
                            } else {
                                throw new Error(`API Error: ${response.status} ${response.statusText}. ${errorDataText.substring(0, 200)}`);
                            }
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder("utf-8");
                        let result = "";
                        if (this.monacoEditor) this.monacoEditor.setValue("");

                        while (true) {
                            if (this.abortController && this.abortController.signal.aborted) break;
                            const { done, value } = await reader.read();
                            if (done) break;
                            if (this.abortController && this.abortController.signal.aborted) break;
                            const chunk = decoder.decode(value, { stream: true });
                            result += chunk;
                            if (this.monacoEditor) this.monacoEditor.setValue(result);
                        }
                        this.updatePreview(result);
                        if (!(this.abortController && this.abortController.signal.aborted)) {
                            if (promptBox) promptBox.value = "";
                            // Consume one prompt for non-paid users and update counter
                            if (!window.CODE_IS_PAID) {
                                window.CODE_PROMPTS_REMAINING = Math.max(0, (window.CODE_PROMPTS_REMAINING || 0) - 1);
                                window.CODE_PROMPTS_USED = (window.CODE_PROMPTS_USED || 0) + 1;
                                this._syncCodePromptCount();
                                this._updatePromptCounter();
                            }
                        }
                    } catch (error) {
                        if (error.name === "AbortError") {
                            console.log("Fetch aborted by user:", error.message);
                        } else {
                            console.error("Error during AI generation:", error);
                            if (!(this.abortController && this.abortController.signal.aborted)) {
                                alert(`Failed to generate code: ${error.message}.`);
                            }
                        }
                    } finally {
                        if (!(this.abortController && this.abortController.signal.aborted && this.isGenerating === false)) {
                            this.setGeneratingState(false);
                        }
                        this.hideTypingIndicator();
                    }
                },

                _setupSplashscreen: function() {
                    setTimeout(() => {
                        const splash = document.querySelector(".splash-screen");
                        if (splash) {
                            splash.style.opacity = "0";
                            setTimeout(() => { splash.style.display = "none"; }, 500);
                        }
                    }, 1000);
                },

                _setupMonacoEditor: function() {
                    const self = this;
                    require.config({ paths: { vs: "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs" } });
                    require(["vs/editor/editor.main"], function () {
                        self.monacoEditor = monaco.editor.create(document.getElementById("editor"), {
                            value: `<!DOCTYPE html>\n<html lang="en">\n<head>\n  <meta charset="UTF-8">\n  <meta name="viewport" content="width=device-width, initial-scale=1.0">\n  <title>Prayer for Cerebras Support</title>\n  <script src="https://cdn.tailwindcss.com"><\/script>\n  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">\n  <style>\n    .gradient-text { background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899); -webkit-background-clip: text; background-clip: text; color: transparent; }\n    .pulse { animation: pulse 2s infinite; }\n    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }\n  </style>\n</head>\n<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-200 font-['Poppins']">\n  <div class="max-w-4xl mx-auto px-4 py-20">\n    <div class="bg-white rounded-2xl shadow-xl overflow-hidden pulse">\n      <div class="p-10 text-center">\n        <div class="mb-8">\n          <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />\n          </svg>\n        </div>\n        <h1 class="text-4xl md:text-5xl font-bold mb-6 gradient-text">Hello Isaac Tal</h1>\n        <p class="text-xl text-gray-600 mb-8">It's Bob here, we're praying for</p>\n        <div class="inline-block px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full shadow-lg">\n          <span class="text-2xl font-bold text-white">Cerebras Support!</span>\n        </div>\n        <div class="mt-12">\n          <div class="flex justify-center space-x-4">\n            <div class="w-3 h-3 rounded-full bg-blue-400 animate-bounce"></div>\n            <div class="w-3 h-3 rounded-full bg-purple-400 animate-bounce" style="animation-delay: 0.2s"></div>\n            <div class="w-3 h-3 rounded-full bg-pink-400 animate-bounce" style="animation-delay: 0.4s"></div>\n          </div>\n        </div>\n      </div>\n    </div>\n    <div class="mt-10 text-center text-gray-500">\n      <p>May 22, 2025 - Sending positive energy your way</p>\n    </div>\n  </div>\n</body>\n</html>`,
                            language: "html",
                            theme: "vs-dark",
                            fontSize: 14,
                            wordWrap: "on",
                            minimap: { enabled: true },
                            scrollBeyondLastLine: false,
                            automaticLayout: true,
                        });
                        self.updatePreview(self.monacoEditor.getValue());
                        self.updateStats(self.monacoEditor.getValue());
                        self.monacoEditor.onDidChangeModelContent(() => {
                            const value = self.monacoEditor.getValue();
                            self.updatePreview(value);
                            self.updateStats(value);
                        });
                        setTimeout(() => {
                            if (self.monacoEditor) self.updateStats(self.monacoEditor.getValue());
                        }, 500);
                    });
                },

                _setupMobileMenu: function() {
                    const mobileMenuButton = document.getElementById('mobileMenuButton');
                    const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
                    const htmlEl = document.documentElement;

                    if (mobileMenuButton && mobileMenuDrawer) {
                        mobileMenuButton.addEventListener('click', () => {
                            const isMenuOpening = mobileMenuDrawer.classList.contains('hidden');
                            mobileMenuDrawer.classList.toggle('hidden');
                            htmlEl.classList.toggle('mobile-menu-open', isMenuOpening);
                            mobileMenuButton.setAttribute('aria-expanded', String(isMenuOpening));
                            mobileMenuDrawer.setAttribute('aria-hidden', String(!isMenuOpening));
                            if (isMenuOpening) this.startCodeAccessCountdown();
                        });
                        mobileMenuDrawer.addEventListener('click', (event) => {
                            if (event.target.closest('button') || event.target.closest('a')) {
                                mobileMenuDrawer.classList.add('hidden');
                                htmlEl.classList.remove('mobile-menu-open');
                                mobileMenuButton.setAttribute('aria-expanded', 'false');
                                mobileMenuDrawer.setAttribute('aria-hidden', 'true');
                            }
                        });
                    }
                    const runBtnMobile = document.getElementById('runBtnMobile');
                    if (runBtnMobile) {
                        runBtnMobile.addEventListener('click', () => {
                            if (!this.monacoEditor) { alert('Editor not ready'); return; }
                            this.deployToSaiCloud();
                        });
                    }
                },

                _setupUIEventListeners: function() {
                    const sendButton = document.getElementById("send");
                    if (sendButton) {
                        sendButton.addEventListener("click", () => {
                            if (!this.monacoEditor) { alert("Editor not ready"); return; }
                            if (this.isGenerating) this.sendStop(); else this.sendPrompt();
                        });
                    }
                    const promptTextarea = document.getElementById("prompt");
                    if (promptTextarea) {
                        promptTextarea.addEventListener("keydown", (e) => {
                            if (e.key === "Enter" && !e.shiftKey) {
                                e.preventDefault();
                                if (!this.monacoEditor) { alert("Editor not ready"); return; }
                                if (!this.isGenerating) this.sendPrompt();
                            }
                        });
                    }
                    const clearPromptButton = document.getElementById("clearPrompt");
                    if (clearPromptButton) {
                        clearPromptButton.addEventListener("click", () => {
                            if (promptTextarea) promptTextarea.value = "";
                        });
                    }
                    const refreshPreviewButton = document.getElementById("refreshPreview");
                    if (refreshPreviewButton) {
                        refreshPreviewButton.addEventListener("click", () => {
                            if (this.monacoEditor) this.updatePreview(this.monacoEditor.getValue());
                        });
                    }
                    const runCloudButton = document.getElementById('runBtn');
                    if (runCloudButton) {
                        runCloudButton.addEventListener('click', () => {
                            if (!this.monacoEditor) { alert('Editor not ready'); return; }
                            this.deployToSaiCloud();
                        });
                    }

                    // Reload full page button: refresh entire page
                    const reloadFullButton = document.getElementById("reloadPreviewFull");
                    if (reloadFullButton) {
                        reloadFullButton.addEventListener("click", () => {
                            try { window.location.reload(); } catch (e) { console.error('reloadPreviewFull failed', e); }
                        });
                    }

                    // Toggle code/editor view (eye button)
                    const toggleCodeBtn = document.getElementById('toggleCodeViewBtn');
                    if (toggleCodeBtn) {
                        toggleCodeBtn.addEventListener('click', function (e) {
                            try {
                                const leftPane = document.getElementById('leftPane');
                                const dragbar = document.getElementById('dragbar');
                                const rightPane = document.getElementById('rightPane');
                                const headerEl = document.querySelector('header');
                                if (!leftPane || !rightPane) return;
                                const currentlyHidden = (leftPane.style.display === 'none' || leftPane.classList.contains('hidden'));
                                if (currentlyHidden) {
                                    // show code/editor
                                    leftPane.style.display = '';
                                    if (dragbar) dragbar.style.display = '';
                                    this.setAttribute('aria-pressed', 'false');
                                    this.title = 'Hide code view';
                                    const icon = this.querySelector('i'); if (icon) { icon.classList.add('fa-code'); icon.classList.remove('opacity-50'); }
                                    // restore rightPane height on small screens
                                    rightPane.style.height = '';
                                } else {
                                    // hide code/editor
                                    leftPane.style.display = 'none';
                                    if (dragbar) dragbar.style.display = 'none';
                                    this.setAttribute('aria-pressed', 'true');
                                    this.title = 'Show code view';
                                    const icon = this.querySelector('i'); if (icon) { icon.classList.add('fa-code'); icon.classList.add('opacity-50'); }
                                    // On small screens, let the preview fill the remaining viewport to avoid a black band
                                    if (window.innerWidth < 640) {
                                        var headerH = (headerEl && headerEl.offsetHeight) ? headerEl.offsetHeight : 0;
                                        // subtract header height so preview doesn't go under header
                                        rightPane.style.height = 'calc(100vh - ' + headerH + 'px)';
                                    }
                                }
                                // If Monaco editor exists, trigger layout to ensure preview fills space correctly
                                try { if (window.SaiChatApp && window.SaiChatApp.monacoEditor) window.SaiChatApp.monacoEditor.layout(); } catch (e) {}
                            } catch (err) { console.error('toggleCodeViewBtn error', err); }
                        });
                    }

                    // Ensure actionButton is hidden whenever the composer is visible.
                    // If composer is hidden, show the action button. Observe attribute/class/style changes on composer.
                    const actionBtn = document.getElementById("actionButton");
                    const composerEl = document.getElementById("composerFooter");
                    if (actionBtn && composerEl) {
                        try {
                            const composerVisible = (getComputedStyle(composerEl).display !== 'none');
                            if (composerVisible) actionBtn.classList.add('hidden'); else actionBtn.classList.remove('hidden');
                        } catch (e) { /* ignore */ }

                        try {
                            const observer = new MutationObserver(function() {
                                try {
                                    const visible = (getComputedStyle(composerEl).display !== 'none');
                                    if (visible) actionBtn.classList.add('hidden'); else actionBtn.classList.remove('hidden');
                                } catch (e) { /* ignore */ }
                            });
                            observer.observe(composerEl, { attributes: true, attributeFilter: ['style', 'class'] });
                        } catch (e) { /* ignore */ }
                    }
                },

                _setupDragbar: function() {
                    const dragbar = document.getElementById("dragbar");
                    const leftPane = document.getElementById("leftPane");
                    const rightPane = document.getElementById("rightPane");
                    const container = document.getElementById("container");
                    const previewFrame = document.getElementById("previewFrame");

                    if (!dragbar || !leftPane || !rightPane || !container || !previewFrame) {
                        console.warn("Dragbar elements not found."); return;
                    }
                    let isDragging = false;
                    const self = this; // Store 'this' for use in nested functions

                    dragbar.addEventListener("mousedown", startDrag);

                    function startDrag(e) {
                        if (window.innerWidth < 640) return; // No drag on mobile
                        e.preventDefault();
                        isDragging = true;
                        previewFrame.style.pointerEvents = "none";
                        rightPane.style.pointerEvents = "none";
                        document.body.style.cursor = "ew-resize";
                        document.addEventListener("mousemove", resizeDuringDrag);
                        document.addEventListener("mouseup", stopDrag);
                    }

                    function resizeDuringDrag(e) {
                        if (!isDragging) return;
                        if (window.innerWidth < 640 || container.style.flexDirection === 'column') {
                            stopDrag(); return;
                        }
                        const containerRect = container.getBoundingClientRect();
                        let newLeftWidth = e.clientX - containerRect.left;
                        const minWidth = 200;
                        const containerWidth = container.offsetWidth;
                        const maxWidth = Math.max(minWidth, containerWidth - 150);
                        if (newLeftWidth < minWidth) newLeftWidth = minWidth;
                        if (newLeftWidth > maxWidth) newLeftWidth = maxWidth;
                        leftPane.style.width = `${newLeftWidth}px`;
                        if (self.monacoEditor) self.monacoEditor.layout();
                    }

                    function stopDrag() {
                        if (!isDragging) return;
                        isDragging = false;
                        previewFrame.style.pointerEvents = "auto";
                        rightPane.style.pointerEvents = "auto";
                        document.body.style.cursor = "auto";
                        document.removeEventListener("mousemove", resizeDuringDrag);
                        document.removeEventListener("mouseup", stopDrag);
                        if (self.monacoEditor && window.innerWidth >= 640) {
                            self.monacoEditor.layout();
                        }
                    }

                    window.addEventListener('resize', () => {
                        if (self.monacoEditor) {
                            setTimeout(() => self.monacoEditor.layout(), 0);
                        }
                        if (isDragging && window.innerWidth < 640) {
                            stopDrag();
                        }
                    });
                },

                showPremiumModal: function() {
                    const premiumModal = document.getElementById("universalPremiumModal");
                    if (!premiumModal) {
                        console.error("Premium modal element not found.");
                        return;
                    }

                    premiumModal.classList.remove("hidden");
                    document.body.style.overflow = "hidden";

                    // Only try to render if the buttons haven't been successfully rendered yet.
                    if (!this.payPalButtonsRendered) {
                        const paypalContainer = document.getElementById("paypal-button-container-P-43S89794RD1094113NAV52CA-modal");

                        // Function to try rendering the buttons
                        const attemptToRender = () => {
                            if (typeof paypal !== "undefined" && paypal.Buttons) {
                                // SDK is ready, render the buttons
                                this._renderPayPalSubscriptionButtonForModal();
                                this.payPalButtonsRendered = true; // Mark as rendered
                                return true; // Success
                            }
                            return false; // SDK not ready
                        };

                        // First, try to render immediately in case the SDK is already loaded.
                        if (attemptToRender()) {
                            return;
                        }

                        // If not ready, show a loading message and start polling.
                        if (paypalContainer) {
                            paypalContainer.innerHTML = '<p class="text-center text-gray-400 dark:text-gray-300">Loading payment options...</p>';
                        }

                        let attempts = 0;
                        const maxAttempts = 20; // Poll for up to 10 seconds (20 * 500ms)
                        const pollInterval = setInterval(() => {
                            attempts++;
                            if (attemptToRender() || attempts >= maxAttempts) {
                                clearInterval(pollInterval);
                                // If it still hasn't rendered after all attempts, show the final error message.
                                if (!this.payPalButtonsRendered && paypalContainer) {
                                    console.error("PayPal SDK failed to load in time.");
                                    paypalContainer.innerHTML = '<p class="text-center text-red-500">PayPal buttons could not load.</p>';
                                }
                            }
                        }, 500);
                    }
                },

                hidePremiumModal: function() {
                    const premiumModal = document.getElementById("universalPremiumModal");
                    if (premiumModal) {
                        premiumModal.classList.add("hidden");
                        document.body.style.overflow = "";
                    }
                },

                _renderPayPalSubscriptionButtonForModal: function() {
                    const paypalContainerModal = document.getElementById("paypal-button-container-P-43S89794RD1094113NAV52CA-modal");
                    if (!paypalContainerModal) {
                        console.error("PayPal button container for modal not found"); return;
                    }
                    paypalContainerModal.innerHTML = "";
                    const currentUserID = this.appUsername || "guest_user_paypal";
                    const self = this;

                    try {
                        paypal.Buttons({
                            style: { shape: 'rect', color: 'gold', layout: 'vertical', label: 'subscribe' },
                            createSubscription: function(data, actions) {
                                return actions.subscription.create({
                                    'plan_id': 'P-43S89794RD1094113NAV52CA',
                                    'custom_id': currentUserID
                                });
                            },
                            onApprove: function(data, actions) {
                                alert("Subscription successful! ID: " + data.subscriptionID);
                                self.hidePremiumModal();
                                // TODO: Update UI/backend
                            },
                            onCancel: function (data) {
                                console.log("Subscription cancelled.", data);
                            },
                            onError: function (err) {
                                console.error("PayPal Subscription Error:", err);
                                alert("An error occurred with your subscription.");
                            }
                        }).render("#paypal-button-container-P-43S89794RD1094113NAV52CA-modal")
                        .catch((err) => {
                             console.error("Error rendering PayPal buttons in modal:", err);
                             if (paypalContainerModal) paypalContainerModal.innerHTML = '<p class="text-center text-red-500">Failed to display PayPal options.</p>';
                        });
                    } catch (error) {
                        console.error("General error setting up PayPal buttons in modal:", error);
                        if (paypalContainerModal) paypalContainerModal.innerHTML = '<p class="text-center text-red-500">Could not render PayPal buttons.</p>';
                    }
                },

                _setupPremiumModal: function() {
                    const premiumModal = document.getElementById("universalPremiumModal");
                    const closeModalButton = document.getElementById("closePremiumModal");

                    if (closeModalButton) {
                        closeModalButton.addEventListener("click", this.hidePremiumModal.bind(this));
                    }
                    if (premiumModal) {
                        premiumModal.addEventListener("click", (event) => {
                            if (event.target === premiumModal) {
                                this.hidePremiumModal();
                            }
                        });
                    }
                    window.addEventListener("message", (event) => {
                        if (event.data && event.data.type === "SHOW_PREMIUM_MODAL") {
                            this.showPremiumModal();
                        }
                    });
                    window.showGlobalPremiumModal = this.showPremiumModal.bind(this);
                    window.hideGlobalPremiumModal = this.hidePremiumModal.bind(this);
                },

                // ── Code Upgrade Modal (P250 / P1000) ──────────────────────
                showCodeUpgradeModal: function() {
                    const el = document.getElementById('code-upgrade-modal');
                    if (el) { el.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
                },

                hideCodeUpgradeModal: function() {
                    const el = document.getElementById('code-upgrade-modal');
                    if (el) { el.classList.add('hidden'); document.body.style.overflow = ''; }
                },

                // POST to server to record prompt consumption (fire-and-forget)
                _syncCodePromptCount: function() {
                    try {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        const csrf = meta ? meta.getAttribute('content') : '';
                        fetch('/api/code/consume-prompt', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                            body: JSON.stringify({ _csrf: csrf }),
                        }).catch(function(){});
                    } catch(e) {}
                },

                // Update the visible prompt counter in the UI
                _updatePromptCounter: function() {
                    const el = document.getElementById('code-prompts-counter');
                    if (!el) return;
                    const rem = window.CODE_PROMPTS_REMAINING || 0;
                    const total = window.CODE_FREE_LIMIT || 2;
                    if (window.CODE_IS_PAID) {
                        el.textContent = '∞ prompts';
                    } else {
                        el.textContent = rem + ' / ' + total + ' free ' + (rem === 1 ? 'prompt' : 'prompts') + ' left';
                        el.classList.toggle('text-red-400', rem === 0);
                        el.classList.toggle('text-yellow-400', rem === 1);
                        el.classList.toggle('text-green-400', rem > 1);
                    }
                },

                init: function() {
                    this.appUsername = window.APP_USERNAME;
                    this.codeAccessExpiryTimestamp = window.CODE_ACCESS_EXPIRY_TIMESTAMP;
                    this.isUserLoggedIn = window.IS_USER_LOGGED_IN;

                    this._setupSplashscreen();
                    this.startCodeAccessCountdown();
                    this._setupMonacoEditor();
                    this._setupUIEventListeners();
                    this._setupDragbar();
                    this._setupPremiumModal();
                    this._setupMobileMenu();

                    // Upgrade modal close button
                    const closeUpgrade = document.getElementById('code-upgrade-modal-close');
                    if (closeUpgrade) closeUpgrade.addEventListener('click', this.hideCodeUpgradeModal.bind(this));
                    document.getElementById('code-upgrade-modal')?.addEventListener('click', (e) => {
                        if (e.target === e.currentTarget) this.hideCodeUpgradeModal();
                    });

                    // Init prompt counter
                    this._updatePromptCounter();
                }
            };

            window.addEventListener("DOMContentLoaded", function() {
                SaiChatApp.init();
            });
        </script>
        <div id="universalInputModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4 z-[120] hidden" aria-labelledby="input-modal-title" role="dialog" aria-modal="true" tabindex="-1">
            <div class="relative w-full max-w-2xl">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden p-6">
                    <div class="mb-3">
                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white uim-message">Input</h3>
                    </div>
                    <div class="mb-4">
                        <input class="uim-input w-full px-4 py-3 border border-gray-200 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-sky-400" placeholder="filename" aria-label="Filename input">
                    </div>
                    <div class="flex justify-end gap-3">
                        <button class="uim-cancel px-5 py-2 bg-sky-100 text-sky-900 rounded-full">Cancel</button>
                        <button class="uim-ok px-5 py-2 bg-sky-700 text-white rounded-full">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Mobile: pin composer to the viewport bottom and respect safe-area-inset */
            @media (max-width: 639px) {
                #composerFooter {
                    position: fixed !important;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 80 !important;
                    margin: 0 0 0 0;
                    border-top-left-radius: 12px;
                    border-top-right-radius: 12px;
                    box-shadow: 0 -8px 30px rgba(0,0,0,0.45);
                    backdrop-filter: blur(6px);
                    padding-bottom: env(safe-area-inset-bottom, 12px);
                }
                /* ensure the editor / scrollable container above composer has enough bottom padding */
                #editorContainer {
                    padding-bottom: calc(var(--composer-height, 160px) + env(safe-area-inset-bottom, 12px));
                }
                /* Fix send (paper-plane) button to viewport corner on mobile */
                #send {
                    position: fixed !important;
                    bottom: 50px !important;
                    right: 25px !important;
                    z-index: 90 !important;
                    margin: 0 !important;
                    transform: none !important;
                }
            }
        </style>

        <style>
            /* Action button: hidden by default to avoid flash on initial load; shown only on small screens when needed.
               Use visibility/opacity and pointer-events so the media query's display:flex doesn't cause a visible flash
               before JS has a chance to control when the button should appear. */
            #actionButton {
                display: none !important;
                align-items: center;
                justify-content: center;
                visibility: hidden;
                opacity: 0;
                transition: opacity 0.18s ease, visibility 0.18s ease;
                pointer-events: none;
            }
            /* Keep layout as flex on small screens, but visibility is controlled by JS to avoid initial flash. */
            @media (max-width: 639px) {
                #actionButton { display: flex !important; }
            }
        </style>

                <!-- Floating action button shown when composer is hidden -->
                <button id="actionButton" aria-label="Open composer" title="Open composer" class="hidden" style="position:absolute; right:25px; bottom:50px; z-index:110; width:52px; height:52px; border-radius:999px; background:var(--primary); color:white; box-shadow:0 8px 24px rgba(0,0,0,0.25); border:none;">
                    <i class="fas fa-comments"></i>
                </button>
        <script>
            // Measure composer height at runtime and publish to CSS variable so content isn't hidden.
            (function(){
                var initialViewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                var lastKeyboardOffset = 0;

                function updateComposerHeight(){
                    var el = document.getElementById('composerFooter');
                    if (!el) return;
                    var h = el.offsetHeight || 160;
                    document.documentElement.style.setProperty('--composer-height', h + 'px');
                    // If keyboard is open, also ensure composer is positioned above it
                    adjustComposerForKeyboard();
                }

                function adjustComposerForKeyboard(){
                    var el = document.getElementById('composerFooter');
                    if (!el) return;
                    var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                    var diff = Math.max(0, initialViewportHeight - viewportHeight);
                    // treat small differences as no keyboard (tolerance)
                    if (diff < 120) diff = 0;
                    if (diff !== lastKeyboardOffset) {
                        lastKeyboardOffset = diff;
                        if (diff > 0) {
                            // move composer above keyboard
                            el.style.bottom = diff + 'px';
                            // also ensure the editor container has enough padding
                            document.documentElement.style.setProperty('--composer-keyboard-offset', diff + 'px');
                        } else {
                            el.style.bottom = '0px';
                            document.documentElement.style.removeProperty('--composer-keyboard-offset');
                        }
                    }
                }

                // Focus handling: when inputs inside composer gain focus, scroll into view
                function onComposerInputFocus(e){
                    var el = document.getElementById('composerFooter');
                    if (!el) return;
                    setTimeout(function(){
                        try { el.scrollIntoView({behavior:'smooth', block: 'end'}); } catch(_){}
                        adjustComposerForKeyboard();
                    }, 50);
                }

                window.addEventListener('load', updateComposerHeight);
                // track viewport changes (visualViewport more accurate for virtual keyboard)
                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', function(){ setTimeout(function(){ updateComposerHeight(); }, 60); });
                    window.visualViewport.addEventListener('scroll', function(){ setTimeout(function(){ updateComposerHeight(); }, 60); });
                } else {
                    window.addEventListener('resize', function(){ setTimeout(updateComposerHeight, 120); });
                }

                // in case dynamic content changes height (e.g., keyboard open), monitor mutations
                var obs = new MutationObserver(function(){ updateComposerHeight(); });
                var el = document.getElementById('composerFooter');
                if (el) {
                    obs.observe(el, { childList:true, subtree:true, characterData:true });
                    // attach focus listeners to inputs inside composer to bring it into view
                    var inputs = el.querySelectorAll('textarea, input, [contenteditable="true"]');
                    inputs.forEach(function(i){ i.addEventListener('focus', onComposerInputFocus); });
                }
                // Also listen globally for focus events (for dynamically added inputs)
                document.addEventListener('focusin', function(ev){
                    var target = ev.target;
                    if (!target) return;
                    if (target.closest && target.closest('#composerFooter')) {
                        onComposerInputFocus();
                    }
                });

                // Recompute initial viewport height when orientation changes or on resize without keyboard
                window.addEventListener('orientationchange', function(){ setTimeout(function(){ initialViewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight; updateComposerHeight(); }, 200); });
            })();

        // Hide/show composer and action button behavior (mobile only)
        (function(){
            if (!(window.matchMedia && window.matchMedia('(max-width: 639px)').matches)) return; // only run on small screens

            function getEl(id){ return document.getElementById(id); }
            var composer = getEl('composerFooter');
            var sendBtn = getEl('send');
            var actionBtn = getEl('actionButton');
            var hideBtn = getEl('hideComposerBtn');
            var editorContainer = getEl('editor');
            var promptEl = getEl('prompt');

            function hideComposer(){
                if (composer) composer.style.display = 'none';
                if (sendBtn) sendBtn.style.display = 'none';
                // reduce editor bottom padding so it stretches into composer space
                try { document.documentElement.style.setProperty('--composer-height', '0px'); } catch (e) {}
                if (actionBtn) {
                    actionBtn.classList.remove('hidden');
                    try { actionBtn.style.visibility = 'visible'; actionBtn.style.opacity = '1'; actionBtn.style.pointerEvents = 'auto'; } catch(e){}
                }
            }

            function showComposer(){
                if (composer) composer.style.display = '';
                if (sendBtn) sendBtn.style.display = '';
                if (actionBtn) {
                    actionBtn.classList.add('hidden');
                    try { actionBtn.style.visibility = 'hidden'; actionBtn.style.opacity = '0'; actionBtn.style.pointerEvents = 'none'; } catch(e){}
                }
                if (promptEl) { promptEl.focus(); setTimeout(function(){ try{ promptEl.scrollIntoView({behavior:'smooth', block:'end'}); }catch(e){} },50); }
                // trigger recompute of composer height so editor padding is restored
                try{ var ev = new Event('resize'); window.dispatchEvent(ev); }catch(e){}
            }

            // editor click should hide composer
            if (editorContainer) {
                editorContainer.addEventListener('click', function(){ hideComposer(); });
            }

            // Also hide when Monaco editor gains focus (if present)
            function attachMonacoFocusWatcher(){
                try{
                    if (window.SaiChatApp && window.SaiChatApp.monacoEditor && typeof window.SaiChatApp.monacoEditor.onDidFocusEditorText === 'function'){
                        window.SaiChatApp.monacoEditor.onDidFocusEditorText(function(){ hideComposer(); });
                    }
                }catch(e){}
            }
            attachMonacoFocusWatcher();
            setTimeout(attachMonacoFocusWatcher, 1200);

            if (actionBtn) actionBtn.addEventListener('click', function(e){
                e.preventDefault();
                showComposer();
                // Also restore code view if it was hidden so users can edit again
                try {
                    var leftPane = document.getElementById('leftPane');
                    var dragbar = document.getElementById('dragbar');
                    if (leftPane) leftPane.style.display = '';
                    if (dragbar) dragbar.style.display = '';
                    // update the toolbar toggle button state/icon
                    var toggleBtn = document.getElementById('toggleCodeViewBtn');
                    if (toggleBtn) {
                        toggleBtn.setAttribute('aria-pressed', 'false');
                        toggleBtn.title = 'Hide code view';
                        var icon = toggleBtn.querySelector('i');
                        if (icon) { icon.classList.remove('opacity-50'); }
                    }
                    // trigger layout for Monaco if present
                    if (window.SaiChatApp && window.SaiChatApp.monacoEditor) {
                        try { window.SaiChatApp.monacoEditor.layout(); } catch(_){ }
                    }
                } catch (err) { /* ignore */ }
            });
            if (hideBtn) hideBtn.addEventListener('click', function(e){ e.preventDefault(); hideComposer(); });

            // Ensure initial visibility: composer visible, action hidden
            if (actionBtn) {
                actionBtn.classList.add('hidden');
                try { actionBtn.style.visibility = 'hidden'; actionBtn.style.opacity = '0'; actionBtn.style.pointerEvents = 'none'; } catch(e){}
            }
        })();

            // Pull-to-refresh removed. Use the reload button in the preview toolbar instead.
        </script>

        <!-- ── Code Upgrade Modal (P250 / P1000) ──────────────────────────── -->
        <div id="code-upgrade-modal" class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-[120] hidden" role="dialog" aria-modal="true" aria-labelledby="code-upgrade-title">
            <div class="relative max-w-2xl w-full bg-gray-900 rounded-2xl shadow-2xl overflow-hidden border border-gray-700">
                <!-- Close button -->
                <button id="code-upgrade-modal-close" class="absolute top-3 right-3 text-gray-400 hover:text-white z-10 p-1" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <!-- Header -->
                <div class="bg-gradient-to-r from-violet-700 to-indigo-600 px-6 py-5 text-white">
                    <div class="flex items-center gap-3 mb-1">
                        <i class="fas fa-rocket text-2xl"></i>
                        <h2 id="code-upgrade-title" class="text-xl font-bold">Upgrade to Keep Building</h2>
                    </div>
                    <p class="text-sm text-indigo-200">You've used all your free AI code-generation prompts this month. Choose a plan to continue.</p>
                </div>

                <!-- Plan cards -->
                <div class="p-6 grid sm:grid-cols-2 gap-4">
                    <!-- P250 Plan -->
                    <div class="bg-gray-800 border border-gray-600 rounded-xl p-5 flex flex-col gap-3 hover:border-violet-500 transition-colors">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-white">₱250 <span class="text-xs font-normal text-gray-400">/ month</span></span>
                            <span class="text-xs bg-violet-700 text-violet-100 px-2 py-0.5 rounded-full font-medium">Code</span>
                        </div>
                        <ul class="text-sm text-gray-300 space-y-1.5">
                            <li class="flex items-center gap-2"><i class="fas fa-check text-violet-400 w-4"></i> 50 AI code-generation prompts / month</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-violet-400 w-4"></i> Full in-browser HTML/CSS/JS editor</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-violet-400 w-4"></i> Save & export your projects</li>
                            <li class="flex items-center gap-2 text-gray-500"><i class="fas fa-times w-4"></i> No public subdomain</li>
                        </ul>
                        <a href="/register?plan=code_250" class="mt-auto block text-center bg-violet-600 hover:bg-violet-700 text-white font-medium py-2 rounded-lg transition-colors text-sm">Get ₱250 Plan</a>
                    </div>

                    <!-- P1000 Plan -->
                    <div class="bg-gray-800 border border-indigo-500 rounded-xl p-5 flex flex-col gap-3 relative">
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-xs bg-indigo-500 text-white px-3 py-0.5 rounded-full font-semibold shadow">Most Popular</span>
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-white">₱1,000 <span class="text-xs font-normal text-gray-400">/ month</span></span>
                            <span class="text-xs bg-indigo-700 text-indigo-100 px-2 py-0.5 rounded-full font-medium">Code + Domain</span>
                        </div>
                        <ul class="text-sm text-gray-300 space-y-1.5">
                            <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-400 w-4"></i> 50 AI code-generation prompts / month</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-400 w-4"></i> Full in-browser HTML/CSS/JS editor</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-400 w-4"></i> Save & export your projects</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-400 w-4"></i> <strong class="text-white">1 free public subdomain</strong> (e.g. yoursite.silverqueen.pro)</li>
                            <li class="flex items-center gap-2 text-xs text-indigo-300"><i class="fas fa-info-circle w-4"></i> Host &amp; share your sites instantly</li>
                        </ul>
                        <a href="/register?plan=code_1000" class="mt-auto block text-center bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg transition-colors text-sm">Get ₱1,000 Plan</a>
                    </div>
                </div>

                <p class="text-xs text-gray-500 text-center pb-4">Plans renew monthly. Cancel anytime. <a href="/privacy" class="text-violet-400 hover:underline">Terms apply</a>.</p>
            </div>
        </div>

        <div id="universalPremiumModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center p-4 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="max-w-3xl w-full bg-white dark:bg-slate-800 rounded-xl shadow-lg overflow-hidden transform transition-all sm:my-8 animate-fade-in">
                <button id="closePremiumModal" class="absolute top-4 right-4 text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 z-[101]">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="md:flex">
                    <div class="md:w-2/5 bg-gradient-to-br from-purple-600 to-purple-500 p-8 text-white flex flex-col justify-center relative">
                        <div class="flex items-center mb-6"> <i class="fas fa-crown text-4xl mr-4"></i> <div> <h1 class="text-3xl font-bold">Go Premium!</h1> <p class="text-purple-200">Unlock Full Code Editor Access</p> </div> </div>
                        <p class="mb-6 text-purple-100">Subscribe now to enjoy unlimited AI power, priority access, and exclusive features. Elevate your experience today!</p>
                        <div class="bg-purple-700 bg-opacity-40 rounded-lg p-4">
                            <h3 class="font-semibold mb-3 text-lg">Premium Benefits:</h3>
                            <ul class="space-y-2 text-sm text-purple-50">
                                <li class="feature-item">Unlimited daily requests</li> <li class="feature-item">Priority AI processing</li> <li class="feature-item">Extended response lengths</li> <li class="feature-item">Advanced customization options</li> <li class="feature-item">Dedicated 24/7 VIP support</li> <li class="feature-item">Early access to new features & models</li>
                            </ul>
                        </div>
                    </div>
                    <div class="md:w-3/5 p-8 flex flex-col justify-center">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 text-center">Subscribe to Premium</h2>
                        <p class="text-gray-600 dark:text-gray-300 text-center mb-6">Gain immediate access to all premium features with our straightforward subscription plan.</p>
                        <div id="paypal-button-container-P-43S89794RD1094113NAV52CA-modal" class="mx-auto w-full max-w-xs"></div>
                        <div class="mt-8 text-xs text-gray-500 dark:text-gray-400 text-center">
                            <div class="flex items-center justify-center text-green-600 dark:text-green-400 mb-2"><i class="fas fa-shield-alt mr-2"></i><span>Secure & Encrypted Payment via PayPal</span></div>
                            <p>By subscribing, you agree to our <a href="/privacy" class="text-purple-500 hover:underline">Terms of Service</a> and <a href="/privacy" class="text-purple-500 hover:underline">Privacy Policy</a>.</p>
                            <p class="mt-1">You can manage or cancel your subscription anytime through your PayPal account.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>