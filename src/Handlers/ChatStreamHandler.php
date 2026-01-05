<?php

namespace Ginto\Handlers;

use Ginto\Database;

/**
 * ChatStreamHandler - Handles the streaming chat POST requests
 * 
 * This handler encapsulates the complete streaming logic for the /chat route,
 * including CSRF validation, rate limiting, provider selection, conversation
 * history, image handling, and SSE streaming.
 */
class ChatStreamHandler
{
    private $db;
    
    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Handle the streaming chat request (POST /chat)
     */
    public function handle(): void
    {
        // CSRF validation
        if (!$this->validateCsrf()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid or missing CSRF token']);
            exit;
        }

        // Check visitor limits
        if (!$this->checkVisitorLimit()) {
            exit;
        }

        // Initialize rate limiting
        $db = $this->db;
        $rateLimitService = new \App\Core\RateLimitService($db);
        $userIdSession = $_SESSION['user_id'] ?? null;
        $userId = $userIdSession ?? ($_SESSION['sandbox_id'] ?? session_id());

        // Determine user role
        $isAdminUser = !empty($_SESSION['is_admin']) 
            || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin')
            || (!empty($_SESSION['user_role']) && strtolower((string)$_SESSION['user_role']) === 'admin');
        $userRole = !empty($_SESSION['user_id']) ? ($isAdminUser ? 'admin' : 'user') : 'visitor';

        $visitorIp = $userIdSession === null ? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') : null;

        // Get primary provider from environment
        $primaryProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
        if (!in_array($primaryProvider, ['groq', 'cerebras'], true)) {
            $primaryProvider = 'cerebras';
        }
        
        // Default model depends on provider - gpt-oss-120b is Cerebras-only
        if ($primaryProvider === 'cerebras') {
            $defaultModel = 'gpt-oss-120b';
        } else {
            $defaultModel = 'llama-3.3-70b-versatile';
        }

        // Check per-user limits
        $userRateLimiter = new \App\Core\UserRateLimiter($db, $primaryProvider);
        $userLimitCheck = $userRateLimiter->checkLimit(
            $userIdSession ? (int)$userIdSession : null,
            $visitorIp,
            $userRole
        );
        if (!$userLimitCheck['allowed']) {
            $this->sendRateLimitError($userLimitCheck);
            exit;
        }

        // Check provider-level limits
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

        // Select provider
        $providerSelection = $rateLimitService->selectProvider($primaryProvider, $defaultModel);
        $selectedProvider = $providerSelection['provider'];
        $usingFallback = $providerSelection['is_fallback'] ?? false;
        $requestStartTime = microtime(true);

        // Read the incoming prompt
        $prompt = $_POST['prompt'] ?? trim(file_get_contents('php://input')) ?: 'Hello, how can you help me today?';

        // Handle repository description requests (fast path)
        if ($this->handleRepoDescription($prompt)) {
            exit;
        }

        // Check for image attachment
        $hasImage = !empty($_POST['hasImage']) && $_POST['hasImage'] === '1';
        $imageDataUrl = $_POST['image'] ?? null;

        // Build conversation history
        $history = $this->buildHistory();
        $hadImageInHistory = $this->checkImageInHistory($history);

        // Handle session-selected Ollama provider
        $sessionProvider = $_SESSION['llm_provider_name'] ?? null;
        $sessionModel = $_SESSION['llm_model'] ?? null;

        if ($sessionProvider === 'ollama' && $sessionModel) {
            if ($this->handleOllamaRequest($prompt, $sessionModel, $isAdminUser)) {
                exit;
            }
        }

        // Main cloud/local provider handling
        $this->handleMainRequest(
            $prompt, $hasImage, $imageDataUrl, $history, $hadImageInHistory,
            $isAdminUser, $userRole, $userId, $userIdSession, $visitorIp,
            $selectedProvider, $defaultModel, $usingFallback, $requestStartTime,
            $rateLimitService, $userRateLimiter, $db
        );
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Check visitor prompt limits (per session)
     */
    private function checkVisitorLimit(): bool
    {
        if (!empty($_SESSION['user_id'])) {
            return true; // Logged-in users bypass
        }

        $visitorLimitKey = 'visitor_prompts_session';
        $visitorPromptCount = (int)($_SESSION[$visitorLimitKey] ?? 0);
        $visitorMaxPrompts = (int)\Ginto\Helpers\ChatConfig::get('visitor.maxPromptsPerSession', 5);

        if ($visitorPromptCount >= $visitorMaxPrompts) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            echo str_repeat(' ', 1024);
            flush();
            echo "data: " . json_encode([
                'error' => true,
                'action' => 'register',
                'text' => "You've used your {$visitorMaxPrompts} free messages. Create a free account to continue chatting with Ginto!",
                'prompts_used' => $visitorPromptCount,
                'prompts_limit' => $visitorMaxPrompts,
                'register_url' => '/register'
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            echo "data: " . json_encode([
                'final' => true,
                'action' => 'register',
                'html' => '<div class="text-amber-400"><p>You\'ve used all ' . $visitorMaxPrompts . ' free messages.</p><p class="mt-2"><a href="/register" class="text-indigo-400 hover:text-indigo-300 underline font-semibold">Create a free account</a> to continue chatting!</p></div>'
            ]) . "\n\n";
            flush();
            return false;
        }

        // Increment visitor prompt count
        $_SESSION[$visitorLimitKey] = $visitorPromptCount + 1;

        return true;
    }

    /**
     * Send rate limit error as SSE
     */
    private function sendRateLimitError(array $check): void
    {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        echo str_repeat(' ', 1024);
        flush();
        echo "data: " . json_encode([
            'error' => true,
            'text' => $check['message'],
            'reason' => $check['reason'],
            'usage' => $check['usage'],
            'limits' => $check['limits'],
            'retry_after' => $check['retry_after'] ?? 60,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        echo "data: " . json_encode(['final' => true, 'html' => '<p class="text-amber-500">' . htmlspecialchars($check['message']) . '</p>']) . "\n\n";
        flush();
    }

    /**
     * Handle repository description request (fast path)
     */
    private function handleRepoDescription(string $prompt): bool
    {
        $lower = mb_strtolower($prompt);
        $describeKeywords = ['describe this repo', 'about this repo', 'about this repository'];
        $shouldDescribe = false;

        foreach ($describeKeywords as $kw) {
            if (strpos($lower, $kw) !== false) {
                $shouldDescribe = true;
                break;
            }
        }

        if (!$shouldDescribe && isset($_POST['describe_repo'])) {
            $v = strtolower((string)$_POST['describe_repo']);
            if (in_array($v, ['1', 'true', 'yes'], true)) {
                $shouldDescribe = true;
            }
        }

        if (!$shouldDescribe) {
            return false;
        }

        // Check if sandbox session
        try {
            $editorRootCheck = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db, $_SESSION ?? null);
            $isSandboxSession = (realpath($editorRootCheck) !== (realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) ?: (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2))));
            if ($isSandboxSession) {
                self::streamResponse("Repository description is not available for sandboxed sessions.");
                return true;
            }
        } catch (\Throwable $_) {
            self::streamResponse("Repository description is not available at this time.");
            return true;
        }

        // Build deterministic repo summary
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $readme = file_exists($root . '/README.md') ? file_get_contents($root . '/README.md') : '';
        $composer = file_exists($root . '/composer.json') ? @json_decode(file_get_contents($root . '/composer.json'), true) : null;

        $rawFiles = @scandir($root) ?: [];
        $skip = ['vendor', 'node_modules', '.git', '.idea', 'storage', '.env'];
        $files = [];
        foreach ($rawFiles as $f) {
            if ($f === '.' || $f === '..') continue;
            if (isset($f[0]) && $f[0] === '.') continue;
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

        self::streamResponse($summary);
        return true;
    }

    /**
     * Build conversation history from client-supplied data
     */
    private function buildHistory(): array
    {
        $history = [];
        $historyJson = $_POST['history'] ?? null;

        if ($historyJson) {
            $h = json_decode($historyJson, true);
            if (is_array($h)) {
                foreach ($h as $hm) {
                    if (!empty($hm['role']) && isset($hm['content'])) {
                        if ($hm['role'] === 'system') continue;

                        if ($hm['role'] === 'user' && !empty($hm['hasImage'])) {
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

        return $history;
    }

    /**
     * Check if there was an image in conversation history
     */
    private function checkImageInHistory(array $history): bool
    {
        foreach ($history as $msg) {
            if (strpos($msg['content'] ?? '', '[User shared an image]') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle Ollama provider requests
     */
    private function handleOllamaRequest(string $prompt, string $model, bool $isAdminUser): bool
    {
        try {
            // Use centralized host detection for Docker/LXC compatibility
            $host = \App\Core\LLM\ProviderRegistry::getHostAddress();
            $ollamaHost = getenv('OLLAMA_HOST') ?: "http://{$host}:11434";
            $checkCtx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $versionCheck = @file_get_contents($ollamaHost . '/api/version', false, $checkCtx);

            if ($versionCheck === false) {
                @header('Content-Type: text/event-stream; charset=utf-8');
                echo "data: " . json_encode(['error' => 'Ollama server is offline. Please start Ollama with: ollama serve']) . "\n\n";
                return true;
            }

            $ollamaProvider = \App\Core\LLM\LLMProviderFactory::create('ollama', ['model' => $model]);

            if (!$ollamaProvider->isConfigured()) {
                return false;
            }

            self::prepareSSE();

            // Build messages
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

            $messages[] = ['role' => 'user', 'content' => $prompt];

            $fullResponse = '';
            $accumulatedReasoning = '';
            $streamError = null;

            $onChunk = function($chunk, $toolCall = null) use (&$fullResponse, &$accumulatedReasoning) {
                if ($toolCall !== null && isset($toolCall['reasoning'])) {
                    $reasoningText = $toolCall['text'] ?? '';
                    $accumulatedReasoning .= $reasoningText;
                    echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    return;
                }

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

            if ($streamError && empty($fullResponse)) {
                echo "data: " . json_encode(['error' => 'Model not responding: ' . $streamError]) . "\n\n";
                flush();
                return true;
            }

            // Update Ollama cache
            $cacheDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH)) . '/cache';
            $cacheFile = $cacheDir . '/ollama_ps.json';
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($cacheFile, json_encode([
                'models' => [$model],
                'updated_at' => time(),
                'updated_at_iso' => date('c'),
            ], JSON_PRETTY_PRINT));

            // Send final response
            $parsedown = self::getParsedown();
            $fixedResponse = preg_replace('/```([a-zA-Z0-9+#]+)(?!\n)/', "```$1\n", $fullResponse);
            $html = $parsedown ? $parsedown->text($fixedResponse) : nl2br(htmlspecialchars($fixedResponse));
            $reasoningHtml = self::formatReasoningHtml($accumulatedReasoning);

            echo "data: " . json_encode([
                'html' => $html,
                'reasoningHtml' => $reasoningHtml,
                'contentEmpty' => empty(trim($fullResponse)),
                'final' => true
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();

            return true;

        } catch (\Throwable $e) {
            error_log("Ollama provider failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle main cloud/local provider request
     */
    private function handleMainRequest(
        string $prompt, bool $hasImage, ?string $imageDataUrl, array $history, bool $hadImageInHistory,
        bool $isAdminUser, string $userRole, string $userId, ?int $userIdSession, ?string $visitorIp,
        string $selectedProvider, string $defaultModel, bool $usingFallback, float $requestStartTime,
        $rateLimitService, $userRateLimiter, $db
    ): void {
        $sessionProvider = $_SESSION['llm_provider_name'] ?? null;
        $sessionModel = $_SESSION['llm_model'] ?? null;

        // Check for local LLM preference
        $forceLocalLlm = false;
        if ($sessionProvider && $sessionModel && ($sessionProvider === 'local' || $sessionProvider === 'ginto')) {
            if (\App\Core\LLM\Providers\OpenAICompatibleProvider::isGintoDefault($sessionModel)) {
                $forceLocalLlm = true;
            }
        }

        // Session-selected cloud provider
        $sessionCloudProvider = null;
        $sessionCloudModel = null;
        $cloudProviders = ['cerebras', 'groq', 'openai', 'anthropic', 'together', 'fireworks'];
        if ($sessionProvider && $sessionModel && in_array($sessionProvider, $cloudProviders, true)) {
            $sessionCloudProvider = $sessionProvider;
            $sessionCloudModel = $sessionModel;
        }

        try {
            $keyManager = new \App\Core\ProviderKeyManager($db);
            $currentKeyId = null;

            // Detect web search needs
            $needsWebSearch = $this->detectWebSearchNeed($prompt);
            
            // Check search provider preference (lightpanda or groq)
            // When using Lightpanda, we handle search server-side so ANY provider works
            $searchProvider = strtolower(getenv('SEARCH_PROVIDER') ?: ($_ENV['SEARCH_PROVIDER'] ?? 'lightpanda'));
            $useLightpandaSearch = $searchProvider === 'lightpanda' && \Ginto\Handlers\LightpandaSearchHandler::isAvailable();
            
            // Only require Groq for web search if using Groq's built-in browser_search
            // With Lightpanda, any provider works since we handle search ourselves

            // Local LLM config
            $localLlmConfig = \App\Core\LLM\LocalLLMConfig::getInstance();
            $canUseLocalVision = $hasImage && $localLlmConfig->isEnabled() && $localLlmConfig->isVisionServerHealthy();

            $requiresGroq = $needsWebSearch && !$useLightpandaSearch;
            $requiresCloudVision = $hasImage && !$canUseLocalVision;

            // Select API key and provider
            $apiKey = null;
            if ($sessionCloudProvider && !$requiresGroq && !$requiresCloudVision) {
                $sessionKeyData = $keyManager->getAvailableKey($sessionCloudProvider);
                if ($sessionKeyData) {
                    $apiKey = $sessionKeyData['api_key'];
                    $currentKeyId = $sessionKeyData['id'];
                    $selectedProvider = $sessionCloudProvider;
                } else {
                    $sessionCloudProvider = null;
                    $sessionCloudModel = null;
                }
            }

            if (!$apiKey) {
                $keyData = null;
                if ($requiresGroq || $requiresCloudVision) {
                    $keyData = $keyManager->getAvailableKey('groq');
                } else {
                    $keyData = $keyManager->getFirstAvailableKey();
                }

                if ($keyData) {
                    $apiKey = $keyData['api_key'];
                    $currentKeyId = $keyData['id'];
                    $selectedProvider = $keyData['provider'];
                } else {
                    // Fallback to environment variables
                    if ($requiresGroq) {
                        $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
                        $selectedProvider = 'groq';
                    } else {
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

            // Save cloud API key for web search reasoning (before we potentially switch to local)
            $cloudApiKey = ($apiKey && $apiKey !== 'local') ? $apiKey : null;
            $cloudProvider = ($selectedProvider && $selectedProvider !== 'local') ? $selectedProvider : null;
            
            // If no cloud key yet, try to get one for web search reasoning
            if (!$cloudApiKey) {
                $defaultProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
                $envVarPrimary = ($defaultProvider === 'cerebras') ? 'CEREBRAS_API_KEY' : 'GROQ_API_KEY';
                $envVarFallback = ($defaultProvider === 'cerebras') ? 'GROQ_API_KEY' : 'CEREBRAS_API_KEY';
                
                $cloudApiKey = getenv($envVarPrimary) ?: ($_ENV[$envVarPrimary] ?? '');
                $cloudProvider = $defaultProvider;
                
                if (empty($cloudApiKey)) {
                    $cloudApiKey = getenv($envVarFallback) ?: ($_ENV[$envVarFallback] ?? '');
                    $cloudProvider = ($defaultProvider === 'cerebras') ? 'groq' : 'cerebras';
                }
            }
            
            error_log("[ChatStream] cloudApiKey set: " . (!empty($cloudApiKey) ? "YES (" . strlen($cloudApiKey) . " chars)" : "NO") . ", cloudProvider: $cloudProvider");

            // Local LLM fallback
            $useLocalLlm = false;
            $useLocalVision = false;

            if (!$requiresGroq && !$hasImage && ($forceLocalLlm || $localLlmConfig->isPrimary() || empty($apiKey))) {
                if ($localLlmConfig->isEnabled() && $localLlmConfig->isReasoningServerHealthy()) {
                    $useLocalLlm = true;
                    $selectedProvider = 'local';
                    $apiKey = 'local';
                }
            }

            if ($hasImage && $canUseLocalVision && ($forceLocalLlm || $localLlmConfig->isPrimary() || empty($apiKey))) {
                $useLocalVision = true;
                $useLocalLlm = true;
                $selectedProvider = 'local';
                $apiKey = 'local';
            }

            if (empty($apiKey)) {
                @header('Content-Type: text/event-stream; charset=utf-8');
                $debugInfo = [
                    'sessionCloudProvider' => $sessionCloudProvider ?? 'null',
                    'sessionCloudModel' => $sessionCloudModel ?? 'null',
                    'requiresGroq' => $requiresGroq,
                    'needsWebSearch' => $needsWebSearch,
                    'useLightpandaSearch' => $useLightpandaSearch,
                    'selectedProvider' => $selectedProvider,
                ];
                error_log("No API key found: " . json_encode($debugInfo));
                echo "data: " . json_encode(['error' => 'No API keys available. Please configure API keys or enable local LLM. Debug: ' . json_encode($debugInfo)]) . "\n\n";
                exit;
            }

            // Model selection
            $modelMapping = [
                'groq' => [
                    'llama-3.3-70b' => 'llama-3.3-70b-versatile',
                    'gpt-oss-120b' => 'openai/gpt-oss-120b',
                    'vision' => 'meta-llama/llama-4-scout-17b-16e-instruct',
                ],
                'cerebras' => [
                    'gpt-oss-120b' => 'gpt-oss-120b',
                    'llama-3.3-70b' => 'llama-3.3-70b',
                    'vision' => null,
                ],
            ];
            
            // gpt-oss-120b - check if the requested model matches and adjust for correct provider
            // Cerebras uses 'gpt-oss-120b', Groq uses 'openai/gpt-oss-120b'
            $requestedModel = $sessionCloudModel ?? $defaultModel ?? 'gpt-oss-120b';
            if (strpos($requestedModel, 'gpt-oss-120b') !== false) {
                if ($selectedProvider === 'cerebras') {
                    // Cerebras requires model name WITHOUT 'openai/' prefix
                    $requestedModel = 'gpt-oss-120b';
                } elseif ($selectedProvider === 'groq') {
                    // Groq uses 'openai/gpt-oss-120b' prefix
                    $requestedModel = 'openai/gpt-oss-120b';
                }
            }

            if ($useLocalVision && $hasImage && $imageDataUrl) {
                $modelName = $localLlmConfig->getVisionModel();
            } elseif ($hasImage && $imageDataUrl) {
                $modelName = $modelMapping['groq']['vision'];
            } elseif ($useLocalLlm) {
                $modelName = $localLlmConfig->getReasoningModel();
            } elseif ($sessionCloudProvider && $sessionCloudModel) {
                $modelName = $sessionCloudModel;
            } else {
                // Default model based on provider
                if ($selectedProvider === 'cerebras') {
                    $modelName = 'gpt-oss-120b';
                } elseif ($selectedProvider === 'groq') {
                    $modelName = 'llama-3.3-70b-versatile';
                } else {
                    $modelName = $modelMapping[$selectedProvider]['llama-3.3-70b'] ?? 'llama-3.3-70b-versatile';
                }
            }

            // Create provider instance
            if ($useLocalVision && $hasImage) {
                $config = $localLlmConfig->getVisionProviderConfig();
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
                    'api_key' => $config['api_key'],
                    'model' => $config['model'],
                    'base_url' => $config['base_url'],
                ]);
            } elseif ($useLocalLlm) {
                $config = $localLlmConfig->getReasoningProviderConfig();
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
                    'api_key' => $config['api_key'],
                    'model' => $config['model'],
                    'base_url' => $config['base_url'],
                ]);
            } else {
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider($selectedProvider, [
                    'api_key' => $apiKey,
                    'model' => $modelName,
                ]);
            }

            // Prepare SSE
            self::prepareSSE();

            $parsedown = self::getParsedown();
            
            // ==========================================
            // PRE-EXECUTION WEB SEARCH (like /pandasearch)
            // Execute web search BEFORE LLM call, streaming reasoning as we go
            // ==========================================
            $webSearchContext = '';
            $webSearchSources = [];
            
            if ($useLightpandaSearch && $needsWebSearch) {
                error_log("[ChatStream] Executing pre-LLM web search for: $prompt");
                
                $searchStartTime = microtime(true);
                
                // Activity callback - streams search/fetch events
                $onActivity = function(array $event) use ($searchStartTime) {
                    $event['elapsed_ms'] = round((microtime(true) - $searchStartTime) * 1000);
                    echo "data: " . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                };
                
                // Reasoning callback - streams AI analysis chunks
                $reasoningChunks = 0;
                $onReasoning = function(array $event) use ($searchStartTime, &$reasoningChunks) {
                    $event['elapsed_ms'] = round((microtime(true) - $searchStartTime) * 1000);
                    $reasoningChunks++;
                    echo "data: " . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                };
                
                // Emit phase marker: search phase starting (matches pandasearch format)
                echo "data: " . json_encode([
                    'phase' => 'search',
                    'message' => 'Searching the web...',
                    'elapsed_ms' => round((microtime(true) - $searchStartTime) * 1000),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                try {
                    // Debug: log the API key we're passing
                    error_log("[ChatStream] Calling executeInterleavedSearch with cloudProvider=" . ($cloudProvider ?? 'null') . ", cloudApiKey=" . (!empty($cloudApiKey) ? "SET(" . strlen($cloudApiKey) . " chars)" : "EMPTY") . ", modelName=" . ($modelName ?? 'null'));
                    
                    // Use the same interleaved approach as PandaSearchHandler
                    // Pass the selected model so reasoning uses the same model as chat
                    $searchResult = LightpandaSearchHandler::executeInterleavedSearch(
                        ['query' => $prompt, 'num_results' => 3],
                        $onActivity,
                        $onReasoning,
                        $cloudProvider ?? $selectedProvider ?? 'cerebras',
                        $cloudApiKey ?? $apiKey,
                        $modelName
                    );
                    
                    if ($searchResult['success'] ?? false) {
                        $webSearchContext = $searchResult['content'] ?? '';
                        $webSearchSources = $searchResult['sources'] ?? [];
                        
                        // Emit phase marker: search_complete (matches pandasearch format)
                        echo "data: " . json_encode([
                            'phase' => 'search_complete',
                            'message' => '',
                            'elapsed_ms' => round((microtime(true) - $searchStartTime) * 1000),
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                        
                        // Emit sources with favicons and final event (matches pandasearch format)
                        if (!empty($webSearchSources)) {
                            $sources = [];
                            foreach ($webSearchSources as $source) {
                                $sources[] = [
                                    'title' => $source['title'] ?? 'Untitled',
                                    'url' => $source['url'] ?? '',
                                    'domain' => $source['domain'] ?? '',
                                    'favicon' => 'https://www.google.com/s2/favicons?domain=' . ($source['domain'] ?? '') . '&sz=32',
                                ];
                            }
                            
                            // Emit final event with all stats (matches pandasearch format)
                            echo "data: " . json_encode([
                                'final' => true,
                                'success' => true,
                                'sources_count' => count($sources),
                                'reasoning_chunks' => $reasoningChunks,
                                'total_elapsed_ms' => round((microtime(true) - $searchStartTime) * 1000),
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                            
                            echo "data: " . json_encode([
                                'websearch_complete' => true,
                                'sources' => $sources,
                                'sources_count' => count($sources),
                                'reasoning_chunks' => $reasoningChunks,
                                'total_elapsed_ms' => round((microtime(true) - $searchStartTime) * 1000),
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    }
                    
                    error_log("[ChatStream] Web search complete: " . count($webSearchSources) . " sources found");
                    
                } catch (\Throwable $e) {
                    error_log("[ChatStream] Web search error: " . $e->getMessage());
                    echo "data: " . json_encode([
                        'phase' => 'error',
                        'message' => 'Search error: ' . $e->getMessage(),
                        'elapsed_ms' => round((microtime(true) - $searchStartTime) * 1000),
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }
            }

            // Build system prompt - pass search provider flag
            $systemPrompt = $this->buildSystemPrompt($hasImage, $hadImageInHistory, false, $isAdminUser, false);
            
            // If we have web search context, append it to system prompt
            if (!empty($webSearchContext)) {
                $systemPrompt .= "\n\n## Web Search Results\nThe following information was found from web search. Use this to answer the user's question:\n\n" . $webSearchContext;
            }

            // Build messages
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $hm) {
                if (!empty($hm['role']) && isset($hm['content'])) {
                    $messages[] = ['role' => $hm['role'], 'content' => $hm['content']];
                }
            }

            // Add user message
            if ($hasImage && $imageDataUrl) {
                $userContent = [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]]
                ];
                $messages[] = ['role' => 'user', 'content' => $userContent];
            } else {
                $messages[] = ['role' => 'user', 'content' => $prompt];
            }

            // Calculate max tokens
            $maxTokens = $this->calculateMaxTokens($userRole, $hasImage, $imageDataUrl, $useLocalVision, $localLlmConfig ?? null);

            // Apply delay
            $delayMs = (int)\Ginto\Helpers\ChatConfig::get('rateLimit.delayBetweenRequests', 0);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            // === PROPER TOOL USE IMPLEMENTATION ===
            // Get tools in OpenAI format if user has sandbox access
            $tools = [];
            $hasSandboxAccess = !empty($_SESSION['sandbox_id']) && !empty($_SESSION['user_id']);
            
            // Always add generate_image tool for all logged-in users
            if (!empty($_SESSION['user_id'])) {
                $mcpUnifier = new \App\Core\McpUnifier();
                $tools = $mcpUnifier->getToolsAsOpenAI(['generate_image']);
                error_log("[ChatStream] Image gen tool loaded");
            }
            
            if ($hasSandboxAccess) {
                $mcpUnifier = $mcpUnifier ?? new \App\Core\McpUnifier();
                $sandboxTools = $mcpUnifier->getToolsAsOpenAI([
                    'sandbox_list_files',
                    'sandbox_read_file', 
                    'sandbox_write_file',
                    'sandbox_delete',
                    'sandbox_rename_file',
                    'sandbox_copy_file',
                    'sandbox_create_document',
                    'sandbox_create_project',
                    'sandbox_exec',
                ]);
                $tools = array_merge($tools, $sandboxTools);
                error_log("[ChatStream] Sandbox tools loaded: " . count($sandboxTools) . " tools");
            } else {
                error_log("[ChatStream] No sandbox access - sandbox tools empty");
            }
            
            error_log("[ChatStream] Total tools: " . count($tools));
            
            // NOTE: Web search is now executed BEFORE the LLM call (see above)
            // No need to add Lightpanda as a tool - results are already in context

            // Agentic loop - keep going until model finishes (no more tool_calls)
            $maxIterations = 15;
            $iteration = 0;
            $accumulatedContent = '';
            $accumulatedReasoning = '';

            try {
            while ($iteration < $maxIterations) {
                $iteration++;
                
                // Debug: log iteration
                error_log("[ChatStream] Tool loop iteration $iteration");
                
                $response = $provider->chatStream(
                    messages: $messages,
                    tools: $tools,
                    options: ['max_tokens' => $maxTokens, 'tool_choice' => 'auto'],
                    onChunk: function($chunk, $event = null) use (&$accumulatedContent, &$accumulatedReasoning, $parsedown) {
                        if ($event !== null) {
                            if (isset($event['activity'])) {
                                $payload = array_filter([
                                    'activity' => $event['activity'],
                                    'type' => $event['type'] ?? null,
                                    'query' => $event['query'] ?? null,
                                    'url' => $event['url'] ?? null,
                                    'domain' => $event['domain'] ?? null,
                                    'status' => $event['status'] ?? 'running',
                                ], fn($v) => $v !== null);
                                echo "data: " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                                flush();
                                return;
                            }

                            if (isset($event['reasoning'])) {
                                $reasoningText = self::stripMarkers($event['text'] ?? '');
                                $accumulatedReasoning .= $reasoningText;
                                echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                                flush();
                                return;
                            }
                            return;
                        }

                        if ($chunk !== '' && $chunk !== null) {
                            // Only strip markers if the chunk has actual content, not just whitespace
                            // Important: preserve space-only chunks as they separate words
                            if (preg_match('/[^\s]/', $chunk)) {
                                $chunk = self::stripMarkers($chunk);
                            }
                            // Skip truly empty chunks but keep whitespace-only chunks
                            if ($chunk === '') return;
                            $accumulatedContent .= $chunk;
                            error_log("[ChatStream] Streaming text chunk: " . strlen($chunk) . " chars");
                            echo "data: " . json_encode(['text' => $chunk], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    }
                );

                // Check if model wants to use tools
                $toolCalls = $response->getToolCalls();
                $finishReason = $response->getFinishReason();

                // Debug: log tool calls
                error_log("[ChatStream] Iteration $iteration: finishReason=$finishReason, toolCalls count=" . count($toolCalls));

                // Check for tool calls - some providers return tool_calls even with 'stop' finish reason
                // So prioritize checking for actual tool_calls over finish_reason
                if (empty($toolCalls)) {
                    // No tool calls - model is done, break the loop
                    error_log("[ChatStream] No tool calls, breaking loop");
                    break;
                }

                // Execute each tool call and add results to messages
                foreach ($toolCalls as $tc) {
                    $toolName = $tc['name'] ?? '';
                    $toolArgs = $tc['arguments'] ?? [];
                    $toolId = $tc['id'] ?? ('call_' . uniqid());

                    // Stream tool execution status to client
                    echo "data: " . json_encode([
                        'tool_use' => true,
                        'tool_name' => $toolName,
                        'tool_args' => $toolArgs,
                        'status' => 'executing'
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();

                    try {
                        // Special handling for generate_image with progress streaming
                        if ($toolName === 'generate_image') {
                            $result = $this->executeImageGeneration($toolArgs['prompt'] ?? '');
                        } else {
                            // Execute the tool via McpInvoker
                            $result = \App\Core\McpInvoker::invoke($toolName, $toolArgs);
                        }
                        
                        // Stream result to client (include tool_args for checkpoint support)
                        echo "data: " . json_encode([
                            'tool_result' => true,
                            'tool_name' => $toolName,
                            'tool_args' => $toolArgs,
                            'result' => $result,
                            'success' => $result['success'] ?? true
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();

                        // Add assistant message with tool call
                        $messages[] = [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => json_encode($toolArgs)
                                ]
                            ]]
                        ];

                        // Add tool result message
                        // For generate_image, send simplified result without URLs (UI already shows image)
                        if ($toolName === 'generate_image' && ($result['success'] ?? false)) {
                            $toolResultContent = json_encode([
                                'success' => true,
                                'message' => 'Image generated and displayed to user. Do NOT output any image markdown or URLs - the image is already visible.',
                                'prompt' => $result['prompt'] ?? '',
                                'model' => $result['model'] ?? 'FastSD CPU'
                            ]);
                        } else {
                            $toolResultContent = json_encode($result);
                        }
                        
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolId,
                            'content' => $toolResultContent
                        ];

                    } catch (\Throwable $e) {
                        // Tool execution failed
                        echo "data: " . json_encode([
                            'tool_result' => true,
                            'tool_name' => $toolName,
                            'error' => $e->getMessage(),
                            'success' => false
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();

                        // Add error result to messages
                        $messages[] = [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => json_encode($toolArgs)
                                ]
                            ]]
                        ];
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolId,
                            'content' => json_encode(['success' => false, 'error' => $e->getMessage()])
                        ];
                    }
                }
                
                // Clear accumulated content for next iteration (we'll get new content)
                $accumulatedContent = '';
            }
            } catch (\Throwable $loopError) {
                error_log("[ChatStream] Tool loop error: " . $loopError->getMessage());
                echo "data: " . json_encode(['error' => 'Tool execution failed: ' . $loopError->getMessage()]) . "\n\n";
                flush();
            }

            // Send final response
            $finalContent = self::stripMarkers($accumulatedContent ?: ($response->getContent() ?? ''));
            $accumulatedReasoning = self::stripMarkers($accumulatedReasoning);

            self::sendFinalResponse($finalContent, $accumulatedReasoning, $parsedown);

            // Log successful request
            $requestLatency = (int)((microtime(true) - $requestStartTime) * 1000);
            $tokensEstimate = (int)(strlen($prompt) / 4) + (int)(strlen($accumulatedContent) / 4);

            if ($currentKeyId) {
                $keyManager->markKeyUsed($currentKeyId);
            }

            $userRateLimiter->recordUsage(
                $userIdSession ? (int)$userIdSession : null,
                $visitorIp,
                $tokensEstimate
            );

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
            $this->handleStreamError($e, $rateLimitService, $keyManager ?? null, $currentKeyId ?? null,
                $userId, $userRole, $selectedProvider, $modelName ?? 'gpt-oss-120b',
                $prompt, $hasImage, $usingFallback, $requestStartTime);
        }

        exit;
    }

    /**
     * Detect if prompt needs web search
     */
    private function detectWebSearchNeed(string $prompt): bool
    {
        $searchKeywords = [
            'search', 'google', 'find', 'look up', 'lookup', 'what is the latest',
            'current', 'today', 'news', 'recent', 'now', '2024', '2025',
            'price of', 'weather', 'stock', 'how much is', 'who won',
            'what happened', 'when did', 'where is', 'latest version',
            'release date', 'update', 'announced', 'breaking',
            'is it true', 'studies show', 'research', 'health', 'cause', 'effect'
        ];

        foreach ($searchKeywords as $kw) {
            if (stripos($prompt, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build system prompt
     */
    private function buildSystemPrompt(bool $hasImage, bool $hadImageInHistory, bool $isContinuation, bool $isAdminUser, bool $useLightpandaSearch = false): string
    {
        $systemPrompt = 'You are Ginto, an AI assistant created by Bob Reyes. '
            . 'You are powered by advanced language models and have web search capability. '
            . 'When asked about your identity, say you are Ginto, created by Oliver Bob. But when you\'re not asked about your identity, focus on answering the user\'s questions helpfully and accurately. '
            . 'RESPONSE STYLE: Be concise and direct. Use short, clear sentences. Avoid unnecessary filler words, lengthy introductions, or verbose explanations. '
            . 'Exception: When providing code, technical explanations, or when the user explicitly asks for detailed/comprehensive responses, give thorough answers. ';

        // Load agent instructions
        $agentInstructions = require __DIR__ . '/Includes/agent_instruct.php';

        // Check if user is a visitor (not logged in)
        $isVisitor = empty($_SESSION['user_id']);
        
        if ($isVisitor) {
            // Visitors cannot use any sandbox tools
            $systemPrompt .= $agentInstructions['visitor']();
        } else {
            // Logged-in users - check sandbox availability using UnifiedSandbox
            // UnifiedSandbox auto-detects backend (LXC default, Docker if configured)
            $sandboxAvailable = \Ginto\Helpers\UnifiedSandbox::isAvailable();
            $sandboxBackend = \Ginto\Helpers\UnifiedSandbox::getBackend();

            // Get sandbox ID from session OR look up from database
            $sandboxId = $_SESSION['sandbox_id'] ?? null;
            if (!$sandboxId && $this->db && !empty($_SESSION['user_id'])) {
                // Try to load sandbox_id from client_sandboxes table
                try {
                    $row = $this->db->get('client_sandboxes', ['sandbox_id'], ['user_id' => $_SESSION['user_id']]);
                    if (!empty($row['sandbox_id'])) {
                        $sandboxId = $row['sandbox_id'];
                        $_SESSION['sandbox_id'] = $sandboxId; // Cache in session
                    }
                } catch (\Throwable $_) {}
            }
            
            $hasSandbox = $sandboxAvailable && !empty($sandboxId) && \Ginto\Helpers\UnifiedSandbox::exists($sandboxId);

            if (!$sandboxAvailable) {
                $systemPrompt .= $agentInstructions['sandboxNotInstalled']($sandboxBackend);
            } elseif ($hasSandbox) {
                $isPremiumUser = false;
                if (!$isAdminUser && !empty($_SESSION['user_id']) && $this->db) {
                    try {
                        $activeSub = $this->db->get('subscriptions', ['id'], [
                            'user_id' => $_SESSION['user_id'],
                            'status' => 'active'
                        ]);
                        $isPremiumUser = !empty($activeSub);
                    } catch (\Throwable $_) {}
                }
                $systemPrompt .= $agentInstructions['withSandbox']($sandboxId, $isContinuation, $isAdminUser, $isPremiumUser, $sandboxBackend);
            } else {
                $systemPrompt .= $agentInstructions['noSandbox']($sandboxBackend);
            }
        }

        if (!$hasImage) {
            if ($useLightpandaSearch) {
                $systemPrompt .= 'When the user asks about current events, news, or information that would benefit from web search, use your lightpanda_search tool. '
                    . 'This tool searches the web and fetches content from top results. Call it with a clear search query. '
                    . 'Be efficient: request only 2-3 results per search. ';
            } else {
                $systemPrompt .= 'When the user asks about current events, news, or information that would benefit from web search, use your browser_search tool. '
                    . 'Be efficient: search only 3-5 most relevant sources, not more. '
                    . 'Keep your reasoning concise and focused. ';
            }

            if ($hadImageInHistory) {
                $systemPrompt .= 'Note: Earlier in this conversation, the user shared an image which you analyzed. '
                    . 'When they ask follow-up questions, refer to your previous analysis of that image. '
                    . 'Messages marked with [User shared an image] indicate when an image was attached. ';
            }
        } else {
            $systemPrompt .= 'You have vision capabilities. Analyze the image carefully and provide helpful, detailed responses about what you see. ';
        }

        $systemPrompt .= 'IMPORTANT: Always reserve enough tokens to provide a complete, well-formatted final answer.';

        return $systemPrompt;
    }

    /**
     * Calculate max tokens based on user role
     */
    private function calculateMaxTokens(string $userRole, bool $hasImage, ?string $imageDataUrl, bool $useLocalVision, $localLlmConfig): int
    {
        $maxTokensBase = (int)(getenv('MAX_TOKENS_BASE') ?: ($_ENV['MAX_TOKENS_BASE'] ?? 8192));
        $tokenPercentages = [
            'admin' => (int)(getenv('MAX_TOKENS_ADMIN_PERCENT') ?: ($_ENV['MAX_TOKENS_ADMIN_PERCENT'] ?? 100)),
            'user' => (int)(getenv('MAX_TOKENS_USER_PERCENT') ?: ($_ENV['MAX_TOKENS_USER_PERCENT'] ?? 25)),
            'visitor' => (int)(getenv('MAX_TOKENS_VISITOR_PERCENT') ?: ($_ENV['MAX_TOKENS_VISITOR_PERCENT'] ?? 10)),
        ];

        $tierTokenPercent = $tokenPercentages[strtolower($userRole)] ?? $tokenPercentages['visitor'];
        $maxTokens = (int)floor($maxTokensBase * ($tierTokenPercent / 100));
        $maxTokens = max(512, $maxTokens);

        if ($hasImage && $imageDataUrl) {
            if ($useLocalVision) {
                $maxTokens = min($maxTokens, $localLlmConfig->getVisionMaxTokens());
            } else {
                $maxTokens = min($maxTokens, 4096);
            }
        }

        return $maxTokens;
    }

    /**
     * Handle stream error
     */
    private function handleStreamError(
        \Throwable $e, $rateLimitService, $keyManager, $currentKeyId,
        string $userId, string $userRole, string $selectedProvider, string $modelName,
        string $prompt, bool $hasImage, bool $usingFallback, float $requestStartTime
    ): void {
        $errorMessage = $e->getMessage();
        $isRateLimitError = (
            stripos($errorMessage, 'rate limit') !== false ||
            stripos($errorMessage, 'rate_limit') !== false ||
            stripos($errorMessage, '429') !== false ||
            stripos($errorMessage, 'too many requests') !== false
        );

        if ($isRateLimitError && $keyManager && $currentKeyId) {
            $keyManager->markKeyRateLimited($currentKeyId, 60);
            $nextKey = $keyManager->getNextAvailableKey($currentKeyId);
            error_log("Rate limit hit on key {$currentKeyId}, next: " . ($nextKey ? $nextKey['id'] : 'none'));
        }

        // Log failed request
        $requestLatency = (int)((microtime(true) - $requestStartTime) * 1000);
        $rateLimitService->logRequest([
            'user_id' => $userId,
            'user_role' => $userRole,
            'provider' => $selectedProvider,
            'model' => $modelName,
            'tokens_input' => (int)(strlen($prompt) / 4),
            'tokens_output' => 0,
            'request_type' => $hasImage ? 'vision' : 'chat',
            'response_status' => 'error',
            'fallback_used' => $usingFallback ? 1 : 0,
            'latency_ms' => $requestLatency,
        ]);

        // User-friendly error message
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

        @ini_set('output_buffering', 'off');
        while (ob_get_level()) ob_end_flush();

        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
        }
        echo str_repeat(' ', 1024);
        flush();

        \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/chat', 'trace' => $e->getTraceAsString()]);
        echo "data: " . json_encode(['error' => $userError]) . "\n\n";
        flush();
    }

    /**
     * Helper: Fix malformed code blocks for Parsedown
     */
    public static function fixCodeBlockNewlines(string $content): string
    {
        $content = preg_replace('/```([a-zA-Z0-9+#]+)(?!\n)/', "```$1\n", $content);

        $phpOpen = '<' . '?php';
        $phpClose = '?' . '>';

        $content = preg_replace('/' . preg_quote($phpOpen, '/') . '(?=\/\/)/', $phpOpen . ' ', $content);
        $content = preg_replace('/' . preg_quote($phpOpen, '/') . '(?=[^\s\/])/', $phpOpen . "\n", $content);

        $content = preg_replace_callback(
            '/```php\s*(' . preg_quote($phpOpen, '/') . '[\s\S]*?)```/i',
            function($matches) use ($phpClose) {
                $code = $matches[1];
                if (!preg_match('/' . preg_quote($phpClose, '/') . '\s*$/', trim($code))) {
                    $code = rtrim($code) . "\n" . $phpClose;
                }
                return "```php\n" . $code . "\n```";
            },
            $content
        );

        return $content;
    }

    /**
     * Helper: Send SSE data chunk
     */
    public static function sendSSE(string $content, $parsedown): void
    {
        if ($content === '') return;

        try {
            $content = self::fixCodeBlockNewlines($content);
            if ($parsedown !== null) {
                $html = $parsedown->text($content);
            } else {
                $html = '<pre>' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            }
            echo "data: " . json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        } catch (\Throwable $_) {
            echo $content;
            flush();
        }
    }

    /**
     * Stream a complete response as SSE
     */
    public static function streamResponse(string $content): void
    {
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        while (ob_get_level()) ob_end_flush();
        ignore_user_abort(true);

        if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
        if (!headers_sent()) header('Cache-Control: no-cache');
        if (!headers_sent()) header('X-Accel-Buffering: no');
        echo str_repeat(' ', 1024);
        flush();

        $parsedown = self::getParsedown();
        $fixedContent = self::fixCodeBlockNewlines($content);
        $html = $parsedown ? $parsedown->text($fixedContent) : '<pre>' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        echo "data: " . json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    /**
     * Format reasoning text as timeline HTML
     */
    public static function formatReasoningHtml(string $reasoning): string
    {
        if (empty($reasoning)) return '';

        $createReasoningItem = fn($content) => '<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>' . htmlspecialchars(trim(preg_replace('/\n/', ' ', $content))) . '</p></div></div>';

        $paragraphs = array_filter(preg_split('/\n\n+/', $reasoning), fn($p) => trim($p));

        if (count($paragraphs) <= 1) {
            $paragraphs = array_filter(preg_split('/\n/', $reasoning), fn($p) => trim($p));
        }

        if (count($paragraphs) <= 1 && strlen(trim($reasoning)) > 100) {
            $text = preg_replace('/\s+/', ' ', trim($reasoning));
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

        return implode('', array_map($createReasoningItem, $paragraphs));
    }

    /**
     * Strip internal markers from content
     */
    public static function stripMarkers(string $content): string
    {
        // Remove citation markers
        $content = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $content);
        
        // Most aggressive pattern: any JSON object starting with {"query"
        // This catches all browser_search variations regardless of other fields
        $content = preg_replace('/\{"query"\s*:[^}]+\}/', '', $content);
        
        // Remove search/browse markers
        $content = preg_replace('/Search web\.\{[^}]+\}\.{0,3}/i', '', $content);
        $content = preg_replace('/Browse web\.\{[^}]+\}\.{0,3}/i', '', $content);
        $content = preg_replace('/Let\'s search[^.]*\./i', '', $content);
        $content = preg_replace('/We need to browse[^.]*\./i', '', $content);
        
        // Remove tool_call JSON patterns
        $content = preg_replace('/\{"tool_call"\s*:\s*\{[\s\S]*?\}\s*\}/', '', $content);
        
        // Remove image markdown - UI displays images from tool results, AI shouldn't duplicate
        // Matches ![alt text](url) and ![](url)
        $content = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $content);
        
        // Clean up leftover punctuation and whitespace
        $content = preg_replace('/\.{2,}/', '.', $content);
        // Only collapse multiple internal spaces, preserve single spaces
        $content = preg_replace('/  +/', ' ', $content);
        $content = preg_replace('/\s+\./', '.', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Don't trim - preserve leading/trailing spaces for word boundaries
        return $content;
    }

    /**
     * Initialize Parsedown for markdown rendering
     */
    public static function getParsedown()
    {
        $parsedown = null;
        if (class_exists('\ParsedownExtra')) {
            try { $parsedown = new \ParsedownExtra(); } catch (\Throwable $_) {}
        } elseif (class_exists('\Parsedown')) {
            try { $parsedown = new \Parsedown(); } catch (\Throwable $_) {}
        }
        return $parsedown;
    }

    /**
     * Prepare SSE headers and flush initial padding
     */
    public static function prepareSSE(): void
    {
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        while (ob_get_level()) ob_end_flush();
        ob_implicit_flush(true);
        ignore_user_abort(true);

        if (!headers_sent()) header('Content-Type: text/event-stream; charset=utf-8');
        if (!headers_sent()) header('Cache-Control: no-cache');
        if (!headers_sent()) header('X-Accel-Buffering: no');
        if (!headers_sent()) header('Connection: keep-alive');

        echo str_repeat(' ', 1024) . "\n\n";
        flush();
    }

    /**
     * Send final SSE response with HTML and reasoning
     */
    /**
     * Execute image generation with progress streaming
     */
    private function executeImageGeneration(string $prompt): array
    {
        $sdcpuBaseUrl = 'http://127.0.0.1:8888';
        $streamUrl = $sdcpuBaseUrl . '/api/generate-stream';
        
        if (empty(trim($prompt))) {
            return ['success' => false, 'error' => 'Prompt is required'];
        }
        
        // Helper to emit progress
        $emitProgress = function($progress, $message, $step = null, $totalSteps = null) {
            $data = [
                'image_progress' => true,
                'progress' => $progress,
                'message' => $message
            ];
            if ($step !== null) $data['step'] = $step;
            if ($totalSteps !== null) $data['total_steps'] = $totalSteps;
            echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        };
        
        $emitProgress(0, 'Connecting to FastSD CPU...');
        
        // Check health
        $healthCheck = @file_get_contents($sdcpuBaseUrl . '/api/health');
        if ($healthCheck === false) {
            return ['success' => false, 'error' => 'Image generation server is not available'];
        }
        
        $emitProgress(5, 'Server ready, starting generation...');
        
        $requestData = [
            'prompt' => $prompt,
            'width' => 512,
            'height' => 384,
            'num_inference_steps' => 4,
            'guidance_scale' => 1.0,
        ];
        
        // Use streaming endpoint for real-time progress
        $ch = curl_init($streamUrl);
        $responseBuffer = '';
        $lastProgress = 0;
        $finalResult = null;
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: text/event-stream'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$responseBuffer, &$lastProgress, &$finalResult, $emitProgress) {
                $responseBuffer .= $data;
                
                // Parse SSE events
                while (($pos = strpos($responseBuffer, "\n\n")) !== false) {
                    $chunk = substr($responseBuffer, 0, $pos);
                    $responseBuffer = substr($responseBuffer, $pos + 2);
                    
                    if (strpos($chunk, 'data: ') === 0) {
                        $json = substr($chunk, 6);
                        $event = @json_decode($json, true);
                        
                        if ($event) {
                            if (isset($event['step']) && isset($event['total_steps'])) {
                                // Real step progress from server
                                $step = $event['step'];
                                $total = $event['total_steps'];
                                $progress = 10 + (80 * $step / $total); // 10-90% range for steps
                                if ($progress > $lastProgress) {
                                    $lastProgress = $progress;
                                    $emitProgress(
                                        round($progress), 
                                        "Step {$step}/{$total}",
                                        $step,
                                        $total
                                    );
                                }
                            } elseif (isset($event['image'])) {
                                // Final result with image
                                $finalResult = $event;
                                $emitProgress(90, 'Processing image...');
                            }
                        }
                    }
                }
                
                return strlen($data);
            }
        ]);
        
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if (!$success || ($httpCode !== 200 && $httpCode !== 0)) {
            // Fallback to non-streaming endpoint
            $emitProgress(20, 'Fallback to standard generation...');
            
            $ch = curl_init($sdcpuBaseUrl . '/api/generate');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $finalResult = @json_decode($response, true);
        }
        
        if (!$finalResult || !isset($finalResult['image'])) {
            return ['success' => false, 'error' => 'Failed to generate image'];
        }
        
        $emitProgress(95, 'Saving image...');
        
        // Save image
        $storageDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/imagegen' : dirname(__DIR__, 2) . '/storage/imagegen';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        
        $safePrompt = preg_replace('/[^a-zA-Z0-9]/', '_', substr($prompt, 0, 30));
        $outputFile = date('Y-m-d_His') . '_sdcpu_' . $safePrompt . '_' . substr(md5(uniqid()), 0, 8) . '.png';
        $outputPath = $storageDir . '/' . $outputFile;
        
        $imageData = base64_decode($finalResult['image']);
        file_put_contents($outputPath, $imageData);
        
        $webUrl = '/storage/imagegen/' . $outputFile;
        
        $emitProgress(100, 'Complete!');
        
        return [
            'success' => true,
            'prompt' => $prompt,
            'model' => 'FastSD CPU (sd-turbo-openvino)',
            'images' => [
                [
                    'url' => $webUrl,
                    'width' => $finalResult['width'] ?? 512,
                    'height' => $finalResult['height'] ?? 512,
                ]
            ],
            'generation_time_ms' => $finalResult['generation_time_ms'] ?? null,
            'seed' => $finalResult['seed'] ?? null,
        ];
    }

    /**
     * Send final SSE response with HTML and reasoning
     */
    public static function sendFinalResponse(string $content, string $reasoning, $parsedown): void
    {
        if (!$content && !$reasoning) return;

        $html = '';
        if ($content) {
            $fixedContent = self::fixCodeBlockNewlines($content);
            $html = $parsedown ? $parsedown->text($fixedContent) : '<pre>' . htmlspecialchars($content) . '</pre>';
        }

        $reasoningHtml = self::formatReasoningHtml($reasoning);

        // Log response
        $logDir = dirname(__DIR__, 2) . '/../storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logEntry = date('Y-m-d H:i:s') . " [CHAT RESPONSE]\n";
        $logEntry .= "=== RAW CONTENT ===\n" . $content . "\n";
        $logEntry .= "=== REASONING ===\n" . $reasoning . "\n";
        $logEntry .= "==================\n\n";
        @file_put_contents($logDir . '/ginto.log', $logEntry, FILE_APPEND | LOCK_EX);

        echo "data: " . json_encode([
            'html' => $html,
            'reasoningHtml' => $reasoningHtml,
            'contentEmpty' => empty(trim($content)),
            'final' => true
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}

