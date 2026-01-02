<?php

declare(strict_types=1);

namespace Ginto\Handlers;

/**
 * LightpandaSearchHandler - Server-side web search using Lightpanda browser
 * 
 * This handler provides web search capabilities that work with ANY LLM provider,
 * not just Groq. It uses our Lightpanda headless browser (11x faster than Chrome)
 * to perform searches and fetch web content.
 * 
 * Benefits over Groq's browser_search:
 * - Works with any LLM provider (Cerebras, OpenAI, Claude, local models)
 * - Full control over search behavior and caching
 * - Can search multiple engines (DuckDuckGo, Google, Bing)
 * - Can fetch and parse page content after search
 * 
 * Usage:
 * 1. Call search() to perform a web search
 * 2. Call fetch() to get content from a specific URL
 * 3. Call searchAndFetch() for a combined search + fetch of top results
 */
class LightpandaSearchHandler
{
    private const LIGHTPANDA_SCRIPT = 'tools/lightpanda-mcp/scripts/websearch.js';
    private const STEALTH_SCRIPT = 'tools/lightpanda-mcp/scripts/stealth-browser.js';
    private const TIMEOUT = 30; // seconds
    private const MAX_CONTENT_LENGTH = 15000; // Max chars to return from a page
    
    /**
     * Get project root directory
     */
    private static function projectRoot(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    }
    
    /**
     * Check if Lightpanda is available (script exists and Node.js is installed)
     */
    public static function isAvailable(): bool
    {
        $scriptPath = self::projectRoot() . '/' . self::LIGHTPANDA_SCRIPT;
        if (!file_exists($scriptPath)) {
            return false;
        }
        
        // Also check if Node.js is available
        return self::getNodePath() !== null;
    }
    
    /**
     * Get node binary path - checks common locations
     */
    private static function getNodePath(): ?string
    {
        // Check environment variable first
        $envNode = getenv('NODE_PATH') ?: ($_ENV['NODE_PATH'] ?? null);
        if ($envNode && is_executable($envNode)) {
            return $envNode;
        }
        
        // Common node locations
        $paths = [
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/home/' . (getenv('USER') ?: 'www-data') . '/.nvm/versions/node/v22.*/bin/node',
            '/root/.nvm/versions/node/v22.*/bin/node',
            '/opt/node/bin/node',
        ];
        
        foreach ($paths as $path) {
            // Handle glob patterns
            if (str_contains($path, '*')) {
                $matches = glob($path);
                if (!empty($matches) && is_executable($matches[0])) {
                    return $matches[0];
                }
            } elseif (is_executable($path)) {
                return $path;
            }
        }
        
        // Try which command as last resort
        $which = trim(shell_exec('which node 2>/dev/null') ?? '');
        if ($which && is_executable($which)) {
            return $which;
        }
        
        return null;
    }
    
