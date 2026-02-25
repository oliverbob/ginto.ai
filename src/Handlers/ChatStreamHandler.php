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
        // Also allow central UserController check (some controllers/templates use that instead of session flags)
        try {
            if (!$isAdminUser && class_exists('Ginto\\Controllers\\UserController')) {
                $isAdminUser = (bool) \Ginto\Controllers\UserController::isAdmin();
            }
        } catch (\Throwable $_) {
            // ignore and keep session-derived value
        }
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

        // Read the incoming data - handle both form POST and JSON
        $jsonInput = null;
        if (empty($_POST['prompt'])) {
            $rawInput = file_get_contents('php://input');
            $jsonInput = json_decode($rawInput, true);
        }
        
        $prompt = $_POST['prompt'] ?? ($jsonInput['prompt'] ?? null) ?: 'Hello, how can you help me today?';

        // Handle repository description requests (fast path)
        if ($this->handleRepoDescription($prompt)) {
            exit;
        }

        // Check for image attachment
        $hasImage = (!empty($_POST['hasImage']) && $_POST['hasImage'] === '1') || (!empty($jsonInput['hasImage']));
        $imageDataUrl = $_POST['image'] ?? ($jsonInput['image'] ?? null);

        // Check for RAG document attachment (document was uploaded and processed server-side)
        $documentId = !empty($_POST['documentId']) ? (int)$_POST['documentId'] : (!empty($jsonInput['documentId']) ? (int)$jsonInput['documentId'] : null);
        $hasDocument = $documentId !== null && $documentId > 0;

        // Build conversation history
        $history = $this->buildHistory();
        $hadImageInHistory = $this->checkImageInHistory($history);

        // Handle session-selected Ollama provider
        // Prefer global admin selection (settings.llm_global_selection), then legacy session keys, then UI keys set by /api/models/set
        $sessionProvider = null;
        $sessionModel = null;
        try {
            if ($this->db) {
                $row = $this->db->get('settings', ['value'], ['key' => 'llm_global_selection']);
                if ($row && !empty($row['value'])) {
                    $decoded = json_decode($row['value'], true);
                    if (is_array($decoded) && !empty($decoded['provider'])) {
                        $sessionProvider = $decoded['provider'];
                        $sessionModel = $decoded['model'] ?? null;
                    }
                }
            }
        } catch (\Throwable $_) { /* ignore */ }

        if (empty($sessionProvider)) {
            $sessionProvider = $_SESSION['llm_provider_name'] ?? ($_SESSION['current_provider'] ?? null);
            $sessionModel = $_SESSION['llm_model'] ?? ($_SESSION['current_model'] ?? null);
        }

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
            $rateLimitService, $userRateLimiter, $db, $documentId
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
     * Truncate message array to keep outgoing payload under a byte limit for
     * specific providers (Groq is sensitive to oversized requests).
     *
     * Strategy: drop oldest non-system messages until payload fits.
     */
    private function truncateMessagesForProvider(array $messages, string $provider, int $limitBytes = null): array
    {
        if (strtolower($provider) !== 'groq') {
            return $messages;
        }

        $containsBase64Image = false;
        foreach ($messages as $msg) {
            $content = $msg['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $part) {
                $imageUrl = $part['image_url']['url'] ?? null;
                if (is_string($imageUrl) && str_starts_with($imageUrl, 'data:image/')) {
                    $containsBase64Image = true;
                    break 2;
                }
            }
        }

        $limit = $limitBytes ?? ($containsBase64Image ? 3500000 : 180000);

        $payload = ['model' => $messages[0]['model'] ?? null, 'messages' => $messages];
        $json = @json_encode($payload);
        if ($json === false) return $messages;

        // Keep the latest user message (typically the current turn with image)
        // so truncation never drops the actual prompt being answered.
        $protectedIndex = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $protectedIndex = $i;
                break;
            }
        }

        // Remove oldest non-system messages until under limit
        while (strlen($json) > $limit) {
            $removed = false;
            for ($i = 1; $i < count($messages); $i++) {
                if ($protectedIndex !== null && $i === $protectedIndex) {
                    continue;
                }
                if (($messages[$i]['role'] ?? '') !== 'system') {
                    array_splice($messages, $i, 1);
                    if ($protectedIndex !== null && $i < $protectedIndex) {
                        $protectedIndex--;
                    }
                    $removed = true;
                    break;
                }
            }
            if (!$removed) break; // nothing left to remove
            $payload['messages'] = $messages;
            $json = @json_encode($payload);
            if ($json === false) break;
        }

        if ($json !== false) {
            if (strlen($json) > $limit) {
                error_log('[ChatStream] Truncation incomplete: payload ' . strlen($json) . " bytes (limit {$limit})");
            } else {
                error_log('[ChatStream] Truncated messages for Groq to ' . strlen($json) . " bytes");
            }
        }

        return $messages;
    }

    /**
     * Log outgoing payloads to storage/logs for debugging oversized requests.
     * Masks common secret-looking keys and writes a timestamped full payload file
     * plus a short summary to the main log.
     */
    private function logOutgoingPayload(array $payload, string $providerName): void
    {
        try {
            $logsDir = dirname(__DIR__, 2) . '/../storage/logs';
            if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);

            // Mask sensitive-looking keys in a copy for the summary
            $masked = $this->maskSensitiveData($payload);

            $jsonFull = @json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $jsonMasked = @json_encode($masked, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $ts = date('Ymd_His') . '_' . str_pad((string)round(microtime(true) * 1000) % 1000, 3, '0', STR_PAD_LEFT);
            $shortPath = $logsDir . "/groq_payload_summary.log";
            $fullPath = $logsDir . "/groq_payload_full_{$ts}.json";

            // Write full payload to its own file (may be large)
            if ($jsonFull !== false) {
                // Limit the maximum size to avoid filling disk accidentally (500KB)
                if (strlen($jsonFull) > 512000) {
                    // Truncate but still save a useful portion
                    $trunc = substr($jsonFull, 0, 512000) . "\n...[TRUNCATED - payload too large]";
                    @file_put_contents($fullPath, $trunc, LOCK_EX);
                } else {
                    @file_put_contents($fullPath, $jsonFull, LOCK_EX);
                }
            }

            $summaryEntry = date('c') . " provider={$providerName} size=" . (is_string($jsonFull) ? strlen($jsonFull) : 0) . " bytes file=" . basename($fullPath) . " payload_preview=" . substr($jsonMasked, 0, 2000) . "\n";
            @file_put_contents($shortPath, $summaryEntry, FILE_APPEND | LOCK_EX);
            error_log('[ChatStream] Outgoing payload logged to ' . $fullPath . ' (summary appended)');
        } catch (\Throwable $_) {
            // Never let logging failures interrupt chat flow
            error_log('[ChatStream] Failed to write outgoing payload log: ' . $_->getMessage());
        }
    }

    private function maskSensitiveData($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $lower = strtolower((string)$k);
                if (strpos($lower, 'key') !== false || strpos($lower, 'token') !== false || strpos($lower, 'secret') !== false || strpos($lower, 'authorization') !== false || strpos($lower, 'api') !== false) {
                    $out[$k] = '[MASKED]';
                    continue;
                }
                $out[$k] = $this->maskSensitiveData($v);
            }
            return $out;
        }
        // For strings, truncate very long values for the summary
        if (is_string($data) && strlen($data) > 2000) {
            return substr($data, 0, 2000) . '...[TRUNC]';
        }
        return $data;
    }

    private function emitAdminLogEvent(bool $isAdminUser, string $message, string $provider, string $model): void
    {
        if (!$isAdminUser) {
            return;
        }

        echo "data: " . json_encode([
            'admin_log' => true,
            'message' => $message,
            'provider' => $provider,
            'model' => $model,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    private function isPrivateOrLocalHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || $host === 'host.docker.internal' || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }

    private function localPathFromPublicImagePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/storage/chat_images/')) {
            return STORAGE_PATH . '/chat_images/' . ltrim(substr($path, strlen('/storage/chat_images/')), '/');
        }
        if (str_starts_with($path, '/storage/generated_images/')) {
            return STORAGE_PATH . '/generated_images/' . ltrim(substr($path, strlen('/storage/generated_images/')), '/');
        }
        if (str_starts_with($path, '/storage/imagegen/')) {
            return STORAGE_PATH . '/imagegen/' . ltrim(substr($path, strlen('/storage/imagegen/')), '/');
        }
        if (str_starts_with($path, '/assets/uploads/')) {
            return ROOT_PATH . '/public/assets/uploads/' . ltrim(substr($path, strlen('/assets/uploads/')), '/');
        }

        return null;
    }

    private function dataUrlFromLocalImagePath(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $size = @filesize($path);
        if (!is_int($size) || $size <= 0) {
            return null;
        }

        if ($size > 3 * 1024 * 1024) {
            return null;
        }

        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/jpeg';
        if (!str_starts_with((string)$mime, 'image/')) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    private function prepareVisionImageInput(string $imageInput, array &$adminLogEvents = []): string
    {
        $resolved = trim($imageInput);
        if ($resolved === '') {
            return $resolved;
        }

        if (str_starts_with($resolved, 'data:image/')) {
            $adminLogEvents[] = '[vision] image input already data URL';
            return $resolved;
        }

        if (str_starts_with($resolved, '/')) {
            $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
                ? 'https'
                : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resolved = $scheme . '://' . $host . $resolved;
        }

        $parts = @parse_url($resolved);
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');

        if ($host !== '' && $this->isPrivateOrLocalHost($host)) {
            $localPath = $this->localPathFromPublicImagePath($path);
            if ($localPath) {
                $dataUrl = $this->dataUrlFromLocalImagePath($localPath);
                if ($dataUrl) {
                    $adminLogEvents[] = '[vision] converted local URL to data URL for provider reachability';
                    return $dataUrl;
                }
                $adminLogEvents[] = '[vision] local URL detected but conversion to data URL failed';
            } else {
                $adminLogEvents[] = '[vision] local URL path not mappable to storage path';
            }
        }

        return $resolved;
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
        $rateLimitService, $userRateLimiter, $db, ?int $documentId = null
    ): void {
        $adminLogEvents = [];

        // Respect both legacy llm_* session keys and the UI's current_* keys
        $sessionProvider = $_SESSION['llm_provider_name'] ?? ($_SESSION['current_provider'] ?? null);
        $sessionModel = $_SESSION['llm_model'] ?? ($_SESSION['current_model'] ?? null);

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
        $cloudProviders = ['cerebras', 'groq', 'openai', 'anthropic', 'together', 'fireworks', 'ginto_tunnel'];
        if ($sessionProvider && $sessionModel && in_array($sessionProvider, $cloudProviders, true)) {
            $sessionCloudProvider = $sessionProvider;
            $sessionCloudModel = $sessionModel;
        }

        // Debug: log session-selected provider/model for tracing
        error_log(sprintf("[ChatStream-debug] sessionProvider=%s sessionModel=%s sessionCloudProvider=%s sessionCloudModel=%s selectedProviderInitial=%s",
            $sessionProvider ?? 'null', $sessionModel ?? 'null', $sessionCloudProvider ?? 'null', $sessionCloudModel ?? 'null', $selectedProvider ?? 'null'
        ));

        try {
            $keyManager = new \App\Core\ProviderKeyManager($db);
            $currentKeyId = null;

            // If the session belongs to a logged-in user and they have a personal API key,
            // prefer using that key (and lift per-user limits handled in UserRateLimiter).
            $userProvidedKey = null;
            if (!empty($userIdSession)) {
                // If user selected a provider explicitly, prefer their key for that provider
                if (!empty($sessionCloudProvider)) {
                    $userProvidedKey = $keyManager->getUserKey($sessionCloudProvider, (int)$userIdSession);
                }
                // Otherwise try any user-owned key
                if (!$userProvidedKey) {
                    $userProvidedKey = $keyManager->getUserFirstKey((int)$userIdSession);
                }
                if ($userProvidedKey) {
                    $apiKey = $userProvidedKey['api_key'];
                    $currentKeyId = $userProvidedKey['id'];
                    $selectedProvider = $userProvidedKey['provider'];
                    error_log(sprintf('[ChatStream] Using user-owned API key id=%d provider=%s for user=%s', $currentKeyId, $selectedProvider, $userIdSession));
                }
            }

            // Detect web search needs
            $needsWebSearch = $this->detectWebSearchNeed($prompt);

            // Respect explicit opt-in for automatic web searches.
            // Default behavior: do NOT run automatic web searches unless
            // environment variable AUTO_WEB_SEARCH is set to '1' or the
            // user explicitly asks the agent to search the web in the prompt.
            $autoWebSearch = strtolower(getenv('AUTO_WEB_SEARCH') ?: ($_ENV['AUTO_WEB_SEARCH'] ?? '0'));
            if ($autoWebSearch !== '1') {
                $explicitSearch = (bool) preg_match(
                    '/\b(search( the)? web|web search|look up|find information|search for|please search|please look up)\b/i',
                    $prompt
                );
                if (!$explicitSearch) {
                    error_log('[ChatStream] AUTO_WEB_SEARCH disabled and no explicit search request found; skipping web search.');
                    $needsWebSearch = false;
                } else {
                    error_log('[ChatStream] Explicit web search requested in prompt; allowing web search despite AUTO_WEB_SEARCH setting.');
                }
            } else {
                error_log('[ChatStream] AUTO_WEB_SEARCH=1; automatic web search allowed.');
            }
            
            // Check search provider preference (lightpanda or groq)
            // When using Lightpanda, we handle search server-side so ANY provider works
            $searchProvider = strtolower(getenv('SEARCH_PROVIDER') ?: ($_ENV['SEARCH_PROVIDER'] ?? 'lightpanda'));
            $useLightpandaSearch = $searchProvider === 'lightpanda' && \Ginto\Handlers\LightpandaSearchHandler::isAvailable();
            
            // Only require Groq for web search if using Groq's built-in browser_search
            // With Lightpanda, any provider works since we handle search ourselves

            // Local LLM config
            $localLlmConfig = \App\Core\LLM\LocalLLMConfig::getInstance();
            $forceGroqVisionForAllModels = $hasImage && strtolower(getenv('GROQ_VISION_FOR_ALL_MODELS') ?: ($_ENV['GROQ_VISION_FOR_ALL_MODELS'] ?? 'false')) === 'true';
            $canUseLocalVision = $hasImage && !$forceGroqVisionForAllModels && $localLlmConfig->isEnabled() && $localLlmConfig->isVisionServerHealthy();

            $requiresGroq = $needsWebSearch && !$useLightpandaSearch;
            $requiresCloudVision = $hasImage && !$canUseLocalVision;
            
            // Check if user's selected model is vision-capable
            $visionCapableModels = [
                'groq' => ['llama-3.2-11b-vision-preview', 'llama-3.2-90b-vision-preview', 'meta-llama/llama-4-scout-17b-16e-instruct', 'meta-llama/llama-4-maverick-17b-128e-instruct'],
                'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4-vision-preview'],
                'together' => ['meta-llama/Llama-Vision-Free', 'meta-llama/Llama-3.2-11B-Vision-Instruct-Turbo', 'meta-llama/Llama-3.2-90B-Vision-Instruct-Turbo'],
                'cerebras' => [], // Cerebras doesn't have vision models
            ];
            
            $userSelectedVisionModel = false;
            if ($sessionCloudModel && $sessionCloudProvider) {
                $providerVisionModels = $visionCapableModels[$sessionCloudProvider] ?? [];
                $userSelectedVisionModel = in_array($sessionCloudModel, $providerVisionModels, true);
            }

            // Select API key and provider
            $apiKey = null;
            // Prefer session-selected cloud provider first (DB key then .env)
            if ($sessionCloudProvider) {
                $sessionKeyData = $keyManager->getAvailableKey($sessionCloudProvider);
                if ($sessionKeyData) {
                    $apiKey = $sessionKeyData['api_key'];
                    $currentKeyId = $sessionKeyData['id'];
                    $selectedProvider = $sessionCloudProvider;
                } else {
                    // Try environment variable for the session provider
                    $envKeyName = strtoupper($sessionCloudProvider) . '_API_KEY';
                    $apiKey = getenv($envKeyName) ?: ($_ENV[$envKeyName] ?? '');
                    if ($apiKey) {
                        $selectedProvider = $sessionCloudProvider;
                    } else {
                        // No key available for the user's chosen provider; clear session selection so fallback logic can run
                        $sessionCloudProvider = null;
                        $sessionCloudModel = null;
                    }
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
                    // If user explicitly selected a different provider via session
                    // prefer an environment API key for that provider (if present)
                    // over the DB-selected key. This makes session choice win
                    // when env keys are configured but DB contains keys for a
                    // different provider (common when DB only has one provider).
                    if (!empty($sessionCloudProvider)) {
                        $envKeyName = strtoupper($sessionCloudProvider) . '_API_KEY';
                        $envKeyVal = getenv($envKeyName) ?: ($_ENV[$envKeyName] ?? '');
                        if (!empty($envKeyVal)) {
                            $apiKey = $envKeyVal;
                            $selectedProvider = $sessionCloudProvider;
                            $currentKeyId = null;
                        } else {
                            $apiKey = $keyData['api_key'];
                            $currentKeyId = $keyData['id'];
                            $selectedProvider = $keyData['provider'];
                        }
                    } else {
                        $apiKey = $keyData['api_key'];
                        $currentKeyId = $keyData['id'];
                        $selectedProvider = $keyData['provider'];
                    }
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
                'openai' => [
                    'vision' => 'gpt-4o',
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

            // Determine vision provider and model for cloud vision
            $visionProvider = $selectedProvider;
            $visionApiKey = $apiKey;
            $directVisionInMainChat = false;
            
            if ($useLocalVision && $hasImage && $imageDataUrl) {
                $modelName = $localLlmConfig->getVisionModel();
                $directVisionInMainChat = true;
                $adminLogEvents[] = '[vision] using local vision model: ' . $modelName;
            } elseif ($hasImage && $imageDataUrl) {
                // Cloud vision - user already selected a vision model, or we need to find one
                if ($forceGroqVisionForAllModels) {
                    $modelName = $modelMapping['groq']['vision'];
                    $visionProvider = 'groq';
                    $directVisionInMainChat = false;
                    $groqKeyData = $keyManager->getAvailableKey('groq');
                    if ($groqKeyData) {
                        $visionApiKey = $groqKeyData['api_key'];
                    } else {
                        $visionApiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
                    }
                    error_log("[ChatStream] GROQ_VISION_FOR_ALL_MODELS enabled; pre-analyzing image with Groq: $modelName");
                    $adminLogEvents[] = '[vision] GROQ_VISION_FOR_ALL_MODELS enabled, pre-analyzing with groq/' . $modelName;
                } elseif ($userSelectedVisionModel && $sessionCloudModel) {
                    // User selected a vision-capable model, use it with already-selected provider/key
                    $modelName = $sessionCloudModel;
                    $visionProvider = $selectedProvider;
                    $visionApiKey = $apiKey;
                    $directVisionInMainChat = true;
                    error_log("[ChatStream] Using user's vision model: $modelName on $visionProvider");
                    $adminLogEvents[] = '[vision] using selected vision model: ' . $visionProvider . '/' . $modelName;
                } elseif (!empty($visionCapableModels[$selectedProvider])) {
                    // Selected provider has vision models, use its default vision model
                    $modelName = $modelMapping[$selectedProvider]['vision'] ?? $visionCapableModels[$selectedProvider][0];
                    $visionProvider = $selectedProvider;
                    $visionApiKey = $apiKey;
                    $directVisionInMainChat = true;
                    error_log("[ChatStream] Using provider's vision model: $modelName on $visionProvider");
                    $adminLogEvents[] = '[vision] using provider default vision model: ' . $visionProvider . '/' . $modelName;
                } else {
                    // Provider doesn't support vision (e.g., Cerebras), fall back to Groq
                    $modelName = $modelMapping['groq']['vision'];
                    $visionProvider = 'groq';
                    // Get Groq API key from DB first, then .env
                    $groqKeyData = $keyManager->getAvailableKey('groq');
                    if ($groqKeyData) {
                        $visionApiKey = $groqKeyData['api_key'];
                    } else {
                        $visionApiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
                    }
                    error_log("[ChatStream] Provider $selectedProvider has no vision, falling back to Groq: $modelName");
                    $adminLogEvents[] = '[vision] provider has no vision, pre-analyzing with groq/' . $modelName;
                }
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

            // Write selection to model.log (one level above repo in storage/) for diagnostics
            try {
                $modelLogPath = dirname(__DIR__, 2) . '/../storage/model.log';
                $entry = date('Y-m-d H:i:s') . " selectedProvider={$selectedProvider} selectedModel={$modelName} sessionProvider=" . ($sessionProvider ?? 'null') . " sessionModel=" . ($sessionModel ?? 'null') . " sessionCloudProvider=" . ($sessionCloudProvider ?? 'null') . " sessionCloudModel=" . ($sessionCloudModel ?? 'null') . "\n";
                @file_put_contents($modelLogPath, $entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $_) {
                // ignore logging failures
            }

            // Create provider instance
            $selectedProviderBaseUrl = $this->resolveOpenAiCompatibleProviderBaseUrl($selectedProvider, $db, $userIdSession, $isAdminUser);
            if ($useLocalVision && $hasImage) {
                $config = $localLlmConfig->getVisionProviderConfig();
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
                    'api_key' => $config['api_key'],
                    'model' => $config['model'],
                    'base_url' => $config['base_url'],
                ]);
            } elseif ($hasImage && $imageDataUrl && !$useLocalVision && ($forceGroqVisionForAllModels || !$directVisionInMainChat)) {
                // Cloud vision - call the vision-capable provider (Groq) FIRST,
                // attaching the current conversation history so the vision model
                // can use context when analyzing the image. Then continue the
                // main chat on the originally selected non-vision provider.

                // Save main provider/model/key so we can continue the chat there
                $mainProviderName = $selectedProvider;
                $mainApiKey = $apiKey;
                $mainModelName = $useLocalLlm
                    ? $localLlmConfig->getReasoningModel()
                    : ($sessionCloudModel ?? $defaultModel ?? ($selectedProvider === 'cerebras' ? 'gpt-oss-120b' : ($selectedProvider === 'groq' ? 'llama-3.3-70b-versatile' : 'llama-3.3-70b-versatile')));

                // Vision model (already computed into $modelName) and Groq key
                $visionModelName = $modelName;
                $visionProviderName = $visionProvider;

                // Build messages for vision call: include a system prompt and the full history
                $visionMessages = [];
                $visionSystem = $this->buildSystemPrompt(true, $hadImageInHistory, false, $isAdminUser, false);
                $visionMessages[] = ['role' => 'system', 'content' => $visionSystem];
                // Do NOT attach full conversation history to the vision model.
                // Vision model should only receive the image + prompt so it focuses
                // on visual analysis and does not consume large context windows.

                // Add the user's current prompt and actual image payload (multimodal)
                // IMPORTANT: Vision models must receive image_url content, not just a text placeholder.
                $visionUserContent = [
                    ['type' => 'text', 'text' => $prompt]
                ];

                if (is_string($imageDataUrl) && $imageDataUrl !== '') {
                    $resolvedImageUrl = $this->prepareVisionImageInput($imageDataUrl, $adminLogEvents);

                    $visionUserContent[] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => $resolvedImageUrl],
                    ];
                    $adminLogEvents[] = '[vision] built multimodal payload with image_url type=' . (str_starts_with($resolvedImageUrl, 'data:image/') ? 'data-url' : 'url');
                }

                $visionMessages[] = ['role' => 'user', 'content' => $visionUserContent];

                try {
                    $visionProviderInstance = new \App\Core\LLM\Providers\OpenAICompatibleProvider('groq', [
                        'api_key' => $visionApiKey,
                        'model' => $visionModelName,
                    ]);

                    // Request a concise analysis from the vision model
                    $visionResp = $visionProviderInstance->chat($visionMessages, [], ['max_tokens' => 1024, 'model' => $visionModelName]);
                    $visionAnalysis = trim((string)$visionResp->getContent());
                    if (!empty($visionAnalysis)) {
                        // Prepend analysis as a system message so the main model can use it
                        $analysisMsg = ['role' => 'system', 'content' => "[Image analysis from {$visionProviderName} - model {$visionModelName}]:\n" . $visionAnalysis];
                        array_unshift($history, $analysisMsg);
                        error_log("[ChatStream] Vision analysis attached to history ({$visionProviderName}): " . substr($visionAnalysis, 0, 200));
                        $adminLogEvents[] = '[vision] pre-analysis success, chars=' . strlen($visionAnalysis);
                    } else {
                        error_log('[ChatStream] Vision analysis returned empty content');
                        $adminLogEvents[] = '[vision] pre-analysis returned empty content';
                    }
                } catch (\Throwable $ve) {
                    error_log('[ChatStream] Vision analysis failed: ' . $ve->getMessage());
                    $adminLogEvents[] = '[vision] pre-analysis failed: ' . $ve->getMessage();
                }

                // Continue with the main (non-vision) provider using the original selection
                $selectedProvider = $mainProviderName;
                $apiKey = $mainApiKey;
                $modelName = $mainModelName;
                $selectedProviderBaseUrl = $this->resolveOpenAiCompatibleProviderBaseUrl($selectedProvider, $db, $userIdSession, $isAdminUser);
                $mainProviderConfig = [
                    'api_key' => $apiKey,
                    'model' => $modelName,
                ];
                if (!empty($selectedProviderBaseUrl)) {
                    $mainProviderConfig['base_url'] = $selectedProviderBaseUrl;
                }
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider($selectedProvider, $mainProviderConfig);
            } elseif ($useLocalLlm) {
                $config = $localLlmConfig->getReasoningProviderConfig();
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
                    'api_key' => $config['api_key'],
                    'model' => $config['model'],
                    'base_url' => $config['base_url'],
                ]);
            } else {
                $providerConfig = [
                    'api_key' => $apiKey,
                    'model' => $modelName,
                ];
                if (!empty($selectedProviderBaseUrl)) {
                    $providerConfig['base_url'] = $selectedProviderBaseUrl;
                }
                $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider($selectedProvider, $providerConfig);
            }

            // Prepare SSE
            self::prepareSSE();

            if ($isAdminUser && !empty($adminLogEvents)) {
                foreach ($adminLogEvents as $logMessage) {
                    echo "data: " . json_encode([
                        'admin_log' => true,
                        'message' => $logMessage,
                        'provider' => $selectedProvider,
                        'model' => $modelName,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }
            }

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
            
            // If we have web search context, append it to system prompt with explicit instruction
            if (!empty($webSearchContext)) {
                $systemPrompt .= "\n\n## Web Search Results\n"
                    . "The following information was found from web search. Use this to answer the user's question.\n"
                    . "IMPORTANT: Do NOT use web_fetch or any web tools to re-fetch these URLs - the content is already provided below.\n\n" 
                    . $webSearchContext;
            }

            // Inject RAG document context if user has uploaded documents
            if (!empty($userIdSession)) {
                $ragService = new DocumentRagService($db);
                
                // Determine max context size based on model's context window
                $ragMaxLength = $this->getModelRagLimit($modelName, $selectedProvider);
                
                // If a specific document was attached to this message, prioritize it
                if ($documentId !== null && $documentId > 0) {
                    $docText = $ragService->getDocumentText((int)$userIdSession, $documentId, $ragMaxLength);
                    if (!empty($docText)) {
                        $systemPrompt .= "\n\n## Attached Document Content (RAG)\n"
                            . "The user has attached a document to this message. Use this content to answer their question:\n\n"
                            . $docText . "\n\n---\n";
                    }
                } else {
                    // Otherwise, include all user's uploaded documents as context
                    $ragContext = $ragService->getDocumentContext((int)$userIdSession, null, $ragMaxLength);
                    if (!empty($ragContext)) {
                        $systemPrompt .= "\n\n" . $ragContext;
                    }
                }
            }

            // Build messages
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $hm) {
                if (!empty($hm['role']) && isset($hm['content'])) {
                    // Exclude image markers from history sent to the chat model
                    if (is_string($hm['content']) && strpos($hm['content'], '[User shared an image]') === 0) {
                        continue;
                    }
                    $messages[] = ['role' => $hm['role'], 'content' => $hm['content']];
                }
            }

            // Add user message
            if ($hasImage && $imageDataUrl && $directVisionInMainChat) {
                $resolvedImageUrl = $this->prepareVisionImageInput((string)$imageDataUrl, $adminLogEvents);
                $this->emitAdminLogEvent(
                    $isAdminUser,
                    '[vision] dispatching multimodal main request image_url=' . (str_starts_with($resolvedImageUrl, 'data:image/') ? 'data-url' : 'url'),
                    $selectedProvider,
                    $modelName
                );

                $messages[] = [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $resolvedImageUrl]],
                    ],
                ];
            } else {
                // Non-vision providers use pre-analysis only
                $messages[] = ['role' => 'user', 'content' => $prompt];
            }

            // Calculate max tokens (pass provider for provider-specific limits)
            $maxTokens = $this->calculateMaxTokens($userRole, $hasImage, $imageDataUrl, $useLocalVision, $localLlmConfig ?? null, $selectedProvider);

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
                
                // Add Lightpanda web tools for URL fetching ONLY if web search was NOT performed
                // When web search runs, it already fetches content from each URL, so web_fetch would be redundant
                // web_fetch is for reading specific URLs the user provides (without web search)
                // web_extract_links is for extracting links from pages
                if (empty($webSearchContext)) {
                    $webTools = $mcpUnifier->getToolsAsOpenAI([
                        'web_fetch',         // Fetch URL content with Lightpanda browser
                        'web_extract_links', // Extract links from a page
                    ]);
                    $tools = array_merge($tools, $webTools);
                    error_log("[ChatStream] Web tools loaded: " . count($webTools) . " tools (no web search performed)");
                } else {
                    error_log("[ChatStream] Web tools NOT loaded - web search already fetched content");
                }
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
            
            // Filter out disabled tools from session
            $disabledTools = $_SESSION['disabled_tools'] ?? [];
            if (!empty($disabledTools) && !empty($tools)) {
                $beforeCount = count($tools);
                $tools = array_filter($tools, function($tool) use ($disabledTools) {
                    $toolName = $tool['function']['name'] ?? '';
                    return !in_array($toolName, $disabledTools, true);
                });
                $tools = array_values($tools); // Re-index array
                error_log("[ChatStream] Filtered out " . ($beforeCount - count($tools)) . " disabled tools");
            }
            
            error_log("[ChatStream] Total tools: " . count($tools));
            
            // NOTE: Web search is now executed BEFORE the LLM call (see above)
            // Lightpanda tools are now available for on-demand URL fetching

            // Agentic loop - keep going until model finishes (no more tool_calls)
            $maxIterations = 15;
            $iteration = 0;
            $accumulatedContent = '';
            $accumulatedReasoning = '';

            try {
            // Before entering the agent loop, measure payload size and apply
            // provider-specific truncation (Groq requires smaller payloads).
            try {
                $providerNameForLimit = method_exists($provider, 'getName') ? $provider->getName() : ($selectedProvider ?? 'groq');
                $previewPayload = ['model' => $modelName, 'messages' => $messages, 'tools' => $tools];
                $previewJson = @json_encode($previewPayload) ?: '';
                $previewSize = strlen($previewJson);
                error_log("[ChatStream] Prepared payload size for {$providerNameForLimit}: {$previewSize} bytes");

                // Log the actual outgoing payload to storage/logs for post-mortem
                try {
                    if (strtolower($providerNameForLimit) === 'groq' && !$hasImage) {
                        $this->logOutgoingPayload($previewPayload, 'groq');
                    }
                } catch (\Throwable $_) {
                    error_log('[ChatStream] Failed to log outgoing payload: ' . $_->getMessage());
                }

                // If Groq and payload is large, truncate messages conservatively
                $groqLimitBytes = ($hasImage && $directVisionInMainChat) ? 3500000 : 180000;
                $groqTriggerBytes = ($hasImage && $directVisionInMainChat) ? 3600000 : 200000;
                if (strtolower($providerNameForLimit) === 'groq' && $previewSize > $groqTriggerBytes) {
                    $messages = $this->truncateMessagesForProvider($messages, 'groq', $groqLimitBytes);
                    $afterJson = @json_encode(['model' => $modelName, 'messages' => $messages, 'tools' => $tools]) ?: '';
                    error_log('[ChatStream] Payload truncated for Groq; new size: ' . strlen($afterJson) . ' bytes');

                    // If still too large, take more aggressive steps: shorten system prompt and remove tools
                    $limitAggressive = ($hasImage && $directVisionInMainChat) ? 3400000 : 170000;
                    if (strlen($afterJson) > $limitAggressive) {
                        // Shorten system prompt (first message expected to be system)
                        if (!empty($messages) && isset($messages[0]['role']) && $messages[0]['role'] === 'system' && isset($messages[0]['content'])) {
                            $orig = (string)$messages[0]['content'];
                            $short = mb_substr($orig, 0, 3000); // keep ~3k chars
                            $short .= "\n\n[TRUNCATED SYSTEM PROMPT FOR GROQ DUE TO SIZE LIMITS]";
                            $messages[0]['content'] = $short;
                        }

                        // Drop tools to reduce token usage
                        $tools = [];

                        $afterJson = @json_encode(['model' => $modelName, 'messages' => $messages, 'tools' => $tools]) ?: '';
                        error_log('[ChatStream] Aggressively reduced payload for Groq; new size: ' . strlen($afterJson) . ' bytes (tools dropped, system prompt shortened)');
                    }
                }
            } catch (\Throwable $e) {
                error_log('[ChatStream] Payload sizing error: ' . $e->getMessage());
            }

            while ($iteration < $maxIterations) {
                $iteration++;
                
                // Debug: log iteration
                error_log("[ChatStream] Tool loop iteration $iteration");
                $this->emitAdminLogEvent($isAdminUser, '[chat] sending request iteration=' . $iteration . ' messages=' . count($messages) . ' tools=' . count($tools), $selectedProvider, $modelName);
                
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
                        } elseif (in_array($toolName, ['web_fetch', 'web_extract_links'])) {
                            // Special handling for Lightpanda web tools with activity streaming
                            $result = $this->executeWebTool($toolName, $toolArgs);
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

            // Log model response to storage/logs/model.log (one level above repo)
            try {
                $logsDir = dirname(__DIR__, 2) . '/../storage/logs';
                if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);
                $modelLogPath = $logsDir . '/model.log';
                $responseSnippet = str_replace("\n", ' ', trim(substr($finalContent, 0, 2000)));
                $entry = date('Y-m-d H:i:s') . ' provider=' . ($selectedProvider ?? 'unknown') . ' model=' . ($modelName ?? 'unknown') . ' response_snippet="' . $responseSnippet . "" . "\n";
                @file_put_contents($modelLogPath, $entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $_) {
                // ignore logging failures
            }

            self::sendFinalResponse($finalContent, $accumulatedReasoning, $parsedown, $selectedProvider ?? null, $modelName ?? null, $isAdminUser ?? false);

            // Log successful request
            $requestLatency = (int)((microtime(true) - $requestStartTime) * 1000);
            $tokensEstimate = (int)(strlen($prompt) / 4) + (int)(strlen($accumulatedContent) / 4);

            if ($currentKeyId) {
                $keyManager->markKeyUsed($currentKeyId);
            }

            $userRateLimiter->recordUsage(
                $userIdSession ? (int)$userIdSession : null,
                $visitorIp,
                $tokensEstimate,
                $currentKeyId ?? null
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
        $lowerPrompt = strtolower($prompt);
        
        // Skip web search for personal profile questions - the model already has this info
        $personalPatterns = [
            'my name', 'my username', 'my email', 'my phone', 'my country',
            'my full name', 'my fullname', 'my profile', 'my account',
            'do you know my', 'what is my', 'what\'s my', 'whats my',
            'tell me my', 'show me my', 'my referral', 'my link'
        ];
        
        foreach ($personalPatterns as $pattern) {
            if (stripos($lowerPrompt, $pattern) !== false) {
                return false;
            }
        }
        
        // Skip web search for Ginto product/package/income questions - model has this info
        $gintoPatterns = [
            'package 250', 'package 1000', 'package 5000', 'package 10000', 'package 50000',
            '₱250', '₱1000', '₱1,000', '₱5000', '₱5,000', '₱10000', '₱10,000', '₱50000', '₱50,000',
            'p250', 'p1000', 'p1,000', 'p5000', 'p5,000', 'p10000', 'p10,000', 'p50000', 'p50,000',
            'starter plan', 'professional plan', 'executive plan', 'gold package', 'platinum package',
            'starter tier', 'professional tier', 'executive tier',
            'ginto plan', 'ginto package', 'ginto subscription', 'ginto income', 'ginto earning',
            'potential income', 'potential earning', 'income projection', 'commission',
            'how much can i earn', 'how much will i earn', 'how much income', 'how much commission',
            'subscription plan', 'monthly subscription', 'power 10', 'power10',
            'warlito clemente', 'warlito', 'clemente',
            // Video/homepage questions - model has this info
            'the video', 'this video', 'that video', 'homepage video', 'youtube video',
            'video on the homepage', 'video on homepage', 'datacenter video', 'infrastructure video',
            'why is there a video', 'what is the video', 'about the video', 'video about',
            'ginto vision', 'ginto\'s vision', 'ai infrastructure', 'philippines ai'
        ];
        
        foreach ($gintoPatterns as $pattern) {
            if (stripos($lowerPrompt, $pattern) !== false) {
                return false;
            }
        }
        
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

        // Always add product/membership tier information so the model can answer questions about plans
        $systemPrompt .= $agentInstructions['productInfo']();

        // Add user's profile details and referral link if they are logged in
        if (!empty($_SESSION['user_id']) && $this->db) {
            try {
                $userData = $this->db->get('users', [
                    'public_id',
                    'username',
                    'email',
                    'phone',
                    'fullname',
                    'country'
                ], ['id' => $_SESSION['user_id']]);
                
                if ($userData) {
                    // Add user profile section
                    $systemPrompt .= "\n\n## YOUR PROFILE\n"
                        . "You are chatting with a logged-in user. Here are their details:\n"
                        . "- **Username:** " . ($userData['username'] ?? 'Not set') . "\n"
                        . "- **Email:** " . ($userData['email'] ?? 'Not set') . "\n"
                        . "- **Phone:** " . ($userData['phone'] ?? 'Not set') . "\n"
                        . "- **Full Name:** " . ($userData['fullname'] ?? 'Not set') . "\n"
                        . "- **Country:** " . ($userData['country'] ?? 'Not set') . "\n"
                        . "You may address them by their name or username when appropriate to make the conversation more personal.\n";
                    
                    // Add referral link section
                    if (!empty($userData['public_id'])) {
                        $referralLink = 'https://ginto.ai/register?ref=' . urlencode($userData['public_id']);
                        $systemPrompt .= "\n## YOUR REFERRAL LINK\n"
                            . "The current user's personal referral link is: {$referralLink}\n"
                            . "When the user asks for their referral link, share link, or wants to invite others, "
                            . "you MUST output the link in this EXACT format with copy button:\n\n"
                            . "**Your Referral Link:**\n"
                            . "```\n{$referralLink}\n```\n"
                            . "👆 Click the copy button above to copy your link!\n\n"
                            . "Or click here: [{$referralLink}]({$referralLink})\n\n"
                            . "This shows the full URL in a code block (with copy button) AND as a clickable link.\n";
                    }
                }
            } catch (\Throwable $_) {}
        }

        // Inform model about disabled tools so it doesn't try to use unavailable features
        $disabledTools = $_SESSION['disabled_tools'] ?? [];
        if (!empty($disabledTools)) {
            $disabledList = implode(', ', $disabledTools);
            $systemPrompt .= "\n\nIMPORTANT - DISABLED TOOLS: The following tools have been disabled by the user and are NOT available: " . $disabledList . ". "
                . "Do NOT attempt to use these tools or provide functionality that requires them. "
                . "If a user asks for something that requires a disabled tool (e.g., image generation when generate_image is disabled), "
                . "politely explain that this feature has been disabled in their settings. ";
        }

        if (!$hasImage) {
            // Web search vs URL fetch distinction
            $systemPrompt .= 'IMPORTANT - Web Search vs URL Fetch:\n'
                . '• **Web Search** (AUTOMATIC): When user asks to search, find info, or asks questions - the system searches BEFORE you respond. '
                . 'Search results are provided in context. DO NOT use web_fetch to re-fetch those URLs!\n'
                . '• **URL Fetch** (web_fetch tool): ONLY when user explicitly provides a specific URL like "read https://example.com". '
                . 'NEVER use web_fetch for searching or finding information - that wastes tokens!\n'
                . 'If web search results are provided above, use that content directly - do NOT call web_fetch. ';

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
     * Get RAG document context limit based on model's context window
     * Models with larger context windows can handle more document content
     */
    private function getModelRagLimit(string $modelName, string $provider): int
    {
        $modelLower = strtolower($modelName);
        
        // Models with very large context windows (128k+) - can handle ~100k chars (~25k tokens)
        $largeContextModels = [
            'llama-4-maverick',      // 128k context
            'llama-4-scout',         // 128k context  
            'gemma2-9b-it',          // 128k context (Groq)
            'gpt-4o',                // 128k context
            'gpt-4-turbo',           // 128k context
            'claude-3',              // 200k context
            'gemini',                // 1M+ context
        ];
        
        foreach ($largeContextModels as $model) {
            if (stripos($modelLower, $model) !== false) {
                return 100000; // ~100KB for large context models
            }
        }
        
        // Models with medium context windows (32k-64k) - can handle ~40k chars
        $mediumContextModels = [
            'llama-3.3-70b',         // 128k but be conservative
            'llama-3.1-70b',         // 128k but be conservative
            'llama-3.1-8b',          // 128k but be conservative
            'mixtral-8x7b',          // 32k context
            'qwen',                  // Various sizes
        ];
        
        foreach ($mediumContextModels as $model) {
            if (stripos($modelLower, $model) !== false) {
                return 50000; // ~50KB for medium context models
            }
        }
        
        // Small context models or unknown - be conservative
        // Groq's older models have 8k context
        if ($provider === 'groq') {
            // Check for specific small context Groq models
            if (stripos($modelLower, 'llama-3-8b') !== false || 
                stripos($modelLower, 'llama-3-70b') !== false) {
                return 20000; // ~20KB for 8k context models
            }
        }
        
        // Default: safe for most models
        return 25000;
    }

    /**
     * Calculate max tokens based on user role and provider constraints
     */
    private function calculateMaxTokens(string $userRole, bool $hasImage, ?string $imageDataUrl, bool $useLocalVision, $localLlmConfig, string $provider = ''): int
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
        
        // Provider-specific max token limits
        // Groq has strict limits and max_tokens must leave room for input within context window
        if ($provider === 'groq') {
            $maxTokens = min($maxTokens, 4096); // Conservative limit for Groq to avoid context overflow
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

    private function resolveOpenAiCompatibleProviderBaseUrl(string $provider, $db, ?int $userIdSession, bool $isAdminUser): ?string
    {
        if ($provider !== 'ginto_tunnel') {
            return null;
        }

        $envBase = trim((string)(getenv('GINTO_TUNNEL_BASE_URL') ?: ($_ENV['GINTO_TUNNEL_BASE_URL'] ?? 'https://ollama.ginto.ai/v1/')));
        if ($envBase !== '' && !preg_match('#^https?://#i', $envBase)) {
            $envBase = 'https://' . ltrim($envBase, '/');
        }
        $fallback = rtrim($envBase, '/') . '/';

        if (!$db) {
            return $fallback;
        }

        $settingKeys = [];
        if (!$isAdminUser && !empty($userIdSession)) {
            $settingKeys[] = 'llm_ginto_tunnel_base_url_user_' . (int)$userIdSession;
        }
        $settingKeys[] = 'llm_ginto_tunnel_base_url';

        foreach ($settingKeys as $settingKey) {
            try {
                $row = $db->get('settings', ['value'], ['key' => $settingKey]);
                $candidate = trim((string)($row['value'] ?? ''));
                if ($candidate === '') {
                    continue;
                }
                if (!preg_match('#^https?://#i', $candidate)) {
                    $candidate = 'https://' . ltrim($candidate, '/');
                }
                return rtrim($candidate, '/') . '/';
            } catch (\Throwable $_) {
                continue;
            }
        }

        return $fallback;
    }

    /**
     * Send final SSE response with HTML and reasoning
     */
    /**
     * Execute Lightpanda web tools with activity streaming
     * Provides real-time UI feedback similar to web search
     */
    private function executeWebTool(string $toolName, array $toolArgs): array
    {
        $startTime = microtime(true);
        
        // Helper to emit activity events
        $emitActivity = function(array $event) use ($startTime) {
            $event['elapsed_ms'] = round((microtime(true) - $startTime) * 1000);
            echo "data: " . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        };
        
        if ($toolName === 'web_fetch') {
            $url = $toolArgs['url'] ?? '';
            $domain = parse_url($url, PHP_URL_HOST) ?: $url;
            
            // Emit: Starting fetch
            $emitActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'fetch',
                'message' => 'Fetching URL...',
                'url' => $url,
                'domain' => $domain,
                'status' => 'reading'
            ]);
            
            // Emit: Reading domain
            $emitActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'read',
                'message' => 'Reading: ' . $domain,
                'url' => $url,
                'domain' => $domain,
                'status' => 'reading'
            ]);
            
            // Execute the actual tool
            $result = \App\Core\McpInvoker::invoke($toolName, $toolArgs);
            
            // Emit: Complete
            $emitActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'fetch_complete',
                'message' => 'Fetched: ' . $domain,
                'url' => $url,
                'domain' => $domain,
                'status' => ($result['success'] ?? false) ? 'complete' : 'error'
            ]);
            
            return $result;
            
        } elseif ($toolName === 'web_extract_links') {
            $url = $toolArgs['url'] ?? '';
            $domain = parse_url($url, PHP_URL_HOST) ?: $url;
            
            // Emit: Extracting links
            $emitActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'extract',
                'message' => 'Extracting links from: ' . $domain,
                'url' => $url,
                'domain' => $domain,
                'status' => 'extracting'
            ]);
            
            // Execute the actual tool
            $result = \App\Core\McpInvoker::invoke($toolName, $toolArgs);
            
            // Emit: Complete
            $linkCount = $result['count'] ?? count($result['links'] ?? []);
            $emitActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'extract_complete',
                'message' => "Extracted {$linkCount} links",
                'url' => $url,
                'domain' => $domain,
                'link_count' => $linkCount,
                'status' => ($result['success'] ?? false) ? 'complete' : 'error'
            ]);
            
            return $result;
        }
        
        // Fallback - shouldn't happen but just in case
        return \App\Core\McpInvoker::invoke($toolName, $toolArgs);
    }

    /**
     * Execute image generation with progress streaming
     */
    private function imageGenEnv(string $key, string $default = ''): string
    {
        return strtolower(trim((string)($_ENV[$key] ?? getenv($key) ?? $default)));
    }

    private function imageGenRaw(string $key): string
    {
        return trim((string)($_ENV[$key] ?? getenv($key) ?? ''));
    }

    private function imageGenIntInRange(string $key, int $min, int $max): ?int
    {
        $raw = $this->imageGenRaw($key);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $value = (int)$raw;
        if ($value < $min || $value > $max) {
            return null;
        }
        return $value;
    }

    private function imageGenFloatInRange(string $key, float $min, float $max): ?float
    {
        $raw = $this->imageGenRaw($key);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $value = (float)$raw;
        if ($value < $min || $value > $max) {
            return null;
        }
        return $value;
    }

    private function imagePromptWantsMultiSubject(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\b(\d+)\s+(people|persons|characters|subjects|figures)\b/', $normalized)) {
            return true;
        }

        $multiHints = [
            'two ',
            'three ',
            'four ',
            'five ',
            'group',
            'crowd',
            'multiple',
            'pair',
            'twins',
            'people',
            'characters',
            'subjects',
            'team',
            'family',
        ];

        foreach ($multiHints as $hint) {
            if (strpos($normalized, $hint) !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildImagePromptPayload(string $prompt): array
    {
        if ($this->imagePromptWantsMultiSubject($prompt)) {
            return ['prompt' => $prompt];
        }

        $singlePrompt = rtrim($prompt) . ', single subject, one character only, centered composition';
        return [
            'prompt' => $singlePrompt,
            'negative_prompt' => 'duplicate subject, multiple people, multiple characters, twins, clone, mirrored person, group photo',
        ];
    }

    private function resolveImageGenModelId(): ?string
    {
        $modelId = trim($this->imageGenRaw('IMAGEGEN_MODEL_ID'));
        if ($modelId === '') {
            return null;
        }
        return $modelId;
    }

    private function resolveImageGenerationConfig(): array
    {
        $profile = $this->imageGenEnv('IMAGEGEN_PROFILE', 'balanced');
        switch ($profile) {
            case 'startup':
                $config = [
                    'profile' => 'startup',
                    'width' => 384,
                    'height' => 384,
                    'num_inference_steps' => 3,
                    'guidance_scale' => 0.8,
                ];
                break;
            case 'fast':
                $config = [
                    'profile' => 'fast',
                    'width' => 512,
                    'height' => 384,
                    'num_inference_steps' => 3,
                    'guidance_scale' => 0.9,
                ];
                break;
            case 'quality':
                $config = [
                    'profile' => 'quality',
                    'width' => 768,
                    'height' => 512,
                    'num_inference_steps' => 8,
                    'guidance_scale' => 1.5,
                ];
                break;
            case 'ultra':
                $config = [
                    'profile' => 'ultra',
                    'width' => 1024,
                    'height' => 576,
                    'num_inference_steps' => 12,
                    'guidance_scale' => 2.0,
                ];
                break;
            default:
                $config = [
                    'profile' => 'balanced',
                    'width' => 512,
                    'height' => 384,
                    'num_inference_steps' => 4,
                    'guidance_scale' => 1.0,
                ];
                break;
        }

        $stepsOverride = $this->imageGenIntInRange('IMAGEGEN_STEPS', 1, 50);
        if ($stepsOverride !== null) {
            $config['num_inference_steps'] = $stepsOverride;
        }

        $guidanceOverride = $this->imageGenFloatInRange('IMAGEGEN_GUIDANCE_SCALE', 0.1, 20.0);
        if ($guidanceOverride !== null) {
            $config['guidance_scale'] = $guidanceOverride;
        }

        $widthOverride = $this->imageGenIntInRange('IMAGEGEN_WIDTH', 256, 1536);
        if ($widthOverride !== null) {
            $config['width'] = $widthOverride;
        }

        $heightOverride = $this->imageGenIntInRange('IMAGEGEN_HEIGHT', 256, 1536);
        if ($heightOverride !== null) {
            $config['height'] = $heightOverride;
        }

        return $config;
    }

    private function resolveImageGenBaseUrl(bool $sdcpuActive): string
    {
        $sdcpuTunnelEnabled = in_array(
            strtoupper(trim((string)($_ENV['SDCPU_TUNNEL'] ?? getenv('SDCPU_TUNNEL') ?? 'false'))),
            ['TRUE', '1', 'YES', 'ON'],
            true
        );

        $computeMode = $this->imageGenEnv('IMAGEGEN_COMPUTE_MODE', 'auto');
        if ($computeMode === 'gpu') {
            return 'https://vision.ginto.ai';
        }
        if ($computeMode === 'cpu') {
            return 'http://127.0.0.1:8888';
        }

        return ($sdcpuActive && $sdcpuTunnelEnabled) ? 'https://vision.ginto.ai' : 'http://127.0.0.1:8888';
    }

    private function resolveLocalImageGenTunnelBaseUrl(): string
    {
        $localRelayPortRaw = trim((string)($_ENV['TUNNEL_RELAY_LOCAL_PORT'] ?? getenv('TUNNEL_RELAY_LOCAL_PORT') ?? '18080'));
        $localRelayPort = is_numeric($localRelayPortRaw) ? (int)$localRelayPortRaw : 18080;
        if ($localRelayPort < 1024 || $localRelayPort > 65535) {
            $localRelayPort = 18080;
        }

        return 'http://127.0.0.1:' . $localRelayPort;
    }

    private function imageGenConsumeSseEventFromBuffer(string &$buffer): ?string
    {
        if (!preg_match('/\r\n\r\n|\n\n|\r\r/', $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $separator = $matches[0][0];
        $offset = $matches[0][1];
        $event = substr($buffer, 0, $offset);
        $buffer = substr($buffer, $offset + strlen($separator));

        return trim($event);
    }

    private function imageGenDecodeSsePayload(string $event): ?array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($event)) ?: [];
        $dataLines = [];

        foreach ($lines as $line) {
            if (strpos($line, 'data:') === 0) {
                $dataLines[] = ltrim(substr($line, 5));
            }
        }

        if ($dataLines === []) {
            return null;
        }

        $json = implode("\n", $dataLines);
        $decoded = @json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeImageGenResult(?array $result): ?array
    {
        if (!is_array($result) || $result === []) {
            return null;
        }

        $imageValue = $result['image'] ?? null;

        if (!is_string($imageValue) || trim($imageValue) === '') {
            $images = $result['images'] ?? null;
            if (is_array($images) && isset($images[0])) {
                if (is_string($images[0])) {
                    $imageValue = $images[0];
                } elseif (is_array($images[0])) {
                    $imageValue = $images[0]['image'] ?? ($images[0]['b64_json'] ?? null);
                }
            }
        }

        if ((!is_string($imageValue) || trim($imageValue) === '') && isset($result['data'])) {
            $data = $result['data'];
            if (is_array($data)) {
                if (isset($data[0]) && is_array($data[0])) {
                    $imageValue = $data[0]['b64_json'] ?? ($data[0]['image'] ?? null);
                } elseif (isset($data['image']) && is_string($data['image'])) {
                    $imageValue = $data['image'];
                }
            }
        }

        if (!is_string($imageValue) || trim($imageValue) === '') {
            return null;
        }

        $imageValue = trim($imageValue);
        if (str_starts_with($imageValue, 'data:image/')) {
            $commaPos = strpos($imageValue, 'base64,');
            if ($commaPos !== false) {
                $imageValue = substr($imageValue, $commaPos + 7);
            }
        }

        if ($imageValue === '') {
            return null;
        }

        $result['image'] = $imageValue;
        return $result;
    }

    private function summarizeImageGenApiResultForDebug(?array $result): string
    {
        if (!is_array($result)) {
            return 'non-json response';
        }

        $keys = array_keys($result);
        $summary = 'keys=' . implode(',', array_slice($keys, 0, 8));

        if (!empty($result['detail']) && is_string($result['detail'])) {
            $summary .= ' detail=' . substr($result['detail'], 0, 180);
        } elseif (!empty($result['message']) && is_string($result['message'])) {
            $summary .= ' message=' . substr($result['message'], 0, 180);
        } elseif (!empty($result['error']) && is_string($result['error'])) {
            $summary .= ' error=' . substr($result['error'], 0, 180);
        }

        return $summary;
    }

    private function executeImageGeneration(string $prompt): array
    {
        if (empty(trim($prompt))) {
            return ['success' => false, 'error' => 'Prompt is required'];
        }

        // SDCPU_ACTIVE=true bypasses the subscription gate entirely
        $sdcpuActive = strtolower(trim($_ENV['SDCPU_ACTIVE'] ?? getenv('SDCPU_ACTIVE') ?? 'false')) === 'true';
        $sdcpuBaseUrl = $this->resolveImageGenBaseUrl($sdcpuActive);
        $sdcpuTunnel = $sdcpuBaseUrl !== 'http://127.0.0.1:8888';
        $streamUrl = $sdcpuBaseUrl . '/api/generate-stream';

        // Check if user has ImageGen subscription
        $userId = $_SESSION['user_id'] ?? null;
        $hasImageGenSubscription = false;

        if ($userId && $this->db) {
            $addon = $this->db->get('user_addons', ['status'], [
                'user_id' => $userId,
                'addon_type' => 'imagegen',
                'status' => 'active'
            ]);
            $hasImageGenSubscription = !empty($addon);
        }

        if (!$hasImageGenSubscription && !$sdcpuActive) {
            // Return upgrade required response
            return [
                'success' => false,
                'error' => 'Image generation requires an ImageGen Pro subscription.',
                'upgrade_required' => true,
                'addon_type' => 'imagegen',
                'addon_name' => 'ImageGen Pro',
                'addon_price' => '$500.00/month',
                'features' => [
                    'Unlimited AI image generation',
                    'GPU-accelerated processing (10x faster)',
                    'Image-to-image editing',
                    'Inpainting and outpainting',
                    'Multiple style presets',
                    'Priority support',
                    'Dedicated GPU resources'
                ]
            ];
        }

        if (!$sdcpuActive) {
            // Subscription active but GPU server not yet configured
            return [
                'success' => false,
                'error' => 'processing imagegen request for your purchase',
                'subscription_active' => true,
                'pending_setup' => true
            ];
        }

        // SDCPU is active — proceed with generation
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

        $emitProgress(0, $sdcpuTunnel ? 'Connecting to FastSD tunnel relay...' : 'Connecting to FastSD CPU server...');

        // Check health
        $healthCheckUrl = $sdcpuBaseUrl . '/api/health';
        $healthCurl = curl_init($healthCheckUrl);
        curl_setopt_array($healthCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $healthCheck = curl_exec($healthCurl);
        $healthHttpCode = (int) curl_getinfo($healthCurl, CURLINFO_HTTP_CODE);
        curl_close($healthCurl);

        if ($healthCheck === false || $healthHttpCode < 200 || $healthHttpCode >= 300) {
            return ['success' => false, 'error' => 'Image generation server is not available'];
        }

        $emitProgress(5, 'Server ready, starting generation...');
        
        $generationConfig = $this->resolveImageGenerationConfig();
        $promptPayload = $this->buildImagePromptPayload($prompt);
        $requestData = [
            'prompt' => $promptPayload['prompt'],
            'width' => $generationConfig['width'],
            'height' => $generationConfig['height'],
            'num_inference_steps' => $generationConfig['num_inference_steps'],
            'guidance_scale' => $generationConfig['guidance_scale'],
            'num_images' => 1,
        ];
        $modelId = $this->resolveImageGenModelId();
        if ($modelId !== null) {
            $requestData['model'] = $modelId;
        }
        if (!empty($promptPayload['negative_prompt'])) {
            $requestData['negative_prompt'] = $promptPayload['negative_prompt'];
        }
        
        // Use streaming endpoint for real-time progress
        $ch = curl_init($streamUrl);
        $responseBuffer = '';
        $lastProgress = 0;
        $finalResult = null;
        $streamApiError = null;
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: text/event-stream'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$responseBuffer, &$lastProgress, &$finalResult, &$streamApiError, $emitProgress) {
                $responseBuffer .= $data;
                
                // Parse SSE events (supports both \n\n and \r\n\r\n separators)
                while (($chunk = $this->imageGenConsumeSseEventFromBuffer($responseBuffer)) !== null) {
                    $event = $this->imageGenDecodeSsePayload($chunk);
                    if (!$event) {
                        continue;
                    }

                    if (!empty($event['error']) && is_string($event['error'])) {
                        $streamApiError = $event['error'];
                    }

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
                    } else {
                        $normalizedEvent = $this->normalizeImageGenResult($event);
                        if ($normalizedEvent && isset($normalizedEvent['image'])) {
                        // Final result with image
                            $finalResult = $normalizedEvent;
                            $emitProgress(90, 'Processing image...');
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

        if (!$finalResult && trim($responseBuffer) !== '') {
            $tail = trim($responseBuffer);
            $tailEvent = $this->imageGenDecodeSsePayload($tail);
            $normalizedTailEvent = $this->normalizeImageGenResult(is_array($tailEvent) ? $tailEvent : null);
            if (is_array($normalizedTailEvent) && isset($normalizedTailEvent['image'])) {
                $finalResult = $normalizedTailEvent;
            } else {
                $tailJson = @json_decode($tail, true);
                $normalizedTailJson = $this->normalizeImageGenResult(is_array($tailJson) ? $tailJson : null);
                if (is_array($normalizedTailJson) && isset($normalizedTailJson['image'])) {
                    $finalResult = $normalizedTailJson;
                }
            }
        }
        
        if (!$success || ($httpCode !== 200 && $httpCode !== 0) || !$finalResult || !isset($finalResult['image'])) {
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
            $fallbackHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $fallbackCurlError = curl_error($ch);
            curl_close($ch);
            
            $decoded = @json_decode((string)$response, true);
            $finalResult = $this->normalizeImageGenResult(is_array($decoded) ? $decoded : null);

            $needsCompatRetry = $fallbackCurlError !== '' || $fallbackHttpCode !== 200 || !$finalResult || !isset($finalResult['image']);
            if ($needsCompatRetry) {
                $emitProgress(25, 'Retrying with compatibility payload...');

                $compatRequestData = $requestData;
                unset($compatRequestData['negative_prompt'], $compatRequestData['model'], $compatRequestData['num_images']);

                $compatCh = curl_init($sdcpuBaseUrl . '/api/generate');
                curl_setopt_array($compatCh, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($compatRequestData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 60,
                ]);

                $compatResponse = curl_exec($compatCh);
                $compatHttpCode = (int)curl_getinfo($compatCh, CURLINFO_HTTP_CODE);
                $compatCurlError = curl_error($compatCh);
                curl_close($compatCh);

                $compatDecoded = @json_decode((string)$compatResponse, true);
                $compatResult = $this->normalizeImageGenResult(is_array($compatDecoded) ? $compatDecoded : null);

                if ($compatCurlError === '' && $compatHttpCode === 200 && $compatResult && isset($compatResult['image'])) {
                    $finalResult = $compatResult;
                } else {
                    if (is_array($compatDecoded)) {
                        $decoded = $compatDecoded;
                    }
                    if ($compatCurlError !== '') {
                        $streamApiError = trim(($streamApiError ? ($streamApiError . ' | ') : '') . $compatCurlError);
                    }
                }
            }
        }
        
        if (!$finalResult || !isset($finalResult['image'])) {
            $debugSummary = $this->summarizeImageGenApiResultForDebug(is_array($finalResult) ? $finalResult : null);
            if ($streamApiError) {
                $debugSummary .= ' stream_error=' . substr($streamApiError, 0, 180);
            }
            return ['success' => false, 'error' => 'Failed to generate image (' . $debugSummary . ')'];
        }
        
        $emitProgress(95, 'Saving image...');
        
        // Save image (filesystem storage located one level above the repo)
        $storageDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/imagegen' : dirname(__DIR__, 2) . '/../storage/imagegen';
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
            'tunneled' => $sdcpuTunnel,
            'model' => 'Ginto AI ImageGen 1.0',
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
    public static function sendFinalResponse(string $content, string $reasoning, $parsedown, ?string $provider = null, ?string $model = null, bool $isAdmin = false): void
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
        $logEntry .= "=== PROVIDER ===\n" . ($provider ?? 'unknown') . "\n";
        $logEntry .= "=== MODEL ===\n" . ($model ?? 'unknown') . "\n";
        $logEntry .= "=== RAW CONTENT ===\n" . $content . "\n";
        $logEntry .= "=== REASONING ===\n" . $reasoning . "\n";
        $logEntry .= "==================\n\n";
        @file_put_contents($logDir . '/ginto.log', $logEntry, FILE_APPEND | LOCK_EX);
        $payload = [
            'html' => $html,
            'reasoningHtml' => $reasoningHtml,
            'contentEmpty' => empty(trim($content)),
            'final' => true
        ];

        // For admin sessions, include provider/model and raw content so admin UI can display them
        if ($isAdmin) {
            $payload['provider'] = $provider ?? 'unknown';
            $payload['model'] = $model ?? 'unknown';
            $payload['raw_content'] = $content;
        }

        echo "data: " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}

