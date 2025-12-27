<?php

// Ensure session is active for CSRF validation
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// CSRF validation for POST requests
// Allow bypass via .env CSRF_BYPASS=true or session flag in development
$csrfValid = false;
$envCsrfBypass = filter_var(getenv('CSRF_BYPASS') ?: ($_ENV['CSRF_BYPASS'] ?? false), FILTER_VALIDATE_BOOLEAN);
$devCsrfBypass = !empty($_SESSION['dev_csrf_bypass']) && ($_ENV['APP_ENV'] ?? 'production') !== 'production';

if ($envCsrfBypass || $devCsrfBypass) {
    $csrfValid = true; // CSRF bypass enabled via .env or dev session
} else {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        $csrfValid = true;
    }
}

if (!$csrfValid) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid or missing CSRF token']);
    exit;
}

// --- Visitor Prompt Limit (configurable via config/chat.json) ---
// Logged-in users bypass this limit
$isLoggedInUser = !empty($_SESSION['user_id']);
if (!$isLoggedInUser) {
    $currentHour = date('Y-m-d-H');
    $visitorLimitKey = 'visitor_prompts_' . $currentHour;
    $visitorPromptCount = (int)($_SESSION[$visitorLimitKey] ?? 0);
    $visitorMaxPrompts = (int)\Ginto\Helpers\ChatConfig::get('visitor.maxPromptsPerHour', 5);
    
    if ($visitorPromptCount >= $visitorMaxPrompts) {
        // Return SSE-formatted response for visitor limit
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        echo str_repeat(' ', 1024);
        flush();
        echo "data: " . json_encode([
            'error' => true,
            'action' => 'register',
            'text' => "You've reached the free limit of {$visitorMaxPrompts} messages per hour. Create a free account to continue chatting with Ginto!",
            'prompts_used' => $visitorPromptCount,
            'prompts_limit' => $visitorMaxPrompts,
            'register_url' => '/register'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        echo "data: " . json_encode([
            'final' => true, 
            'action' => 'register',
            'html' => '<div class="text-amber-400"><p>You\'ve used all ' . $visitorMaxPrompts . ' free messages this hour.</p><p class="mt-2"><a href="/register" class="text-indigo-400 hover:text-indigo-300 underline font-semibold">Create a free account</a> to continue chatting!</p></div>'
        ]) . "\n\n";
        flush();
        exit;
    }
    
    // Increment visitor prompt count
    $_SESSION[$visitorLimitKey] = $visitorPromptCount + 1;
    
    // Clean up old hourly keys (keep session tidy)
    foreach ($_SESSION as $key => $val) {
        if (str_starts_with($key, 'visitor_prompts_') && $key !== $visitorLimitKey) {
            unset($_SESSION[$key]);
        }
    }
}

// --- Rate Limiting & Provider Selection ---
// Initialize rate limiting service and determine which provider to use
$rateLimitService = new \App\Core\RateLimitService($db ?? null);
$userIdSession = $_SESSION['user_id'] ?? null;
$userId = $userIdSession ?? ($_SESSION['sandbox_id'] ?? session_id());

// Determine user role - check multiple session keys for admin status
$isAdminUser = !empty($_SESSION['is_admin']) 
    || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin')
    || (!empty($_SESSION['user_role']) && strtolower((string)$_SESSION['user_role']) === 'admin');
$userRole = !empty($_SESSION['user_id']) ? ($isAdminUser ? 'admin' : 'user') : 'visitor';

$visitorIp = $userIdSession === null ? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') : null;

// Get primary provider from environment (default: cerebras for text, groq for vision)
$primaryProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'groq'));
if (!in_array($primaryProvider, ['groq', 'cerebras'], true)) {
    $primaryProvider = 'groq';
}
$defaultModel = 'openai/gpt-oss-120b';

// Initialize per-user rate limiter to protect against hitting provider limits
$userRateLimiter = new \App\Core\UserRateLimiter($db, $primaryProvider);

// Check per-user limits FIRST (protects us from hitting provider billing)
$userLimitCheck = $userRateLimiter->checkLimit(
    $userIdSession ? (int)$userIdSession : null,
    $visitorIp,
    $userRole
);
if (!$userLimitCheck['allowed']) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    echo str_repeat(' ', 1024);
    flush();
    echo "data: " . json_encode([
        'error' => true,
        'text' => $userLimitCheck['message'],
        'reason' => $userLimitCheck['reason'],
        'usage' => $userLimitCheck['usage'],
        'limits' => $userLimitCheck['limits'],
        'retry_after' => $userLimitCheck['retry_after'] ?? 60,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    echo "data: " . json_encode(['final' => true, 'html' => '<p class="text-amber-500">' . htmlspecialchars($userLimitCheck['message']) . '</p>']) . "\n\n";
    flush();
    exit;
}

// Also check provider-level limits (for fallback selection)
$providerLimitCheck = $rateLimitService->canMakeRequest($userId, $userRole, $primaryProvider, $defaultModel);
if (!$providerLimitCheck['allowed']) {
    header('Content-Type: application/json');
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit exceeded',
        'reason' => $providerLimitCheck['reason'],
        'limit' => $providerLimitCheck['limit'],
        'current' => $providerLimitCheck['current'],
        'retry_after' => $providerLimitCheck['retry_after'] ?? 60,
    ]);
    exit;
}

// Select provider (may fallback to cerebras if org limits approached)
$providerSelection = $rateLimitService->selectProvider($primaryProvider, $defaultModel);
$selectedProvider = $providerSelection['provider'];
$usingFallback = $providerSelection['is_fallback'] ?? false;
$requestStartTime = microtime(true);

// Read the incoming prompt
$prompt = $_POST['prompt'] ?? trim(file_get_contents('php://input')) ?: 'Hello, how can you help me today?';
$lower = mb_strtolower($prompt);