    /**
     * Execute a Lightpanda operation via Node.js
     */
    private static function execute(string $operation, array $params): array
    {
        $scriptPath = self::projectRoot() . '/' . self::LIGHTPANDA_SCRIPT;
        
        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'error' => 'Lightpanda not installed. Run: cd tools/lightpanda-mcp && npm install'
            ];
        }
        
        // Find node binary
        $nodePath = self::getNodePath();
        if (!$nodePath) {
            return [
                'success' => false,
                'error' => 'Node.js not found. Install Node.js or set NODE_PATH environment variable.'
            ];
        }
        
        // Build command with JSON payload
        $payload = json_encode([
            'operation' => $operation,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Set HOME for Docker container - must be a valid home with .cache directory
        // In Docker, HOME is often "/" which doesn't work, so default to /var/www
        $home = getenv('HOME');
        if (!$home || $home === '/' || !is_dir($home . '/.cache')) {
            $home = '/var/www';
        }
        
        $cmd = sprintf(
            'cd %s && HOME=%s %s %s %s 2>&1',
            escapeshellarg(self::projectRoot()),
            escapeshellarg($home),
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath),
            escapeshellarg(base64_encode($payload))
        );
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        // Check for command not found or other execution errors
        if ($returnCode === 127) {
            return [
                'success' => false,
                'error' => 'Node.js execution failed (code 127). Check Node.js installation.'
            ];
        }
        
        $result = implode("\n", $output);
        
        // Try to parse JSON response - handle potential logging prefix
        $decoded = json_decode($result, true);
        if ($decoded !== null) {
            return $decoded;
        }
        
        // If the full output isn't valid JSON, try to extract JSON from it
        // Look for lines that start with { and contain "success"
        foreach ($output as $line) {
            $line = trim($line);
            if (str_starts_with($line, '{"success"')) {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }
        
        // Return raw output if not JSON
        return [
            'success' => $returnCode === 0,
            'output' => $result,
            'error' => $returnCode !== 0 ? 'Command failed with code ' . $returnCode : null,
        ];
    }
    
    /**
     * Execute a stealth browser operation (Puppeteer + Stealth plugin for Cloudflare bypass)
     */
    private static function executeStealth(string $operation, array $params): array
    {
        $scriptPath = self::projectRoot() . '/' . self::STEALTH_SCRIPT;
        
        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'error' => 'Stealth browser not installed. Run: cd tools/lightpanda-mcp && npm install puppeteer puppeteer-extra puppeteer-extra-plugin-stealth'
            ];
        }
        
        $nodePath = self::getNodePath();
        if (!$nodePath) {
            return [
                'success' => false,
                'error' => 'Node.js not found for stealth browser.'
            ];
        }
        
        $payload = json_encode([
            'operation' => $operation,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $home = getenv('HOME');
        if (!$home || $home === '/' || !is_dir($home . '/.cache')) {
            $home = '/var/www';
        }
        
        $cmd = sprintf(
            'cd %s && HOME=%s %s %s %s 2>&1',
            escapeshellarg(self::projectRoot()),
            escapeshellarg($home),
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath),
            escapeshellarg(base64_encode($payload))
        );
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        $result = implode("\n", $output);
        
        // Parse JSON response
        foreach ($output as $line) {
            $line = trim($line);
            if (str_starts_with($line, '{"success"')) {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }
        
        $decoded = json_decode($result, true);
        if ($decoded !== null) {
            return $decoded;
        }
        
        return [
            'success' => false,
            'output' => $result,
            'error' => 'Failed to parse stealth browser response',
        ];
    }
    
    /**
     * Check if result indicates Cloudflare block
     */
    private static function isCloudflareBlocked(array $result): bool
    {
        if (!isset($result['content']) && !isset($result['html'])) {
            return false;
        }
        
        $content = ($result['content'] ?? '') . ($result['html'] ?? '');
        $content = strtolower($content);
        
        $indicators = [
            'checking your browser',
            'ray id:',
            'cloudflare',
            'please wait while we verify',
            'just a moment',
            'enable javascript and cookies',
            'cf-challenge',
            'cf-browser-verification',
        ];
        
        foreach ($indicators as $indicator) {
            if (str_contains($content, $indicator)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Perform a web search
     * 
     * @param string $query Search query
     * @param string $engine Search engine (duckduckgo, google, bing)
     * @param int $maxResults Maximum number of results
     * @param callable|null $onActivity Callback for activity events (SSE streaming)
     * @return array Search results with titles, URLs, and snippets
     */
    public static function search(
        string $query,
        string $engine = 'duckduckgo',
        int $maxResults = 5,
        ?callable $onActivity = null
    ): array {
        // Emit search started activity
        if ($onActivity) {
            error_log("[LightpandaSearch] Emitting search activity: searching");
            $onActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'search',
                'query' => $query,
                'engine' => $engine,
                'message' => 'Searching: "' . $query . '"',
                'status' => 'searching'
            ]);
        }
        
        $result = self::execute('search', [
            'query' => $query,
            'engine' => $engine,
            'maxResults' => $maxResults,
        ]);
        
        // Emit search completed activity
        if ($onActivity) {
            $resultCount = count($result['results'] ?? []);
            error_log("[LightpandaSearch] Emitting search activity: completed, results=$resultCount");
            $onActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'search',
                'query' => $query,
                'message' => 'Found ' . $resultCount . ' results',
                'status' => 'completed',
                'resultCount' => $resultCount
            ]);
        }
        
        return $result;
    }
    
    /**
     * Fetch content from a URL
     * 
     * @param string $url URL to fetch
     * @param callable|null $onActivity Callback for activity events (SSE streaming)
     * @return array Page title and text content
     */
    public static function fetch(string $url, ?callable $onActivity = null): array
    {
        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
        
        // Emit fetch started activity
        if ($onActivity) {
            error_log("[LightpandaSearch] Emitting read activity: reading $domain");
            $onActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'read',
                'url' => $url,
                'domain' => $domain,
                'message' => 'Reading: ' . $domain,
                'status' => 'reading'
            ]);
        }
        
        $result = self::execute('fetch', [
            'url' => $url,
            'waitTime' => 500, // Wait 500ms for JS to execute
        ]);
        
        // Check if blocked by Cloudflare - use stealth browser as fallback
        if (self::isCloudflareBlocked($result) || (!$result['success'] && isset($result['error']))) {
            if ($onActivity) {
                $onActivity([
                    'activity' => true,
                    'type' => 'websearch',
                    'phase' => 'read',
                    'url' => $url,
                    'domain' => $domain,
                    'message' => 'Cloudflare detected, using stealth browser...',
                    'status' => 'retrying'
                ]);
            }
            
            error_log("[LightpandaSearch] Cloudflare detected on $domain, falling back to stealth browser");
            $result = self::executeStealth('fetch', [
                'url' => $url,
                'waitTime' => 2000,
            ]);
        }
        
        // Truncate content if too long
        if (isset($result['content']) && strlen($result['content']) > self::MAX_CONTENT_LENGTH) {
            $result['content'] = substr($result['content'], 0, self::MAX_CONTENT_LENGTH) . "\n\n[Content truncated...]";
            $result['truncated'] = true;
        }
        
        // Emit fetch completed activity
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'websearch',
                'phase' => 'read',
                'url' => $url,
                'domain' => $domain,
                'message' => 'Done reading: ' . $domain,
                'status' => 'completed'
            ]);
        }
        
        return $result;
    }
    
    /**
     * Click an element on a page
     * 
     * @param string $url URL to load
     * @param string $selector CSS selector to click
     * @param int $waitAfter Time to wait after click (ms)
     * @param callable|null $onActivity Callback for activity events
     * @return array Result with page content after click
     */
    public static function click(string $url, string $selector, int $waitAfter = 2000, ?callable $onActivity = null): array
    {
        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
        
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'browser',
                'phase' => 'click',
                'url' => $url,
                'selector' => $selector,
                'message' => "Clicking: $selector on $domain",
                'status' => 'clicking'
            ]);
        }
        
        $result = self::execute('click', [
            'url' => $url,
            'selector' => $selector,
            'waitAfter' => min($waitAfter, 10000),
        ]);
        
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'browser',
                'phase' => 'click',
                'url' => $url,
                'message' => ($result['success'] ?? false) ? 'Click completed' : 'Click failed',
                'status' => ($result['success'] ?? false) ? 'completed' : 'failed'
            ]);
        }
        
        return $result;
    }
    
    /**
     * Fill form fields on a page
     * 
     * @param string $url URL to load
     * @param array $fields Array of ['selector' => '.input', 'value' => 'text']
     * @param string|null $submitSelector Optional selector for submit button
     * @param int $waitAfter Time to wait after form submission (ms)
     * @param callable|null $onActivity Callback for activity events
     * @return array Result with page content and images after filling
     */
    public static function fill(string $url, array $fields, ?string $submitSelector = null, int $waitAfter = 2000, ?callable $onActivity = null): array
    {
        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
        
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'browser',
                'phase' => 'fill',
                'url' => $url,
                'fieldCount' => count($fields),
                'message' => "Filling " . count($fields) . " field(s) on $domain",
                'status' => 'filling'
            ]);
        }
        
        $result = self::execute('fill', [
            'url' => $url,
            'fields' => $fields,
            'submitSelector' => $submitSelector,
            'waitAfter' => min($waitAfter, 30000),
        ]);
        
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'browser',
                'phase' => 'fill',
                'url' => $url,
                'message' => ($result['success'] ?? false) ? 'Form filled' . ($submitSelector ? ' and submitted' : '') : 'Form fill failed',
                'status' => ($result['success'] ?? false) ? 'completed' : 'failed'
            ]);
        }
        
        return $result;
    }
    
    /**
     * Generate image using form interaction
     * 
     * This method navigates to an image generation site, fills in the prompt,
     * submits, and waits for the generated image.
     * 
     * @param string $url URL of the image generator
     * @param string $promptSelector CSS selector for prompt input
     * @param string $promptValue The prompt text
     * @param string $submitSelector CSS selector for submit button
     * @param string|null $imageSelector Optional CSS selector for result image
     * @param int $maxWaitTime Maximum time to wait for image generation (ms)
     * @param callable|null $onActivity Callback for activity events
     * @return array Result with imageUrl on success
     */
    public static function generateImage(
        string $url,
        string $promptSelector,
        string $promptValue,
        string $submitSelector,
        ?string $imageSelector = null,
        int $maxWaitTime = 60000,
        ?callable $onActivity = null
    ): array {
        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
        
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'imagegen',
                'phase' => 'submit',
                'url' => $url,
                'message' => "Submitting prompt to $domain...",
                'status' => 'submitting'
            ]);
        }
        
        $result = self::execute('generateImage', [
            'url' => $url,
            'promptSelector' => $promptSelector,
            'promptValue' => $promptValue,
            'submitSelector' => $submitSelector,
            'imageSelector' => $imageSelector,
            'maxWaitTime' => min($maxWaitTime, 120000),
        ]);
        
        if ($onActivity) {
            $onActivity([
                'activity' => true,
                'type' => 'imagegen',
                'phase' => 'complete',
                'url' => $url,
                'message' => ($result['success'] ?? false) ? 'Image generated!' : ($result['error'] ?? 'Generation failed'),
                'status' => ($result['success'] ?? false) ? 'completed' : 'failed'
            ]);
        }
        
        return $result;
    }
    
    /**
     * Search and fetch top results in one call
     * 
     * This is the main method for AI-powered web research:
     * 1. Performs a web search
     * 2. Fetches content from the top N results
     * 3. Returns compiled results for the LLM to use
     * 
     * @param string $query Search query
     * @param string $engine Search engine
     * @param int $fetchTopN Number of results to fetch (1-5)
     * @param callable|null $onActivity Callback for activity events
     * @return array Combined search results and page content
     */
    public static function searchAndFetch(
        string $query,
        string $engine = 'duckduckgo',
        int $fetchTopN = 3,
        ?callable $onActivity = null
    ): array {
        $fetchTopN = min(max($fetchTopN, 1), 5);
        
        // Step 1: Search
        $searchResult = self::search($query, $engine, $fetchTopN + 2, $onActivity);
        
        if (!($searchResult['success'] ?? false)) {
            return $searchResult;
        }
        
        $searchResults = $searchResult['results'] ?? [];
        if (empty($searchResults)) {
            return [
                'success' => true,
                'query' => $query,
                'results' => [],
                'sources' => [],
                'message' => 'No search results found',
            ];
        }
        
        // Step 2: Fetch top results
        $sources = [];
        $fetchedCount = 0;
        
        foreach ($searchResults as $result) {
            if ($fetchedCount >= $fetchTopN) {
                break;
            }
            
            $url = $result['link'] ?? $result['href'] ?? $result['url'] ?? null;
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            $fetchResult = self::fetch($url, $onActivity);
            
            if ($fetchResult['success'] ?? false) {
                $sources[] = [
                    'title' => $result['title'] ?? $fetchResult['title'] ?? 'Untitled',
                    'url' => $url,
                    'domain' => parse_url($url, PHP_URL_HOST),
                    'snippet' => $result['snippet'] ?? '',
                    'content' => $fetchResult['content'] ?? '',
                ];
                $fetchedCount++;
            }
        }
        
        return [
            'success' => true,
            'query' => $query,
            'engine' => $engine,
            'resultCount' => count($searchResults),
            'fetchedCount' => $fetchedCount,
            'results' => $searchResults,
            'sources' => $sources,
        ];
    }
    
    /**
     * Format search results for LLM consumption
     * 
     * @param array $searchData Result from search() or searchAndFetch()
     * @return string Formatted text for LLM context
     */
    public static function formatForLLM(array $searchData): string
    {
        if (!($searchData['success'] ?? false)) {
            return "Web search failed: " . ($searchData['error'] ?? 'Unknown error');
        }
        
        $query = $searchData['query'] ?? 'unknown query';
        $output = "## Web Search Results for: \"$query\"\n\n";
        
        // Include fetched sources if available
        $sources = $searchData['sources'] ?? [];
        if (!empty($sources)) {
            foreach ($sources as $i => $source) {
                $num = $i + 1;
                $output .= "### Source $num: {$source['title']}\n";
                $output .= "URL: {$source['url']}\n\n";
                
                if (!empty($source['content'])) {
                    $output .= "{$source['content']}\n\n";
                } elseif (!empty($source['snippet'])) {
                    $output .= "{$source['snippet']}\n\n";
                }
                
                $output .= "---\n\n";
            }
        } else {
            // Just list search results without fetched content
            $results = $searchData['results'] ?? [];
            foreach ($results as $i => $result) {
                $num = $i + 1;
                $title = $result['title'] ?? 'Untitled';
                $url = $result['link'] ?? $result['href'] ?? $result['url'] ?? '#';
                $snippet = $result['snippet'] ?? '';
                
                $output .= "### Result $num: $title\n";
                $output .= "URL: $url\n";
                if ($snippet) {
                    $output .= "$snippet\n";
                }
                $output .= "\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Get tool definition for LLM tool calling
     * 
     * Returns the tool schema in OpenAI function calling format
     */
    public static function getToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'lightpanda_search',
                'description' => 'Search the web and fetch content from top results. Use this when you need current information, news, documentation, or any web content. Returns search results with actual page content.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query. Be specific and include relevant keywords.',
                        ],
                        'num_results' => [
                            'type' => 'integer',
                            'description' => 'Number of results to fetch (1-5). Default is 3.',
                            'default' => 3,
                            'minimum' => 1,
                            'maximum' => 5,
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }
    
    /**
     * Execute the tool call from an LLM
     * 
     * @param array $arguments Tool call arguments from the LLM
     * @param callable|null $onActivity Activity callback for streaming
     * @return string Tool result formatted for LLM consumption
     */
    public static function executeToolCall(array $arguments, ?callable $onActivity = null): string
    {
        $query = $arguments['query'] ?? '';
        $numResults = (int)($arguments['num_results'] ?? 3);
        
        if (empty(trim($query))) {
            return "Error: Search query is required";
        }
        
        $result = self::searchAndFetch($query, 'duckduckgo', $numResults, $onActivity);
        return self::formatForLLM($result);
    }
    
    /**
     * Execute interleaved search with real-time reasoning
     * 
     * This fetches sources one-by-one and generates reasoning DURING
     * the fetch process, not after. Each source triggers incremental analysis.
     * 
     * @param array $arguments Tool arguments with 'query' and optional 'num_results'
     * @param callable|null $onActivity Activity callback for websearch events
     * @param callable|null $onReasoning Callback for streaming reasoning text
     * @param string|null $providerName LLM provider to use
     * @return array Search results with sources
     */
    public static function executeInterleavedSearch(
        array $arguments,
        ?callable $onActivity = null,
        ?callable $onReasoning = null,
        ?string $providerName = null,
        ?string $apiKey = null,
        ?string $modelName = null
    ): array {
        $query = $arguments['query'] ?? '';
        $numResults = (int)($arguments['num_results'] ?? 3);
        $numResults = min(max($numResults, 1), 10);
        
        if (empty(trim($query))) {
            return [
                'success' => false,
                'error' => 'Search query is required',
                'content' => 'Error: Search query is required',
                'sources' => [],
            ];
        }
        
        // Get LLM for incremental reasoning - use passed API key and model if available
        $llm = self::getReasoningLLM($providerName, $apiKey, $modelName);
        
        // Phase 1: Search for URLs
        $searchResult = self::search($query, 'duckduckgo', $numResults + 2, $onActivity);
        
        if (!($searchResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $searchResult['error'] ?? 'Search failed',
                'content' => 'Search failed',
                'sources' => [],
            ];
        }
        
        $searchResults = $searchResult['results'] ?? [];
        if (empty($searchResults)) {
            if ($onReasoning) {
                $onReasoning(['reasoning' => true, 'text' => 'No search results found for this query.']);
            }
            return [
                'success' => true,
                'content' => 'No search results found.',
                'sources' => [],
            ];
        }
        
        // Start reasoning immediately
        if ($onReasoning && $llm) {
            $onReasoning(['reasoning' => true, 'text' => "Analyzing results for: \"$query\"\n\n"]);
        }
        
        // Phase 2: Fetch sources one by one, reasoning as we go
        $sources = [];
        $fetchedCount = 0;
        
        foreach ($searchResults as $result) {
            if ($fetchedCount >= $numResults) {
                break;
            }
            
            $url = $result['link'] ?? $result['href'] ?? $result['url'] ?? null;
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Emit phase marker before fetch (yellow phase marker, same as PandaSearchHandler)
            if ($onActivity) {
                $onActivity([
                    'phase' => 'fetch',
                    'message' => 'Reading source ' . ($fetchedCount + 1) . '...',
                    'url' => $url,
                ]);
            }
            
            // Fetch the page
            $fetchResult = self::fetch($url, $onActivity);
            
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
                
                // Immediately analyze this source if LLM available
                if ($onReasoning && $llm) {
                    self::analyzeSourceIncrementally($llm, $query, $source, $fetchedCount, $onReasoning);
                }
            }
        }
        
        // Final synthesis if we have multiple sources
        if ($onReasoning && $llm && count($sources) > 1) {
            $onReasoning(['reasoning' => true, 'text' => "\n**Summary:** "]);
            self::synthesizeSources($llm, $query, $sources, $onReasoning);
        }
        
        // Format content for LLM context
        $formattedContent = self::formatForLLM([
            'success' => true,
            'query' => $query,
            'sources' => $sources,
        ]);
        
        return [
            'success' => true,
            'content' => $formattedContent,
            'sources' => $sources,
            'resultCount' => count($sources),
        ];
    }
    
    /**
     * Analyze a single source incrementally and stream the reasoning
     */
    private static function analyzeSourceIncrementally(
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
        
        error_log("[LightpandaSearch] Starting incremental analysis for source $sourceNum");
        
        try {
            $llm->chatStream(
                messages: $messages,
                tools: [],
                options: ['max_tokens' => 150, 'temperature' => 0.3],
                onChunk: function($chunk) use ($onReasoning, $sourceNum) {
                    if ($chunk !== '' && $chunk !== null) {
                        error_log("[LightpandaSearch] Streaming chunk for source $sourceNum: " . strlen($chunk) . " chars");
                        $onReasoning(['reasoning' => true, 'text' => $chunk]);
                    }
                }
            );
            error_log("[LightpandaSearch] Finished incremental analysis for source $sourceNum");
            $onReasoning(['reasoning' => true, 'text' => "\n\n"]);
        } catch (\Throwable $e) {
            error_log('[LightpandaSearch] Incremental analysis failed: ' . $e->getMessage());
            $onReasoning(['reasoning' => true, 'text' => "[Analysis pending...]\n\n"]);
        }
    }
    
    /**
     * Synthesize multiple sources into a final summary
     */
    private static function synthesizeSources(
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
            error_log('[LightpandaSearch] Synthesis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get LLM instance for reasoning
     */
    private static function getReasoningLLM(?string $providerName = null, ?string $passedApiKey = null, ?string $passedModelName = null): ?\App\Core\LLM\Providers\OpenAICompatibleProvider
    {
        $apiKey = $passedApiKey;
        $provider = $providerName ?? 'cerebras';
        $modelName = $passedModelName;
        
        // If API key was passed directly, use it immediately
        if ($apiKey && $apiKey !== 'local') {
            // Use passed model or default based on provider
            if (!$modelName) {
                $modelName = $provider === 'cerebras' ? 'llama-3.3-70b' : 'llama-3.3-70b-versatile';
            }
            error_log("[LightpandaSearch] Using passed API key for provider: $provider, model: $modelName");
            return new \App\Core\LLM\Providers\OpenAICompatibleProvider($provider, [
                'api_key' => $apiKey,
                'model' => $modelName,
            ]);
        }
        
        // Fallback: Try to get from key manager
        try {
            $db = null;
            if (class_exists('\Ginto\Database')) {
                $db = \Ginto\Database::getInstance();
            } elseif (class_exists('Database')) {
                $db = \Database::getInstance();
            }
            
            if ($db) {
                $keyManager = new \App\Core\ProviderKeyManager($db);
                $keyData = $provider ? $keyManager->getAvailableKey($provider) : $keyManager->getFirstAvailableKey();
                
                if ($keyData) {
                    $apiKey = $keyData['api_key'];
                    $provider = $keyData['provider'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[LightpandaSearch] ProviderKeyManager error: ' . $e->getMessage());
        }
        
        // Fallback: environment variables
        if (!$apiKey) {
            $defaultProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
            $provider = $provider ?? $defaultProvider;
            
            $keyEnvMap = ['cerebras' => 'CEREBRAS_API_KEY', 'groq' => 'GROQ_API_KEY'];
            $envVar = $keyEnvMap[$provider] ?? 'CEREBRAS_API_KEY';
            $apiKey = getenv($envVar) ?: ($_ENV[$envVar] ?? null);
            
            if (!$apiKey) {
                $fallbackProvider = ($provider === 'cerebras') ? 'groq' : 'cerebras';
                $envVar = $keyEnvMap[$fallbackProvider] ?? 'CEREBRAS_API_KEY';
                $apiKey = getenv($envVar) ?: ($_ENV[$envVar] ?? null);
                if ($apiKey) $provider = $fallbackProvider;
            }
        }
        
        if (!$apiKey) {
            error_log('[LightpandaSearch] No API key found for reasoning LLM');
            return null;
        }
        
        // Use passed model or default based on provider
        if (!$modelName) {
            $modelName = $provider === 'cerebras' ? 'llama-3.3-70b' : 'llama-3.3-70b-versatile';
        }
        
        error_log("[LightpandaSearch] Creating reasoning LLM with provider: $provider, model: $modelName");
        return new \App\Core\LLM\Providers\OpenAICompatibleProvider($provider, [
            'api_key' => $apiKey,
            'model' => $modelName,
        ]);
    }

    /**
     * Execute tool call with AI-generated reasoning and summary
     * 
     * This enhanced version:
     * 1. Performs the web search with activity streaming
     * 2. Sends search results to a lightweight LLM to generate reasoning/analysis
     * 3. Streams the reasoning in real-time to the frontend
     * 
     * @param array $arguments Tool call arguments
     * @param callable|null $onActivity Activity callback for websearch events
     * @param callable|null $onReasoning Callback for streaming reasoning text
     * @param string|null $providerName LLM provider to use for summary (optional)
     * @return array Contains 'content' with formatted search results and 'summary' with AI analysis
     */
    public static function executeToolCallWithSummary(
        array $arguments,
        ?callable $onActivity = null,
        ?callable $onReasoning = null,
        ?string $providerName = null
    ): array {
        $query = $arguments['query'] ?? '';
        $numResults = (int)($arguments['num_results'] ?? 3);
        
        if (empty(trim($query))) {
            return [
                'success' => false,
                'error' => 'Search query is required',
                'content' => 'Error: Search query is required',
            ];
        }
        
        // Step 1: Perform web search with activity streaming
        $result = self::searchAndFetch($query, 'duckduckgo', $numResults, $onActivity);
        $formattedContent = self::formatForLLM($result);
        
        // Step 2: Generate AI summary/reasoning
        $summary = '';
        if ($onReasoning && ($result['success'] ?? false) && !empty($result['sources'])) {
            try {
                $summary = self::generateSearchSummary($query, $result, $onReasoning, $providerName);
            } catch (\Throwable $e) {
                error_log('[LightpandaSearch] Summary generation failed: ' . $e->getMessage());
                // Continue without summary - not a critical failure
            }
        }
        
        return [
            'success' => $result['success'] ?? false,
            'content' => $formattedContent,
            'summary' => $summary,
            'sources' => $result['sources'] ?? [],
            'resultCount' => $result['resultCount'] ?? 0,
        ];
    }
    
    /**
     * Generate an AI summary of search results with streaming reasoning
     * 
     * Uses a fast LLM provider to analyze search results and generate
     * a concise summary that gets streamed as "reasoning" to the frontend.
     * 
     * @param string $query Original search query
     * @param array $searchResult Search results from searchAndFetch()
     * @param callable $onReasoning Callback for streaming reasoning chunks
     * @param string|null $providerName LLM provider (defaults to cerebras for speed)
     * @return string The complete summary text
     */
    private static function generateSearchSummary(
        string $query,
        array $searchResult,
        callable $onReasoning,
        ?string $providerName = null
    ): string {
        // Build context from search results (truncated for token efficiency)
        $sources = $searchResult['sources'] ?? [];
        $contextParts = [];
        $maxContentPerSource = 2000; // Limit content per source
        
        foreach ($sources as $i => $source) {
            $content = $source['content'] ?? $source['snippet'] ?? '';
            if (strlen($content) > $maxContentPerSource) {
                $content = substr($content, 0, $maxContentPerSource) . '...';
            }
            $contextParts[] = sprintf(
                "Source %d: %s\nURL: %s\n%s",
                $i + 1,
                $source['title'] ?? 'Untitled',
                $source['url'] ?? '',
                $content
            );
        }
        $context = implode("\n\n---\n\n", $contextParts);
        
        // Build prompt for summary generation
        $summaryPrompt = "You are analyzing web search results for the query: \"$query\"\n\n"
            . "Search Results:\n$context\n\n"
            . "Provide a clear, well-formatted analysis of these search results.\n\n"
            . "FORMATTING REQUIREMENTS:\n"
            . "- Use **bold** for key terms and important concepts\n"
            . "- Use bullet points with proper markdown (each on its own line starting with - )\n"
            . "- Use headings (## or ###) to organize sections if needed\n"
            . "- Keep paragraphs short and scannable\n"
            . "- Be concise (2-3 short paragraphs or a well-organized list)\n\n"
            . "Focus on the most relevant information to answer the user's query.";
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a research assistant that analyzes search results. Format your response using proper markdown with **bold**, bullet lists, and clear structure.'],
            ['role' => 'user', 'content' => $summaryPrompt],
        ];
        
        // Use fast provider for summary (Cerebras is very fast)
        $provider = $providerName ?? strtolower(getenv('DEFAULT_PROVIDER') ?: 'cerebras');
        $apiKey = null;
        
        // Get API key from environment
        $keyEnvMap = [
            'cerebras' => 'CEREBRAS_API_KEY',
            'groq' => 'GROQ_API_KEY',
            'openai' => 'OPENAI_API_KEY',
        ];
        $envVar = $keyEnvMap[$provider] ?? 'CEREBRAS_API_KEY';
        $apiKey = getenv($envVar) ?: ($_ENV[$envVar] ?? null);
        
        if (!$apiKey) {
            // Try to get from key manager
            try {
                $db = \Ginto\Database::getInstance();
                $keyManager = new \App\Core\ProviderKeyManager($db);
                $keyData = $keyManager->getAvailableKey($provider);
                if ($keyData) {
                    $apiKey = $keyData['api_key'];
                }
            } catch (\Throwable $e) {
                error_log('[LightpandaSearch] Failed to get key from manager: ' . $e->getMessage());
            }
        }
        
        if (!$apiKey) {
            return ''; // Can't generate summary without API key
        }
        
        // Create provider and stream summary
        $llm = new \App\Core\LLM\Providers\OpenAICompatibleProvider($provider, [
            'api_key' => $apiKey,
            'model' => $provider === 'cerebras' ? 'llama-3.3-70b' : 'llama-3.3-70b-versatile',
        ]);
        
        $fullSummary = '';
        
        // Signal that we're starting the reasoning/analysis phase
        $onReasoning(['reasoning' => true, 'text' => 'Analyzing search results...']);
        
        $llm->chatStream(
            messages: $messages,
            tools: [],
            options: ['max_tokens' => 1024, 'temperature' => 0.3],
            onChunk: function($chunk) use (&$fullSummary, $onReasoning) {
                if ($chunk !== '' && $chunk !== null) {
                    $fullSummary .= $chunk;
                    // Stream reasoning chunk to frontend
                    $onReasoning(['reasoning' => true, 'text' => $chunk]);
                }
            }
        );
        
        return $fullSummary;
    }
}
