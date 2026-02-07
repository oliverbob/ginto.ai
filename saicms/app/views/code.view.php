<?php
// Start session if not already started - critical for accessing $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (using your existing logic)
$isUserLoggedIn = isset($_SESSION['user']);
$username = $isUserLoggedIn ? $_SESSION['user'] : null;
// Display part of email or full username based on your preference
$userDisplay = $isUserLoggedIn ? (strpos($_SESSION['user'], '@') ? explode('@', $_SESSION['user'])[0] : $_SESSION['user']) : 'Guest';

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
        $initialExpiryStatusMessage = "No active plan";
        // $initialExpiryStatusColorClass remains gray
        // $initialStatusDotClass remains none
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark bg-[var(--bg-dark)] text-white">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <title>Sai Chat Pro</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Font Awesome is already included, which is good! -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
        <script src="https://www.paypal.com/sdk/js?client-id=AZgBWH2d8yEZqBxYOwpQhU_pD8M2R2InPsU80V97EGksIZzw-QzfvWcCbP3J3nKaQ6xQZ3ZkdurydxKo&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>
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
            <div id="leftPane" class="w-full sm:w-[40%] flex flex-col sm:min-w-[200px] bg-[var(--bg-darker)] order-1
                                     h-[55vh] sm:h-full overflow-hidden">
                <div class="flex-1 relative overflow-x-hidden overflow-y-auto" id="editorContainer">
                    <div id="editor"></div>
                    <div id="typingIndicator" class="absolute bottom-4 left-4 bg-gray-800 px-3 py-2 rounded-md text-sm hidden">
                        <span class="text-gray-300">AI is generating...</span>
                        <div class="typing-indicator"> <div class="typing-dot"></div> <div class="typing-dot"></div> <div class="typing-dot"></div> </div>
                    </div>
                </div>
                <div class="p-3 border-t border-[var(--border-dark)] bg-[var(--bg-dark)] flex flex-col gap-2 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-400 uppercase tracking-wider" for="prompt">AI Prompt</label>
                            <div class="animate-nudge-hand">
                                <!-- Using Font Awesome icon for pointing hand -->
                                <i class="fas fa-hand-point-down w-5 h-5 text-[var(--primary)] pointing-hand-indicator"></i>
                            </div>
                            <span class="text-xs text-gray-500 italic hidden sm:inline -ml-1">What to build?</span>
                        </div>
                        <button id="clearPrompt" class="text-xs text-gray-400 hover:text-white"><i class="fas fa-times mr-1"></i>Clear</button>
                    </div>
                    <div class="relative">
                        <textarea
                            id="prompt"
                            placeholder="Ask AI to build UI (e.g. 'Create a responsive navbar with dark mode toggle')"
                            class="w-full bg-gray-800 text-white px-3 py-2 text-sm border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--primary)]"
                        ></textarea>
                        <div class="absolute bottom-2 right-2 text-xs text-gray-500">Shift+Enter for new line</div>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs text-gray-500 flex items-center gap-2">
                            <span class="status-bar"><span id="lineCount">1</span> lines</span><span>•</span><span id="charCount">0</span> chars
                        </div>
                        <button
                            id="send"
                            class="px-4 py-2 bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-sm rounded-md flex items-center gap-2 transition-all focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:ring-opacity-50"
                        >
                            <i class="fas fa-paper-plane"></i><span>Generate</span>
                        </button>
                    </div>
                </div>
            </div>
            <div id="dragbar" class="order-2"></div>
            <div id="rightPane" class="w-full sm:flex-1 bg-white relative pt-10
                                      h-[45vh] sm:h-full order-3 overflow-y-auto">
                <div id="previewToolbar" class="absolute top-0 left-0 right-0 h-10 bg-gray-100 border-b flex items-center px-3 gap-2 z-10 flex-shrink-0">
                    <button id="refreshPreview" class="p-1 text-gray-600 hover:text-gray-900"><i class="fas fa-sync-alt text-sm"></i></button>
                    <div class="text-xs text-gray-600">Preview</div>
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
                    const currentHtmlContent = this.monacoEditor.getValue();
                    const theUserID = this.appUsername || "guest";
                    if (theUserID === "guest") console.warn("Using 'guest' as userID for API call.");

                    this.setGeneratingState(true);
                    this.showTypingIndicator();

                    try {
                        const response = await fetch("/api", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
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
                            alert("Deploy on Sai Cloud: This feature is not yet implemented.");
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
                    const runCloudButton = document.getElementById("runBtn");
                    if (runCloudButton) {
                        runCloudButton.addEventListener("click", () => {
                            alert("Deploy on Sai Cloud: This feature is not yet implemented.");
                        });
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
                }
            };

            window.addEventListener("DOMContentLoaded", function() {
                SaiChatApp.init();
            });
        </script>

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
                            <p>By subscribing, you agree to our <a href="#" class="text-purple-500 hover:underline">Terms of Service</a> and <a href="#" class="text-purple-500 hover:underline">Privacy Policy</a>.</p>
                            <p class="mt-1">You can manage or cancel your subscription anytime through your PayPal account.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>