// Handle explicit repository description requests (fast path, no LLM needed)
$describeKeywords = ['describe this repo', 'about this repo', 'about this repository'];
$shouldDescribe = false;
foreach ($describeKeywords as $kw) {
    if (strpos($lower, $kw) !== false) { $shouldDescribe = true; break; }
}
if (!$shouldDescribe && isset($_POST['describe_repo'])) {
    $v = strtolower((string)$_POST['describe_repo']);
    if (in_array($v, ['1', 'true', 'yes'], true)) { $shouldDescribe = true; }
}

if ($shouldDescribe) {
    // If this is a sandbox session, do not expose repository-level summaries
    try {
        $dbForSandboxCheck = $db ?? null;
        $editorRootCheck = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($dbForSandboxCheck, $_SESSION ?? null);
        $isSandboxSession = (realpath($editorRootCheck) !== (realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) ?: (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2))));
        if ($isSandboxSession) {
            _chatStreamResponse("Repository description is not available for sandboxed sessions. Please provide admin access or run this request from an admin session.");
            exit;
        }
    } catch (\Throwable $_) {
        // If sandbox detection fails, be conservative and deny the describe action
        _chatStreamResponse("Repository description is not available at this time.");
        exit;
    }

    // Build deterministic repo summary without LLM (admin-only)
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $readme = file_exists($root . '/README.md') ? file_get_contents($root . '/README.md') : '';
    $composer = file_exists($root . '/composer.json') ? @json_decode(file_get_contents($root . '/composer.json'), true) : null;

    // Filter top-level files and avoid exposing VCS or env files
    $rawFiles = @scandir($root) ?: [];
    $skip = ['vendor', 'node_modules', '.git', '.idea', 'storage', '.env'];
    $files = [];
    foreach ($rawFiles as $f) {
        if ($f === '.' || $f === '..') continue;
        if (isset($f[0]) && $f[0] === '.') continue; // hidden files
        if (in_array($f, $skip, true)) continue;
        $files[] = $f;
        if (count($files) >= 40) break;
    }

    $summary = "Repository summary:\n\n";
    if ($composer && !empty($composer['name'])) {
        $summary .= "Package: " . ($composer['name'] ?? '') . "\n";
        if (!empty($composer['description'])) $summary .= "Description: " . substr($composer['description'], 0, 800) . "\n";
        if (!empty($composer['require'])) $summary .= "Requires: " . implode(', ', array_keys($composer['require'])) . "\n\n";
    }
    if ($readme) {
        $summary .= "README (first 2000 chars):\n" . substr($readme, 0, 2000) . "\n\n";
    }
    $summary .= "Top-level files and folders:\n" . implode("\n", $files) . "\n";

    // Stream the response
    _chatStreamResponse($summary);
    exit;
}

// If the current session is using a sandbox, do not enable repository
// description or any behavior that would expose the host repository
// structure. This ensures sandboxed users don't get project-level hints.
try {
    $dbForSandboxCheck = $db ?? null;
    $editorRootCheck = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($dbForSandboxCheck, $_SESSION ?? null);
    $isSandboxSession = (realpath($editorRootCheck) !== (realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) ?: (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2))));
    if ($isSandboxSession) {
        $shouldDescribe = false;
    }
} catch (\Throwable $_) {
    // ignore sandbox detection failures and keep existing behavior
}

// Build conversation history from client-supplied history (no system message - UnifiedMcpClient adds it)
$history = [];
$hadImageInHistory = false;

$historyJson = $_POST['history'] ?? null;
if ($historyJson) {
    $h = json_decode($historyJson, true);
    if (is_array($h)) {
        foreach ($h as $hm) {
            if (!empty($hm['role']) && isset($hm['content'])) {
                // Skip any client-provided system messages - UnifiedMcpClient provides repo context
                if ($hm['role'] === 'system') continue;
                
                // Track if user previously shared an image
                if ($hm['role'] === 'user' && !empty($hm['hasImage'])) {
                    $hadImageInHistory = true;
                    // Add context that user shared an image with their message
                    $history[] = [
                        'role' => 'user', 
                        'content' => '[User shared an image] ' . (string)$hm['content']
                    ];
                } else {
                    $history[] = ['role' => $hm['role'], 'content' => (string)$hm['content']];
                }
            }
        }
    }
}

// Check if this request has an attached image
$hasImage = !empty($_POST['hasImage']) && $_POST['hasImage'] === '1';
$imageDataUrl = $_POST['image'] ?? null;

// =====================================================================
// SESSION-SELECTED PROVIDER (e.g., Ollama from model dropdown)
// =====================================================================
// Check if user has selected a specific provider via the model dropdown.
// This takes priority over the default cloud provider selection.
// =====================================================================
$sessionProvider = $_SESSION['llm_provider_name'] ?? null;
$sessionModel = $_SESSION['llm_model'] ?? null;
$useSessionProvider = false;

