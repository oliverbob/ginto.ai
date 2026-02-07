<?php

declare(strict_types=1);

namespace Ginto\Handlers;

use Ginto\Database;

/**
 * PandaSearchHandler - Isolated web search testing endpoint
 * 
 * This handler provides a standalone /pandasearch API for testing
 * web search streaming behavior independent of the main /chat endpoint.
 * 
 * Purpose:
 * - Test reasoning streaming in isolation
 * - Debug web search activity events
 * - Verify SSE streaming works correctly
 * 
 * The main difference from /chat is that this handler:
 * - Only does web search (no conversation history, no other tools)
 * - Streams reasoning DURING search, not after
 * - Provides cleaner debugging output
 */
class PandaSearchHandler
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
     * Handle the pandasearch request (POST /pandasearch)
     * 
     * This uses an interleaved approach - reasoning starts as soon as
     * the first source is fetched, and continues to update as more arrive.
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
        
        // Get the query
        $query = $_POST['query'] ?? trim(file_get_contents('php://input')) ?: '';
        $numResults = (int)($_POST['num_results'] ?? 3);
        
        if (empty($query)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Query is required']);
            exit;
        }
        
        // Start SSE stream
        $this->startStream();
        
        // Send initial status
        $startTime = microtime(true);
        $this->emit([
            'status' => 'started',
            'query' => $query,
            'num_results' => $numResults,
            'timestamp' => date('c'),
        ]);
        
        // Check if Lightpanda is available
        if (!LightpandaSearchHandler::isAvailable()) {
            $this->emit([
                'error' => true,
                'message' => 'Lightpanda is not available. Please ensure the browser is installed.',
            ]);
            $this->emit(['final' => true]);
            return;
        }
        
        // Activity callback - streams search/fetch events
        $onActivity = function(array $event) use ($startTime) {
            $event['elapsed_ms'] = round((microtime(true) - $startTime) * 1000);
            $this->emit($event);
        };
        
        // Reasoning callback - streams AI analysis chunks
        $reasoningChunks = [];
        $onReasoning = function(array $event) use (&$reasoningChunks, $startTime) {
            $event['elapsed_ms'] = round((microtime(true) - $startTime) * 1000);
            $this->emit($event);
            if (isset($event['text'])) {
                $reasoningChunks[] = $event['text'];
            }
        };
        
        try {
            // Execute interleaved search + reasoning
            $result = $this->executeInterleavedSearch($query, $numResults, $startTime, $onActivity, $onReasoning);
            
            // Send sources list
            $sources = [];
            foreach (($result['sources'] ?? []) as $source) {
                $sources[] = [
                    'title' => $source['title'] ?? 'Untitled',
                    'url' => $source['url'] ?? '',
                    'domain' => $source['domain'] ?? '',
                ];
            }
            
            if (!empty($sources)) {
                $this->emit([
                    'sources' => $sources,
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
                ]);
            }
            
            // Final result
            $this->emit([
                'final' => true,
                'success' => true,
                'total_elapsed_ms' => round((microtime(true) - $startTime) * 1000),
                'sources_count' => count($sources),
                'reasoning_chunks' => count($reasoningChunks),
            ]);
            
        } catch (\Throwable $e) {
            error_log('[PandaSearch] Error: ' . $e->getMessage());
            $this->emit([
                'error' => true,
                'message' => $e->getMessage(),
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
            ]);
            $this->emit(['final' => true]);
        }
    }
    
    /**
     * Execute search with interleaved reasoning
     * 
     * This fetches sources one-by-one and starts reasoning DURING the fetch process,
     * not after. Each source triggers an incremental analysis.
     */
    private function executeInterleavedSearch(
        string $query,
        int $numResults,
        float $startTime,
        callable $onActivity,
        callable $onReasoning
    ): array {
        $numResults = min(max($numResults, 1), 10);
        
        // URL DETECTION: If query is a URL or contains URLs, fetch them directly
        $directUrls = $this->extractUrls($query);
        if (!empty($directUrls)) {
            return $this->fetchDirectUrls($directUrls, $query, $startTime, $onActivity, $onReasoning);
        }
        
        // Phase 1: Search for URLs
        $this->emit([
            'phase' => 'search',
            'message' => 'Searching the web...',
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        $searchResult = LightpandaSearchHandler::search($query, 'duckduckgo', $numResults + 2, $onActivity);
        
        if (!($searchResult['success'] ?? false)) {
            return ['success' => false, 'sources' => []];
        }
        
        $searchResults = $searchResult['results'] ?? [];
        if (empty($searchResults)) {
            $onReasoning(['reasoning' => true, 'text' => 'No search results found for this query.']);
            return ['success' => true, 'sources' => []];
        }
        
        // Get LLM for incremental reasoning
        $llm = $this->getReasoningLLM();
        if (!$llm) {
            // Fallback to sequential mode
            return $this->executeSequentialSearch($query, $numResults, $startTime, $onActivity, $onReasoning);
        }
        
        // Phase 2: Fetch sources one by one, reasoning as we go
        $sources = [];
        $fetchedCount = 0;
        
        // Start reasoning immediately - show what we're looking for
        $onReasoning(['reasoning' => true, 'text' => "Analyzing results for: \"$query\"\n\n"]);
        
        foreach ($searchResults as $index => $result) {
            if ($fetchedCount >= $numResults) {
                break;
            }
            
            $url = $result['link'] ?? $result['href'] ?? $result['url'] ?? null;
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Emit that we're about to fetch
            $this->emit([
                'phase' => 'fetch',
                'message' => 'Reading source ' . ($fetchedCount + 1) . '...',
                'url' => $url,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
            ]);
            
            // Fetch the page
            $fetchResult = LightpandaSearchHandler::fetch($url, $onActivity);
            
            if ($fetchResult['success'] ?? false) {
                $source = [
                    'title' => $result['title'] ?? $fetchResult['title'] ?? 'Untitled',
                    'url' => $url,
                    'domain' => parse_url($url, PHP_URL_HOST),
                    'snippet' => $result['snippet'] ?? '',
                    'content' => $fetchResult['content'] ?? '',
                ];
                $sources[] = $source;
                $fetchedCount++;
                
                // Immediately analyze this source
                $this->analyzeSourceIncrementally($llm, $query, $source, $fetchedCount, $onReasoning);
            }
        }
        
        // Final synthesis if we have multiple sources
        if (count($sources) > 1) {
            $onReasoning(['reasoning' => true, 'text' => "\n\n**Summary:** "]);
            $this->synthesizeSources($llm, $query, $sources, $onReasoning);
        }
        
        $this->emit([
            'phase' => 'search_complete',
            'success' => true,
            'source_count' => count($sources),
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        return [
            'success' => true,
            'sources' => $sources,
        ];
    }
    
    /**
     * Analyze a single source incrementally and stream the reasoning
     */
    private function analyzeSourceIncrementally(
        \App\Core\LLM\Providers\OpenAICompatibleProvider $llm,
        string $query,
        array $source,
        int $sourceNum,
        callable $onReasoning
    ): void {
        $content = $source['content'] ?? $source['snippet'] ?? '';
        if (strlen($content) > 1500) {
            $content = substr($content, 0, 1500) . '...';
        }
        
        $prompt = "Source $sourceNum: {$source['title']}\nURL: {$source['url']}\n\n$content\n\n"
            . "In 1-2 sentences, what is the key insight from this source relevant to: \"$query\"?";
        
        $messages = [
            ['role' => 'system', 'content' => 'Extract the most relevant insight in 1-2 sentences. Be concise.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        $onReasoning(['reasoning' => true, 'text' => "**Source $sourceNum ({$source['domain']}):** "]);
        
        try {
            $llm->chatStream(
                messages: $messages,
                tools: [],
                options: ['max_tokens' => 150, 'temperature' => 0.3],
                onChunk: function($chunk) use ($onReasoning) {
                    if ($chunk !== '' && $chunk !== null) {
                        $onReasoning(['reasoning' => true, 'text' => $chunk]);
                    }
                }
            );
            $onReasoning(['reasoning' => true, 'text' => "\n\n"]);
        } catch (\Throwable $e) {
            error_log('[PandaSearch] Incremental analysis failed: ' . $e->getMessage());
            $onReasoning(['reasoning' => true, 'text' => "[Analysis pending...]\n\n"]);
        }
    }
    
    /**
     * Synthesize multiple sources into a final summary
     */
    private function synthesizeSources(
        \App\Core\LLM\Providers\OpenAICompatibleProvider $llm,
        string $query,
        array $sources,
        callable $onReasoning
    ): void {
        $sourceList = '';
        foreach ($sources as $i => $source) {
            $sourceList .= ($i + 1) . ". {$source['title']} ({$source['domain']})\n";
        }
        
        $prompt = "Query: \"$query\"\n\nSources analyzed:\n$sourceList\n\n"
            . "Provide a 1-sentence synthesis of what these sources tell us about the query.";
        
        $messages = [
            ['role' => 'system', 'content' => 'Synthesize in 1 clear sentence.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        try {
            $llm->chatStream(
                messages: $messages,
                tools: [],
                options: ['max_tokens' => 100, 'temperature' => 0.3],
                onChunk: function($chunk) use ($onReasoning) {
                    if ($chunk !== '' && $chunk !== null) {
                        $onReasoning(['reasoning' => true, 'text' => $chunk]);
                    }
                }
            );
        } catch (\Throwable $e) {
            error_log('[PandaSearch] Synthesis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract URLs from query string
     * Returns URLs if the query contains valid URLs
     */
    private function extractUrls(string $query): array
    {
        $urls = [];
        
        // Pattern to match URLs (http, https, or www)
        $pattern = '/(?:https?:\/\/|www\.)[^\s<>\[\]]+/i';
        
        if (preg_match_all($pattern, $query, $matches)) {
            foreach ($matches[0] as $url) {
                // Add https:// if it starts with www.
                if (stripos($url, 'www.') === 0) {
                    $url = 'https://' . $url;
                }
                // Validate URL
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Fetch URLs directly without searching
     * Used when the user provides specific URLs in the query
     */
    private function fetchDirectUrls(
        array $urls,
        string $originalQuery,
        float $startTime,
        callable $onActivity,
        callable $onReasoning
    ): array {
        $sources = [];
        
        // Emit that we're directly fetching URLs
        $this->emit([
            'phase' => 'direct_fetch',
            'message' => 'Fetching ' . count($urls) . ' URL(s) directly...',
            'urls' => $urls,
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        // Get LLM for reasoning
        $llm = $this->getReasoningLLM();
        
        // Extract any additional context from the query (text that's not a URL)
        $context = trim(preg_replace('/(?:https?:\/\/|www\.)[^\s<>\[\]]+/i', '', $originalQuery));
        if (!empty($context)) {
            $onReasoning(['reasoning' => true, 'text' => "Analyzing: \"$context\"\n\n"]);
        }
        
        foreach ($urls as $index => $url) {
            $domain = parse_url($url, PHP_URL_HOST) ?: $url;
            
            // Emit that we're about to fetch
            $this->emit([
                'phase' => 'fetch',
                'message' => 'Reading: ' . $domain,
                'url' => $url,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
            ]);
            
            // Fetch the page
            $fetchResult = LightpandaSearchHandler::fetch($url, $onActivity);
            
            if ($fetchResult['success'] ?? false) {
                $source = [
                    'title' => $fetchResult['title'] ?? $domain,
                    'url' => $url,
                    'domain' => $domain,
                    'snippet' => '',
                    'content' => $fetchResult['content'] ?? '',
                ];
                $sources[] = $source;
                
                // Analyze this source if we have an LLM
                if ($llm) {
                    $analysisContext = !empty($context) ? $context : "the content of this page";
                    $this->analyzeSourceIncrementally($llm, $analysisContext, $source, $index + 1, $onReasoning);
                }
            } else {
                $onReasoning(['reasoning' => true, 'text' => "**Source " . ($index + 1) . " ({$domain}):** Unable to fetch content.\n\n"]);
            }
        }
        
        // Final synthesis if we have multiple sources
        if (count($sources) > 1 && $llm) {
            $onReasoning(['reasoning' => true, 'text' => "\n\n**Summary:** "]);
            $synthesisContext = !empty($context) ? $context : "the content from these URLs";
            $this->synthesizeSources($llm, $synthesisContext, $sources, $onReasoning);
        }
        
        $this->emit([
            'phase' => 'search_complete',
            'success' => true,
            'source_count' => count($sources),
            'direct_fetch' => true,
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        return [
            'success' => true,
            'sources' => $sources,
            'direct_fetch' => true,
        ];
    }
    
    /**
     * Fallback to sequential search if LLM not available
     */
    private function executeSequentialSearch(
        string $query,
        int $numResults,
        float $startTime,
        callable $onActivity,
        callable $onReasoning
    ): array {
        $provider = strtolower(getenv('DEFAULT_PROVIDER') ?: 'cerebras');
        $result = LightpandaSearchHandler::executeToolCallWithSummary(
            ['query' => $query, 'num_results' => $numResults],
            $onActivity,
            $onReasoning,
            $provider
        );
        
        $this->emit([
            'phase' => 'search_complete',
            'success' => $result['success'] ?? false,
            'source_count' => count($result['sources'] ?? []),
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        return $result;
    }
    
    /**
     * Get LLM instance for reasoning
     */
    private function getReasoningLLM(): ?\App\Core\LLM\Providers\OpenAICompatibleProvider
    {
        $apiKey = null;
        $provider = null;
        
        try {
            $keyManager = new \App\Core\ProviderKeyManager($this->db);
            $keyData = $keyManager->getFirstAvailableKey();
            
            if ($keyData) {
                $apiKey = $keyData['api_key'];
                $provider = $keyData['provider'];
            }
        } catch (\Throwable $e) {
            error_log('[PandaSearch] ProviderKeyManager error: ' . $e->getMessage());
        }
        
        // Fallback to environment variables
        if (!$apiKey) {
            $defaultProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
            $envVarPrimary = ($defaultProvider === 'cerebras') ? 'CEREBRAS_API_KEY' : 'GROQ_API_KEY';
            $envVarFallback = ($defaultProvider === 'cerebras') ? 'GROQ_API_KEY' : 'CEREBRAS_API_KEY';
            
            $apiKey = getenv($envVarPrimary) ?: ($_ENV[$envVarPrimary] ?? '');
            $provider = $defaultProvider;
            
            if (empty($apiKey)) {
                $apiKey = getenv($envVarFallback) ?: ($_ENV[$envVarFallback] ?? '');
                $provider = ($defaultProvider === 'cerebras') ? 'groq' : 'cerebras';
            }
        }
        
        if (!$apiKey) {
            return null;
        }
        
        return new \App\Core\LLM\Providers\OpenAICompatibleProvider($provider, [
            'api_key' => $apiKey,
            'model' => $provider === 'cerebras' ? 'llama-3.3-70b' : 'llama-3.3-70b-versatile',
        ]);
    }
    
    /**
     * GET handler - returns info and test form
     */
    public function info(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->getTestPage();
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
     * Start SSE stream
     */
    private function startStream(): void
    {
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_flush();
        }
        
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        
        // Flush headers and send padding
        echo str_repeat(' ', 1024) . "\n";
        flush();
    }
    
    /**
     * Emit SSE event
     */
    private function emit(array $data): void
    {
        echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
    
    /**
     * Get test page HTML
     */
    private function getTestPage(): string
    {
        // Generate CSRF token if not exists
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PandaSearch Test - Web Search Streaming</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .stream-log { font-family: monospace; font-size: 12px; }
        .event-activity { color: #60a5fa; }
        .event-reasoning { color: #34d399; }
        .event-phase { color: #fbbf24; }
        .event-error { color: #f87171; }
        .event-final { color: #a78bfa; }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-2">🐼 PandaSearch Test</h1>
        <p class="text-gray-400 mb-6">Isolated web search streaming test endpoint</p>
        
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <form id="searchForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="{$csrfToken}">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Search Query</label>
                    <input type="text" name="query" id="query" 
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white"
                           placeholder="e.g., latest SpaceX news 2024" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Number of Results (1-10)</label>
                    <input type="number" name="num_results" id="num_results" value="3" min="1" max="10"
                           class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                </div>
                
                <button type="submit" id="submitBtn"
                        class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-medium">
                    🔍 Search
                </button>
            </form>
        </div>
        
        <div id="resultsArea" class="hidden">
            <div class="grid grid-cols-2 gap-6">
                <!-- Activity Timeline -->
                <div class="bg-gray-800 rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
                        <span>📡 Activity Stream</span>
                        <span id="activityStatus" class="text-xs text-gray-500"></span>
                    </h2>
                    <div id="activityLog" class="stream-log h-96 overflow-y-auto bg-gray-900 rounded p-3 space-y-1">
                    </div>
                </div>
                
                <!-- Reasoning Stream -->
                <div class="bg-gray-800 rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
                        <span>🧠 Reasoning Stream</span>
                        <span id="reasoningStatus" class="text-xs text-gray-500"></span>
                    </h2>
                    <div id="reasoningLog" class="h-96 overflow-y-auto bg-gray-900 rounded p-3">
                        <div id="reasoningContent" class="text-green-400 whitespace-pre-wrap"></div>
                    </div>
                </div>
            </div>
            
            <!-- Sources -->
            <div id="sourcesArea" class="hidden mt-6 bg-gray-800 rounded-lg p-4">
                <h2 class="text-lg font-semibold mb-3">📚 Sources Found</h2>
                <div id="sourcesList" class="space-y-2"></div>
            </div>
            
            <!-- Final Response -->
            <div id="responseArea" class="hidden mt-6 bg-gradient-to-r from-green-900 to-gray-800 rounded-lg p-4 border border-green-700">
                <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
                    <span>✨ Response</span>
                    <span id="responseTime" class="text-xs text-gray-400"></span>
                </h2>
                <div id="responseContent" class="text-gray-100 whitespace-pre-wrap leading-relaxed"></div>
            </div>
            
            <!-- Raw Events -->
            <div class="mt-6 bg-gray-800 rounded-lg p-4">
                <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
                    <span>📋 Raw Events</span>
                    <span id="eventCount" class="text-xs text-gray-500">0 events</span>
                </h2>
                <pre id="rawLog" class="stream-log h-64 overflow-y-auto bg-gray-900 rounded p-3"></pre>
            </div>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('searchForm');
        const resultsArea = document.getElementById('resultsArea');
        const activityLog = document.getElementById('activityLog');
        const reasoningLog = document.getElementById('reasoningLog');
        const reasoningContent = document.getElementById('reasoningContent');
        const rawLog = document.getElementById('rawLog');
        const sourcesList = document.getElementById('sourcesList');
        const sourcesArea = document.getElementById('sourcesArea');
        const responseArea = document.getElementById('responseArea');
        const responseContent = document.getElementById('responseContent');
        const responseTime = document.getElementById('responseTime');
        const submitBtn = document.getElementById('submitBtn');
        
        let eventCount = 0;
        let reasoningText = '';
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Reset UI
            eventCount = 0;
            reasoningText = '';
            activityLog.innerHTML = '';
            reasoningContent.textContent = '';
            responseContent.textContent = '';
            rawLog.textContent = '';
            sourcesList.innerHTML = '';
            sourcesArea.classList.add('hidden');
            responseArea.classList.add('hidden');
            resultsArea.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Searching...';
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('/pandasearch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': formData.get('csrf_token')
                    },
                    body: new URLSearchParams(formData).toString()
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\\n');
                    buffer = lines.pop() || '';
                    
                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (!trimmed || !trimmed.startsWith('data: ')) continue;
                        
                        const jsonStr = trimmed.slice(6).trim();
                        if (!jsonStr) continue;
                        
                        try {
                            const data = JSON.parse(jsonStr);
                            handleEvent(data);
                        } catch (err) {
                            console.warn('Parse error:', err, jsonStr);
                        }
                    }
                }
            } catch (err) {
                addActivityEvent({ error: true, message: err.message });
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🔍 Search';
            }
        });
        
        function handleEvent(data) {
            eventCount++;
            document.getElementById('eventCount').textContent = eventCount + ' events';
            
            // Log raw event
            const elapsed = data.elapsed_ms ? '[' + data.elapsed_ms + 'ms]' : '';
            rawLog.textContent += elapsed + ' ' + JSON.stringify(data) + '\\n';
            rawLog.scrollTop = rawLog.scrollHeight;
            
            // Handle activity events
            if (data.activity === 'websearch') {
                const icon = data.type === 'search' ? '🔎' : '📖';
                const status = data.status === 'completed' ? '✅' : '⏳';
                const text = data.type === 'search' 
                    ? icon + ' Search: "' + data.query + '" ' + status
                    : icon + ' Reading: ' + data.domain + ' ' + status;
                addActivityEvent({ text: text, className: 'event-activity' });
            }
            
            // Handle phase events
            if (data.phase) {
                addActivityEvent({ text: '📍 Phase: ' + data.phase + ' - ' + (data.message || ''), className: 'event-phase' });
            }
            
            // Handle reasoning
            if (data.reasoning !== undefined && data.text) {
                reasoningText += data.text;
                reasoningContent.textContent = reasoningText;
                reasoningLog.scrollTop = reasoningLog.scrollHeight;
                document.getElementById('reasoningStatus').textContent = 'streaming...';
            }
            
            // Handle sources
            if (data.sources && Array.isArray(data.sources)) {
                sourcesArea.classList.remove('hidden');
                data.sources.forEach((src, i) => {
                    const div = document.createElement('div');
                    div.className = 'bg-gray-700 rounded p-2';
                    div.innerHTML = '<span class="text-blue-400">' + (i+1) + '.</span> ' +
                        '<a href="' + src.url + '" target="_blank" class="text-blue-300 hover:underline">' + src.title + '</a> ' +
                        '<span class="text-gray-500 text-sm">(' + src.domain + ')</span>';
                    sourcesList.appendChild(div);
                });
            }
            
            // Handle errors
            if (data.error) {
                addActivityEvent({ text: '❌ Error: ' + data.message, className: 'event-error' });
            }
            
            // Handle final
            if (data.final) {
                const text = data.success 
                    ? '🏁 Complete! ' + (data.sources_count || 0) + ' sources, ' + (data.reasoning_chunks || 0) + ' reasoning chunks, ' + data.total_elapsed_ms + 'ms'
                    : '🏁 Finished (with errors)';
                addActivityEvent({ text: text, className: 'event-final' });
                document.getElementById('activityStatus').textContent = 'complete';
                document.getElementById('reasoningStatus').textContent = 'complete';
                
                // Show the response box with full reasoning
                if (reasoningText.trim()) {
                    responseContent.textContent = reasoningText;
                    responseTime.textContent = 'Generated in ' + data.total_elapsed_ms + 'ms';
                    responseArea.classList.remove('hidden');
                }
            }
        }
        
        function addActivityEvent({ text, className }) {
            const div = document.createElement('div');
            div.className = className || '';
            div.textContent = text;
            activityLog.appendChild(div);
            activityLog.scrollTop = activityLog.scrollHeight;
        }
    </script>
</body>
</html>
HTML;
    }
}