if ($sessionProvider && $sessionModel && $sessionProvider === 'ollama') {
    // User selected Ollama - use it directly without cloud API logic
    try {
        // First check if Ollama server is online
        $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
        $checkCtx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $versionCheck = @file_get_contents($ollamaHost . '/api/version', false, $checkCtx);
        
        if ($versionCheck === false) {
            // Ollama server is not running
            @header('Content-Type: text/event-stream; charset=utf-8');
            echo "data: " . json_encode(['error' => 'Ollama server is offline. Please start Ollama with: ollama serve']) . "\n\n";
            exit;
        }
        
        $ollamaProvider = \App\Core\LLM\LLMProviderFactory::create('ollama', [
            'model' => $sessionModel,
        ]);
        
        if ($ollamaProvider->isConfigured()) {
            $useSessionProvider = true;
            
            // Prepare streaming headers
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            while (ob_get_level()) ob_end_flush();
            ignore_user_abort(true);

            if (!headers_sent()) header('Content-Type: text/event-stream; charset=utf-8');
            if (!headers_sent()) header('Cache-Control: no-cache');
            if (!headers_sent()) header('X-Accel-Buffering: no');
            if (!headers_sent()) header('Connection: keep-alive');

            // Send padding to prevent proxy buffering, followed by newlines to separate from data
            echo str_repeat(' ', 1024) . "\n\n";
            flush();

            // Build messages array
            $messages = [];
            $systemPrompt = 'You are Ginto, a helpful AI assistant created by Oliver Bob. Be concise and direct.';
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            
            // Add history
            $historyJson = $_POST['history'] ?? null;
            if ($historyJson) {
                $h = json_decode($historyJson, true);
                if (is_array($h)) {
                    foreach ($h as $hm) {
                        if (!empty($hm['role']) && isset($hm['content']) && $hm['role'] !== 'system') {
                            $messages[] = ['role' => $hm['role'], 'content' => (string)$hm['content']];
                        }
                    }
                }
            }
            
            // Add current prompt
            $messages[] = ['role' => 'user', 'content' => $prompt];
            
            // Stream response from Ollama using chatStream method
            $fullResponse = '';
            $accumulatedReasoning = '';
            $streamError = null;
            $onChunk = function($chunk, $toolCall = null) use (&$fullResponse, &$accumulatedReasoning) {
                // Handle reasoning/thinking events (qwen3 and other reasoning models)
                if ($toolCall !== null && isset($toolCall['reasoning'])) {
                    $reasoningText = $toolCall['text'] ?? '';
                    $accumulatedReasoning .= $reasoningText;
                    echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    return;
                }
                
                // Handle regular content
                if ($chunk !== '' && $chunk !== null) {
                    $fullResponse .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    flush();
                }
            };
            
            try {
                $ollamaProvider->chatStream($messages, [], [], $onChunk);
            } catch (\Throwable $streamEx) {
                $streamError = $streamEx->getMessage();
            }
            
            // If streaming failed and no response received, send error
            if ($streamError && empty($fullResponse)) {
                echo "data: " . json_encode(['error' => 'Model not responding: ' . $streamError]) . "\n\n";
                flush();
                exit;
            }
            
            // Update Ollama status cache (model is now loaded)
            $cacheDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH)) . '/cache';
            $cacheFile = $cacheDir . '/ollama_ps.json';
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($cacheFile, json_encode([
                'models' => [$sessionModel],
                'updated_at' => time(),
                'updated_at_iso' => date('c'),
            ], JSON_PRETTY_PRINT));
            
            // Final message with rendered HTML
            $parsedown = null;
            if (class_exists('\ParsedownExtra')) {
                try { $parsedown = new \ParsedownExtra(); } catch (\Throwable $_) {}
            } elseif (class_exists('\Parsedown')) {
                try { $parsedown = new \Parsedown(); } catch (\Throwable $_) {}
            }
            // Note: setSafeMode(true) strips PHP tags from code blocks, so we disable it
            // if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
            //     try { $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
            // }
            
            // Fix malformed code blocks: ensure newline after language identifier
            // Streaming chunks may produce ```php<?php without newline, breaking Parsedown
            $fixedResponse = preg_replace('/```([a-zA-Z0-9+#]+)(?!\n)/', "```$1\n", $fullResponse);
            
            $html = $parsedown ? $parsedown->text($fixedResponse) : nl2br(htmlspecialchars($fixedResponse));
            
            // Format reasoning as Groq-style timeline HTML with dot + line per item
            $reasoningHtml = '';
            if ($accumulatedReasoning) {
                $createReasoningItem = fn($content) => '<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>' . htmlspecialchars(trim(preg_replace('/\n/', ' ', $content))) . '</p></div></div>';
                
                // Split by sentences or newlines
                $paragraphs = array_filter(preg_split('/\n\n+/', $accumulatedReasoning), fn($p) => trim($p));
                if (count($paragraphs) <= 1) {
                    $paragraphs = array_filter(preg_split('/\n/', $accumulatedReasoning), fn($p) => trim($p));
                }
                if (count($paragraphs) <= 1 && strlen(trim($accumulatedReasoning)) > 100) {
                    $text = preg_replace('/\s+/', ' ', trim($accumulatedReasoning));
                    $parts = preg_split('/([.!?])\s+(?=[A-Z])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $sentences = [];
                    $current = '';
                    foreach ($parts as $part) {
                        if (preg_match('/^[.!?]$/', $part)) {
                            $current .= $part;
                        } else {
                            if ($current) {
                                $sentences[] = trim($current);
                                $current = $part;
                            } else {
                                $current = $part;
                            }
                        }
                    }
                    if (trim($current)) $sentences[] = trim($current);
                    $paragraphs = array_filter($sentences);
                }
                $reasoningHtml = implode('', array_map($createReasoningItem, $paragraphs));
            }
            
            echo "data: " . json_encode([
                'html' => $html,
                'reasoningHtml' => $reasoningHtml,
                'contentEmpty' => empty(trim($fullResponse)),
                'final' => true
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }
    } catch (\Throwable $e) {
        // Ollama failed, fall through to cloud providers
        error_log("Ollama provider failed: " . $e->getMessage());
    }
}

// =====================================================================
// SESSION-SELECTED PROVIDER: Ginto AI (Local)
// =====================================================================
// Check if user has selected the local/ginto provider with "Ginto AI - Default".
// This sets a flag to prefer local LLM; the existing vision logic will
// automatically route to the vision server (port 8033) for image requests
// and reasoning server (port 8034) for text requests.
// =====================================================================
$forceLocalLlm = false;
if ($sessionProvider && $sessionModel && ($sessionProvider === 'local' || $sessionProvider === 'ginto')) {
    if (\App\Core\LLM\Providers\OpenAICompatibleProvider::isGintoDefault($sessionModel)) {
        $forceLocalLlm = true;
    }
}

// =====================================================================
// SESSION-SELECTED CLOUD PROVIDER (Cerebras, Groq, OpenAI, etc.)
// =====================================================================
// If user has explicitly selected a cloud provider from the model dropdown,
// use that provider and model instead of automatic selection.
// =====================================================================
$sessionCloudProvider = null;
$sessionCloudModel = null;
$cloudProviders = ['cerebras', 'groq', 'openai', 'anthropic', 'together', 'fireworks'];
if ($sessionProvider && $sessionModel && in_array($sessionProvider, $cloudProviders, true)) {
    $sessionCloudProvider = $sessionProvider;
    $sessionCloudModel = $sessionModel;
}

// Use OpenAICompatibleProvider with rate-limit-aware provider selection
try {
    // Initialize ProviderKeyManager for multi-key rotation
    $keyManager = new \App\Core\ProviderKeyManager($db);
    $currentKeyId = null;
    $usingFallback = false;
    
    // Detect if this query likely needs web search
    $searchKeywords = [
        'search', 'google', 'find', 'look up', 'lookup', 'what is the latest',
        'current', 'today', 'news', 'recent', 'now', '2024', '2025',
        'price of', 'weather', 'stock', 'how much is', 'who won',
        'what happened', 'when did', 'where is', 'latest version',
        'release date', 'update', 'announced', 'breaking',
        'is it true', 'studies show', 'research', 'health', 'cause', 'effect'
    ];
    $needsWebSearch = false;
    foreach ($searchKeywords as $kw) {
        if (stripos($prompt, $kw) !== false) {
            $needsWebSearch = true;
            break;
        }
    }
    
    // =====================================================================
    // LOCAL VISION MODEL SUPPORT
    // =====================================================================
    // Check if local vision model is available for image requests.
    // Local vision models (SmolVLM2, llava, etc.) can handle images without cloud API.
    // See: src/Core/LLM/LocalLLMConfig.php for configuration details
    // =====================================================================
    $localLlmConfig = \App\Core\LLM\LocalLLMConfig::getInstance();
    $canUseLocalVision = $hasImage && $localLlmConfig->isEnabled() && $localLlmConfig->isVisionServerHealthy();
    
    // Check if request requires a specific provider
    // Vision can now use local model if available, web search still needs Groq
    $requiresGroq = $needsWebSearch; // Only web search requires Groq now
    $requiresCloudVision = $hasImage && !$canUseLocalVision; // Vision needs cloud only if local not available
    
    // =====================================================================
    // SESSION-SELECTED CLOUD PROVIDER OVERRIDE
    // =====================================================================
    // If user explicitly selected a cloud provider from the dropdown, use it.
    // This takes priority over automatic provider selection.
    // Exception: Web search requires Groq (tool calling support needed)
    // =====================================================================
    $selectedProvider = null;
    $apiKey = null;
    
    // Optional logging for debugging provider selection (set ENABLE_LOGGING=true in .env)
    $enableLogging = strtolower(getenv('ENABLE_LOGGING') ?: ($_ENV['ENABLE_LOGGING'] ?? 'false')) === 'true';
    $logFile = null;
    $logMsg = '';
    if ($enableLogging) {
        $logFile = dirname(ROOT_PATH) . '/storage/logs/ginto.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logMsg = "[" . date('Y-m-d H:i:s') . "] Provider Selection Debug:\n";
        $logMsg .= "  sessionProvider: " . ($sessionProvider ?? 'null') . "\n";
        $logMsg .= "  sessionModel: " . ($sessionModel ?? 'null') . "\n";
        $logMsg .= "  sessionCloudProvider: " . ($sessionCloudProvider ?? 'null') . "\n";
        $logMsg .= "  sessionCloudModel: " . ($sessionCloudModel ?? 'null') . "\n";
        $logMsg .= "  requiresGroq: " . ($requiresGroq ? 'true' : 'false') . "\n";
        $logMsg .= "  requiresCloudVision: " . ($requiresCloudVision ? 'true' : 'false') . "\n";
    }
    
    if ($sessionCloudProvider && !$requiresGroq && !$requiresCloudVision) {
        // User explicitly selected a cloud provider - get key from database
        $sessionKeyData = $keyManager->getAvailableKey($sessionCloudProvider);
        if ($sessionKeyData) {
            $apiKey = $sessionKeyData['api_key'];
            $currentKeyId = $sessionKeyData['id'];
            $selectedProvider = $sessionCloudProvider;
            if ($enableLogging) $logMsg .= "  USING SESSION PROVIDER from DB: $selectedProvider (key_id: $currentKeyId)\n";
        } else {
            if ($enableLogging) $logMsg .= "  No DB key found for: $sessionCloudProvider - clearing session selection\n";
            // Clear session cloud provider so we don't use mismatched model
            $sessionCloudProvider = null;
            $sessionCloudModel = null;
        }
    }
    
    // If no session provider override, use automatic selection
    if (!$selectedProvider) {
        // Try to get a key from the database (unified rotation across all providers)
        $keyData = null;
        if ($requiresGroq || $requiresCloudVision) {
            // Must use Groq for web search or cloud vision
            $keyData = $keyManager->getAvailableKey('groq');
        } else {
            // Get first available key from any provider
            $keyData = $keyManager->getFirstAvailableKey();
        }
        
        if ($keyData) {
            $apiKey = $keyData['api_key'];
            $currentKeyId = $keyData['id'];
            $selectedProvider = $keyData['provider'];
        } else {
            // Fallback to environment variables
            if ($requiresGroq) {
                // Must use Groq
                $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
                $selectedProvider = 'groq';
            } else {
                // Try default provider from env, then fallback
                $defaultProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
                $envVarPrimary = ($defaultProvider === 'cerebras') ? 'CEREBRAS_API_KEY' : 'GROQ_API_KEY';
                $envVarFallback = ($defaultProvider === 'cerebras') ? 'GROQ_API_KEY' : 'CEREBRAS_API_KEY';
                
                $apiKey = getenv($envVarPrimary) ?: ($_ENV[$envVarPrimary] ?? '');
                $selectedProvider = $defaultProvider;
                
                if (empty($apiKey)) {
                    $apiKey = getenv($envVarFallback) ?: ($_ENV[$envVarFallback] ?? '');
                    $selectedProvider = ($defaultProvider === 'cerebras') ? 'groq' : 'cerebras';
                    $usingFallback = true;
                }
            }
        }
    }
    
    // =====================================================================
    // LOCAL LLM FALLBACK (REASONING + VISION)
    // =====================================================================
    // If no cloud API key is available, try local LLM as fallback.
    // Local LLMs are fundamentally different from cloud providers:
    // - No API key required (runs on your machine)
    // - No rate limits (you control the hardware)
    // - No costs (free, unlimited usage)
    // - Privacy (data never leaves your machine)
    // See: src/Core/LLM/LocalLLMConfig.php for full documentation
    // =====================================================================
    $useLocalLlm = false;
    $useLocalVision = false;
    
    // Use local LLM for reasoning if:
    // 1. User selected "Ginto AI - Default" from dropdown, OR
    // 2. No cloud API key is available, OR
    // 3. Local LLM is set as primary provider
    // Exception: Web search still needs Groq (requires tool calling)
    if (!$requiresGroq && !$hasImage && ($forceLocalLlm || $localLlmConfig->isPrimary() || empty($apiKey))) {
        if ($localLlmConfig->isEnabled() && $localLlmConfig->isReasoningServerHealthy()) {
            $useLocalLlm = true;
            $selectedProvider = 'local';
            $apiKey = 'local'; // Placeholder - local LLM doesn't need an API key
        }
    }
    
    // Use local vision model for image requests if:
    // 1. Has an image attached, AND
    // 2. Local vision server is healthy, AND
    // 3. Either user selected Ginto AI - Default, no cloud API key, OR local is set as primary
    if ($hasImage && $canUseLocalVision && ($forceLocalLlm || $localLlmConfig->isPrimary() || empty($apiKey))) {
        $useLocalVision = true;
        $useLocalLlm = true; // Vision also uses local provider
        $selectedProvider = 'local';
        $apiKey = 'local';
    }
    
    if (empty($apiKey)) {
        @header('Content-Type: text/event-stream; charset=utf-8');
        echo "data: " . json_encode(['error' => 'No API keys available. Please configure API keys or enable local LLM.']) . "\n\n";
        exit;
    }

    // Model name mapping between providers
    // Each provider may use different naming conventions for the same model
    $modelMapping = [
        'groq' => [
            'gpt-oss-120b' => 'openai/gpt-oss-120b',
            'llama-3.3-70b' => 'llama-3.3-70b-versatile',
            'vision' => 'meta-llama/llama-4-scout-17b-16e-instruct',
        ],
        'cerebras' => [
            'gpt-oss-120b' => 'gpt-oss-120b',
            'llama-3.3-70b' => 'llama-3.3-70b',
            'vision' => null, // Cerebras doesn't support vision
        ],
    ];

    // Choose model based on request type
    if ($useLocalVision && $hasImage && $imageDataUrl) {
        // =====================================================================
        // LOCAL VISION MODEL
        // =====================================================================
        // Using local vision model (SmolVLM2, llava, etc.) for image understanding.
        // This runs entirely on your machine - no cloud API needed!
        // =====================================================================
        $modelName = $localLlmConfig->getVisionModel();
    } elseif ($hasImage && $imageDataUrl) {
        // Cloud vision requests use Groq's vision model
        $modelName = $modelMapping['groq']['vision'];
    } elseif ($useLocalLlm) {
        // Local LLM uses configuration from LocalLLMConfig
        $modelName = $localLlmConfig->getReasoningModel();
    } elseif ($sessionCloudProvider && $sessionCloudModel) {
        // User explicitly selected a cloud provider/model - use their selection
        $modelName = $sessionCloudModel;
    } else {
        // Text/web search requests use provider-specific model name
        $modelName = $modelMapping[$selectedProvider]['gpt-oss-120b'] ?? 'gpt-oss-120b';
    }

    // Final logging of selected provider and model
    if ($enableLogging && $logFile) {
        $logMsg .= "  FINAL selectedProvider: $selectedProvider\n";
        $logMsg .= "  FINAL modelName: $modelName\n";
        $logMsg .= "  useLocalLlm: " . ($useLocalLlm ? 'true' : 'false') . "\n";
        $logMsg .= "  useLocalVision: " . ($useLocalVision ? 'true' : 'false') . "\n";
        $logMsg .= "---\n";
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
    }

    // =====================================================================
    // CREATE PROVIDER INSTANCE
    // =====================================================================
    // Local LLM uses separate configuration class (src/Core/LLM/LocalLLMConfig.php)
    // - Reasoning model: port 8034 (text generation)
    // - Vision model: port 8033 (image understanding)
    // Cloud providers use the standard OpenAICompatibleProvider
    // =====================================================================
    if ($useLocalVision && $hasImage) {
        // Local vision model (separate server on port 8033)
        $config = $localLlmConfig->getVisionProviderConfig();
        $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
            'api_key' => $config['api_key'],
            'model' => $config['model'],
            'base_url' => $config['base_url'],
        ]);
    } elseif ($useLocalLlm) {
        // Local reasoning model (port 8034)
        $config = $localLlmConfig->getReasoningProviderConfig();
        $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
            'api_key' => $config['api_key'],
            'model' => $config['model'],
            'base_url' => $config['base_url'],
        ]);
    } else {
        // Cloud provider
        $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider($selectedProvider, [
            'api_key' => $apiKey,
            'model' => $modelName,
        ]);
    }

    // Prepare streaming headers
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    while (ob_get_level()) ob_end_flush();
    ignore_user_abort(true);

    // SSE headers
    if (!headers_sent()) header('Content-Type: text/event-stream; charset=utf-8');
    if (!headers_sent()) header('Cache-Control: no-cache');
    if (!headers_sent()) header('X-Accel-Buffering: no');
    if (!headers_sent()) header('Connection: keep-alive');

    // Flush initial padding
    echo str_repeat(' ', 1024);
    flush();

    // Get Parsedown for markdown rendering
    $parsedown = null;
    if (class_exists('\ParsedownExtra')) {
        try { $parsedown = new \ParsedownExtra(); } catch (\Throwable $_) {}
    } elseif (class_exists('\Parsedown')) {
        try { $parsedown = new \Parsedown(); } catch (\Throwable $_) {}
    }
    // Note: setSafeMode(true) strips PHP tags from code blocks, so we disable it
    // if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
    //     try { $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
    // }

    // Detect if this is a continuation request (tool result follow-up)
    $isContinuation = str_starts_with(trim($prompt), '[TOOL RESULT]') || str_contains($prompt, '=== COMPLETED STEPS ===');

    // System message with model identity and web search guidance
    $systemPrompt = 'You are Ginto, an AI assistant created by Oliver Bob. '
        . 'You are powered by advanced language models and have web search capability. '
        . 'When asked about your identity, say you are Ginto, created by Oliver Bob. But when you\'re not asked about your identity, focus on answering the user\'s questions helpfully and accurately. '
        . 'RESPONSE STYLE: Be concise and direct. Use short, clear sentences. Avoid unnecessary filler words, lengthy introductions, or verbose explanations. '
        . 'Exception: When providing code, technical explanations, or when the user explicitly asks for detailed/comprehensive responses, give thorough answers. ';
    
    // Load agent instructions module
    $agentInstructions = require __DIR__ . '/Includes/agent_instruct.php';
    
    // First check if LXC is even available on the system
    $lxcStatus = \Ginto\Helpers\LxdSandboxManager::checkLxcAvailability();
    $lxcAvailable = $lxcStatus['available'] ?? false;
    
    // Check if user has an active sandbox and add sandbox tools to system prompt
    $sandboxId = $_SESSION['sandbox_id'] ?? null;
    $hasSandbox = $lxcAvailable && !empty($sandboxId) && \Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId);
    
    if (!$lxcAvailable) {
        // LXC not installed - guide user to install Ginto
        $systemPrompt .= $agentInstructions['lxcNotInstalled']();
    } elseif ($hasSandbox) {
        // Check premium status for sandbox_exec access
        $isPremiumUser = false;
        if (!$isAdminUser && !empty($_SESSION['user_id']) && $db) {
            try {
                $activeSub = $db->get('subscriptions', ['id'], [
                    'user_id' => $_SESSION['user_id'],
                    'status' => 'active'
                ]);
                $isPremiumUser = !empty($activeSub);
            } catch (\Throwable $_) {}
        }
        
        // User has active sandbox - give full agentic instructions
        // Non-admin/non-premium users won't see sandbox_exec in the prompt
        $systemPrompt .= $agentInstructions['withSandbox']($sandboxId, $isContinuation, $isAdminUser, $isPremiumUser);
    } else {
        // No sandbox - agent can offer to install one
        $systemPrompt .= $agentInstructions['noSandbox']();
    }
    
    // Add web search guidance only for text model (vision model doesn't have browser_search)
    if (!$hasImage) {
        $systemPrompt .= 'When the user asks about current events, news, or information that would benefit from web search, use your browser_search tool. '
            . 'Be efficient: search only 3-5 most relevant sources, not more. '
            . 'Keep your reasoning concise and focused. ';
        
        // If there was an image earlier in the conversation, remind the model
        if ($hadImageInHistory) {
            $systemPrompt .= 'Note: Earlier in this conversation, the user shared an image which you analyzed. '
                . 'When they ask follow-up questions, refer to your previous analysis of that image. '
                . 'Messages marked with [User shared an image] indicate when an image was attached. ';
        }
    } else {
        $systemPrompt .= 'You have vision capabilities. Analyze the image carefully and provide helpful, detailed responses about what you see. ';
    }
    $systemPrompt .= 'IMPORTANT: Always reserve enough tokens to provide a complete, well-formatted final answer.';

    // Build messages with history
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($history as $hm) {
        if (!empty($hm['role']) && isset($hm['content'])) {
            $messages[] = ['role' => $hm['role'], 'content' => $hm['content']];
        }
    }
    
    // Build user message - with image content if attached
    if ($hasImage && $imageDataUrl) {
        // Vision format: content is an array with text and image_url
        $userContent = [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]]
        ];
        $messages[] = ['role' => 'user', 'content' => $userContent];
        error_log("[Vision Debug] hasImage=true, imageDataUrl length=" . strlen($imageDataUrl) . ", model=$modelName, provider=$selectedProvider");
    } else {
        // Text-only format
        $messages[] = ['role' => 'user', 'content' => $prompt];
    }

    // Track accumulated content and reasoning
    $accumulatedContent = '';
    $accumulatedReasoning = '';

    // Calculate tier-based max tokens from environment configuration
    $maxTokensBase = (int)(getenv('MAX_TOKENS_BASE') ?: ($_ENV['MAX_TOKENS_BASE'] ?? 8192));
    $tokenPercentages = [
        'admin' => (int)(getenv('MAX_TOKENS_ADMIN_PERCENT') ?: ($_ENV['MAX_TOKENS_ADMIN_PERCENT'] ?? 100)),
        'user' => (int)(getenv('MAX_TOKENS_USER_PERCENT') ?: ($_ENV['MAX_TOKENS_USER_PERCENT'] ?? 25)),
        'visitor' => (int)(getenv('MAX_TOKENS_VISITOR_PERCENT') ?: ($_ENV['MAX_TOKENS_VISITOR_PERCENT'] ?? 10)),
    ];
    $tierTokenPercent = $tokenPercentages[strtolower($userRole)] ?? $tokenPercentages['visitor'];
    $maxTokens = (int)floor($maxTokensBase * ($tierTokenPercent / 100));
    $maxTokens = max(512, $maxTokens); // Minimum 512 tokens to ensure usable responses

    // Vision model has lower max_tokens limit due to image context usage
    if ($hasImage && $imageDataUrl) {
        if ($useLocalVision) {
            // Local vision model uses configured max_tokens
            $maxTokens = min($maxTokens, $localLlmConfig->getVisionMaxTokens());
        } else {
            // Cloud vision model limit
            $maxTokens = min($maxTokens, 4096);
        }
    }

    // Apply configurable delay before contacting the model (non-blocking rate limit)
    $delayMs = (int)\Ginto\Helpers\ChatConfig::get('rateLimit.delayBetweenRequests', 0);
    if ($delayMs > 0) {
        usleep($delayMs * 1000); // Convert milliseconds to microseconds
    }

    // Stream directly from provider - browser_search is auto-added for GPT-OSS models only
    $response = $provider->chatStream(
        messages: $messages,
        tools: [], // Empty - browser_search is auto-added for GPT-OSS models (not for vision model)
        options: ['max_tokens' => $maxTokens],
        onChunk: function($chunk, $toolCall = null) use (&$accumulatedContent, &$accumulatedReasoning, $parsedown) {
            if ($toolCall !== null) {
                // Handle activity events (websearch)
                if (isset($toolCall['activity'])) {
                    $payload = [
                        'activity' => $toolCall['activity'],
                        'type' => $toolCall['type'] ?? null,
                        'query' => $toolCall['query'] ?? null,
                        'url' => $toolCall['url'] ?? null,
                        'domain' => $toolCall['domain'] ?? null,
                        'status' => $toolCall['status'] ?? 'running',
                    ];
                    $payload = array_filter($payload, fn($v) => $v !== null);
                    echo "data: " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    return;
                }
                
                // Handle reasoning events (thinking/analysis)
                if (isset($toolCall['reasoning'])) {
                    $reasoningText = $toolCall['text'] ?? '';
                    // Strip internal line reference markers
                    $reasoningText = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $reasoningText);
                    $accumulatedReasoning .= $reasoningText;
                    echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    return;
                }
                return;
            }

            if ($chunk !== '' && $chunk !== null) {
                // Strip internal line reference markers like 【2†L31-L38】 or 【†L29-L33】
                $chunk = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $chunk);
                // Strip model's leaked tool call intent patterns like "Search web.{...}"
                $chunk = preg_replace('/Search web\.\{[^}]+\}\.{0,3}/i', '', $chunk);
                // Skip empty chunks after filtering
                if (trim($chunk) === '') return;
                $accumulatedContent .= $chunk;
                
                // Client now uses markdown-it + KaTeX for rendering
                // Server just sends raw text chunks for better streaming performance
                $renderOnServer = \Ginto\Helpers\ChatConfig::get('streaming.renderMarkdownOnServer', false);
                if ($renderOnServer && $parsedown) {
                    // Legacy: Render accumulated content as HTML on server
                    // Fix malformed code blocks before parsing
                    $html = $parsedown->text(_fixCodeBlockNewlines($accumulatedContent));
                    echo "data: " . json_encode(['html' => $html, 'text' => $chunk], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                } else {
                    // Preferred: Send raw text, let client render with markdown-it
                    echo "data: " . json_encode(['text' => $chunk], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                }
                flush();
            }
        }
    );

    // Send final properly-rendered Markdown as HTML
    $finalContent = $accumulatedContent ?: ($response->getContent() ?? '');
    // Strip any remaining line reference markers
    $finalContent = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $finalContent);
    // Strip model's leaked tool call intent patterns like "Search web.{...}"
    $finalContent = preg_replace('/Search web\.\{[^}]+\}\.{0,3}/i', '', $finalContent);
    $accumulatedReasoning = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $accumulatedReasoning);
    
    // Log full response for debugging LaTeX rendering
    $logDir = dirname(__DIR__, 2) . '/../storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logEntry = date('Y-m-d H:i:s') . " [CHAT RESPONSE]\n";
    $logEntry .= "=== RAW CONTENT ===\n" . $finalContent . "\n";
    $logEntry .= "=== REASONING ===\n" . $accumulatedReasoning . "\n";
    $logEntry .= "==================\n\n";
    @file_put_contents($logDir . '/ginto.log', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Send final HTML-rendered version with reasoning
    if ($finalContent || $accumulatedReasoning) {
        // Fix malformed code blocks before parsing
        $html = $finalContent ? ($parsedown ? $parsedown->text(_fixCodeBlockNewlines($finalContent)) : '<pre>' . htmlspecialchars($finalContent) . '</pre>') : '';
        
        // Format reasoning as Groq-style timeline HTML with dot + line per item
        $reasoningHtml = '';
        if ($accumulatedReasoning) {
            // Helper to create a reasoning item with Groq-style structure
            $createReasoningItem = fn($content) => '<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>' . htmlspecialchars(trim(preg_replace('/\n/', ' ', $content))) . '</p></div></div>';
            
            // First try double newlines
            $paragraphs = array_filter(preg_split('/\n\n+/', $accumulatedReasoning), fn($p) => trim($p));
            
            // If only one paragraph, try single newlines
            if (count($paragraphs) <= 1) {
                $paragraphs = array_filter(preg_split('/\n/', $accumulatedReasoning), fn($p) => trim($p));
            }
            
            // If still one paragraph and text is long, split by sentence patterns
            if (count($paragraphs) <= 1 && strlen(trim($accumulatedReasoning)) > 100) {
                $text = preg_replace('/\s+/', ' ', trim($accumulatedReasoning));
                $parts = preg_split('/([.!?])\s+(?=(?:The |User |But |However |Now |Let\'s |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So ))/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                $sentences = [];
                $current = '';
                foreach ($parts as $part) {
                    if (preg_match('/^[.!?]$/', $part)) {
                        $current .= $part;
                    } else {
                        if ($current && preg_match('/^(The |User |But |However |Now |Let\'s |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So )/i', $part)) {
                            $sentences[] = trim($current);
                            $current = $part;
                        } else {
                            $current .= ($current && !preg_match('/[.!?]$/', $current) ? ' ' : '') . $part;
                        }
                    }
                }
                if (trim($current)) {
                    $sentences[] = trim($current);
                }
                $paragraphs = array_filter($sentences);
            }
            
            $reasoningHtml = implode('', array_map($createReasoningItem, $paragraphs));
        }
        
        echo "data: " . json_encode([
            'html' => $html,
            'reasoningHtml' => $reasoningHtml,
            'contentEmpty' => empty(trim($finalContent)),
            'final' => true
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    // Log successful request for rate limiting tracking
    $requestLatency = (int)((microtime(true) - $requestStartTime) * 1000);
    $tokensEstimate = (int)(strlen($prompt) / 4) + (int)(strlen($accumulatedContent) / 4); // Rough estimate
    
    // Mark the API key as successfully used
    if (isset($keyManager) && isset($currentKeyId) && $currentKeyId) {
        $keyManager->markKeyUsed($currentKeyId);
    }
    
    // Record per-user usage (protects against hitting provider limits)
    if (isset($userRateLimiter)) {
        $userRateLimiter->recordUsage(
            $userIdSession ? (int)$userIdSession : null,
            $visitorIp,
            $tokensEstimate
        );
    }
    
    $rateLimitService->logRequest([
        'user_id' => $userId,
        'user_role' => $userRole,
        'provider' => $selectedProvider,
        'model' => $modelName,
        'tokens_input' => (int)(strlen($prompt) / 4),
        'tokens_output' => (int)(strlen($accumulatedContent) / 4),
        'request_type' => $hasImage ? 'vision' : 'chat',
        'response_status' => 'success',
        'fallback_used' => $usingFallback ? 1 : 0,
        'latency_ms' => $requestLatency,
    ]);

} catch (\Throwable $e) {
    // Check if this is a rate limit error and mark the key accordingly
    $errorMessage = $e->getMessage();
    $isRateLimitError = (
        stripos($errorMessage, 'rate limit') !== false ||
        stripos($errorMessage, 'rate_limit') !== false ||
        stripos($errorMessage, '429') !== false ||
        stripos($errorMessage, 'too many requests') !== false
    );
    
    if ($isRateLimitError && isset($keyManager) && isset($currentKeyId) && $currentKeyId) {
        // Mark current key as rate-limited and try to get next key
        $keyManager->markKeyRateLimited($currentKeyId, 60);
        
        // Try to get next available key for retry hint
        $nextKey = $keyManager->getNextAvailableKey($currentKeyId);
        if ($nextKey) {
            // There's another key available - could retry with it
            error_log("Rate limit hit on key {$currentKeyId}, next available: {$nextKey['id']} ({$nextKey['provider']})");
        } else {
            // No more DB keys, will fall back to .env keys on next request
            error_log("Rate limit hit on key {$currentKeyId}, no more DB keys - will use .env on next request");
        }
    }
    
    // Log failed request
    if (isset($rateLimitService)) {
        $requestLatency = isset($requestStartTime) ? (int)((microtime(true) - $requestStartTime) * 1000) : 0;
        $rateLimitService->logRequest([
            'user_id' => $userId ?? session_id(),
            'user_role' => $userRole ?? 'visitor',
            'provider' => $selectedProvider ?? 'groq',
            'model' => $modelName ?? 'openai/gpt-oss-120b',
            'tokens_input' => isset($prompt) ? (int)(strlen($prompt) / 4) : 0,
            'tokens_output' => 0,
            'request_type' => ($hasImage ?? false) ? 'vision' : 'chat',
            'response_status' => 'error',
            'fallback_used' => ($usingFallback ?? false) ? 1 : 0,
            'latency_ms' => $requestLatency,
        ]);
    }
    
    // Fallback error handling
    @ini_set('output_buffering', 'off');
    while (ob_get_level()) ob_end_flush();
    
    // Determine user-friendly error message
    $userError = 'An internal error occurred while processing your request.';
    if (stripos($errorMessage, 'connection refused') !== false || 
        stripos($errorMessage, 'could not connect') !== false ||
        stripos($errorMessage, 'connection timed out') !== false ||
        stripos($errorMessage, 'curl error') !== false) {
        $userError = 'Unable to connect to the AI model. The service may be temporarily unavailable.';
    } elseif (stripos($errorMessage, 'timeout') !== false) {
        $userError = 'The AI model took too long to respond. Please try again.';
    } elseif ($isRateLimitError) {
        $userError = 'Rate limit exceeded. Please wait a moment and try again.';
    }
    
    // Send error as SSE so frontend can display it properly
    if (!headers_sent()) {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
    }
    echo str_repeat(' ', 1024);
    flush();

    // Log full details for administrators, but don't expose provider/raw error text to clients
    \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/chat', 'trace' => $e->getTraceAsString()]);
    echo "data: " . json_encode(['error' => $userError]) . "\n\n";
    flush();
}

exit;
