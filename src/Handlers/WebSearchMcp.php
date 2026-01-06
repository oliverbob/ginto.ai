<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;

/**
 * Web Search MCP Tools
 * 
 * Provides web search and browsing capabilities for AI agents using
 * Lightpanda headless browser (11x faster, 9x less memory than Chrome).
 * 
 * Features:
 * - Web search via multiple engines (DuckDuckGo, Google, Bing)
 * - Page content fetching with JavaScript execution
 * - Link extraction for crawling
 * - Custom JavaScript evaluation on pages
 * 
 * Uses the Lightpanda MCP server located at tools/lightpanda-mcp/
 */
final class WebSearchMcp
{
    private const LIGHTPANDA_SCRIPT = 'tools/lightpanda-mcp/scripts/websearch.js';
    private const TIMEOUT = 30; // seconds
    
    /**
     * Get project root directory
     */
    private static function projectRoot(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    }
    
    /**
     * Execute a Lightpanda operation via Node.js
     */
    private static function executeLightpanda(string $operation, array $params): array
    {
        $scriptPath = self::projectRoot() . '/' . self::LIGHTPANDA_SCRIPT;
        
        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'error' => 'Lightpanda script not found. Run: cd tools/lightpanda-mcp && npm install'
            ];
        }
        
        // Build command with JSON payload
        $payload = json_encode([
            'operation' => $operation,
            'params' => $params,
        ]);
        
        $cmd = sprintf(
            'cd %s && node %s %s 2>&1',
            escapeshellarg(self::projectRoot()),
            escapeshellarg($scriptPath),
            escapeshellarg(base64_encode($payload))
        );
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        $result = implode("\n", $output);
        
        // Try to parse JSON response
        $decoded = json_decode($result, true);
        if ($decoded !== null) {
            return $decoded;
        }
        
        // Return raw output if not JSON
        return [
            'success' => $returnCode === 0,
            'output' => $result,
            'error' => $returnCode !== 0 ? 'Command failed with code ' . $returnCode : null,
        ];
    }

    // =========================================================================
    // WEB SEARCH TOOLS
    // =========================================================================

    #[McpTool(
        name: 'web_search',
        description: 'Search the web using a search engine. Returns top results with titles, URLs, and snippets. Use this to find information, articles, documentation, or any web content. Engines: duckduckgo (default, private), google, bing.'
    )]
    public function webSearch(
        string $query,
        string $engine = 'duckduckgo',
        int $maxResults = 10
    ): array {
        if (empty(trim($query))) {
            return ['success' => false, 'error' => 'Search query is required'];
        }
        
        $validEngines = ['duckduckgo', 'google', 'bing'];
        if (!in_array($engine, $validEngines)) {
            $engine = 'duckduckgo';
        }
        
        $maxResults = min(max($maxResults, 1), 20);
        
        return self::executeLightpanda('search', [
            'query' => $query,
            'engine' => $engine,
            'maxResults' => $maxResults,
        ]);
    }

    #[McpTool(
        name: 'web_fetch',
        description: 'Fetch the content of a webpage with full JavaScript execution. Returns the page title and text content. Use this to read articles, documentation, blog posts, or any web content that may require JS rendering.'
    )]
    public function webFetch(
        string $url,
        ?string $waitForSelector = null,
        int $waitTime = 0
    ): array {
        if (empty(trim($url))) {
            return ['success' => false, 'error' => 'URL is required'];
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid URL format'];
        }
        
        $waitTime = min(max($waitTime, 0), 10000);
        
        return self::executeLightpanda('fetch', [
            'url' => $url,
            'waitForSelector' => $waitForSelector,
            'waitTime' => $waitTime,
        ]);
    }

    #[McpTool(
        name: 'web_extract_links',
        description: 'Extract all links from a webpage. Returns a list of URLs with their link text. Useful for finding resources, navigation, or building a list of pages to explore.'
    )]
    public function webExtractLinks(string $url): array
    {
        if (empty(trim($url))) {
            return ['success' => false, 'error' => 'URL is required'];
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid URL format'];
        }
        
        return self::executeLightpanda('extractLinks', [
            'url' => $url,
        ]);
    }

    #[McpTool(
        name: 'web_screenshot',
        description: 'Capture a screenshot of a webpage. Returns a base64-encoded PNG image. Useful for visual verification, debugging, or when page layout matters.'
    )]
    public function webScreenshot(
        string $url,
        bool $fullPage = false
    ): array {
        if (empty(trim($url))) {
            return ['success' => false, 'error' => 'URL is required'];
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid URL format'];
        }
        
        return self::executeLightpanda('screenshot', [
            'url' => $url,
            'fullPage' => $fullPage,
        ]);
    }

    #[McpTool(
        name: 'web_evaluate',
        description: 'Execute custom JavaScript on a webpage and return the result. The script runs in the page context with full DOM access. Use this for advanced data extraction or page manipulation.'
    )]
    public function webEvaluate(
        string $url,
        string $script
    ): array {
        if (empty(trim($url))) {
            return ['success' => false, 'error' => 'URL is required'];
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid URL format'];
        }
        
        if (empty(trim($script))) {
            return ['success' => false, 'error' => 'JavaScript code is required'];
        }
        
        return self::executeLightpanda('evaluate', [
            'url' => $url,
            'script' => $script,
        ]);
    }

    #[McpTool(
        name: 'web_quick_answer',
        description: 'Search and fetch the top result in one step. Performs a web search, then fetches the content of the first result. Useful for quickly getting information about a topic.'
    )]
    public function webQuickAnswer(
        string $query,
        string $engine = 'duckduckgo'
    ): array {
        if (empty(trim($query))) {
            return ['success' => false, 'error' => 'Search query is required'];
        }
        
        // First, search
        $searchResult = $this->webSearch($query, $engine, 3);
        
        if (!($searchResult['success'] ?? false)) {
            return $searchResult;
        }
        
        $results = $searchResult['results'] ?? [];
        if (empty($results)) {
            return [
                'success' => false,
                'error' => 'No search results found',
                'query' => $query,
            ];
        }
        
        // Fetch the first result
        $firstUrl = $results[0]['link'] ?? $results[0]['href'] ?? null;
        if (!$firstUrl) {
            return [
                'success' => true,
                'query' => $query,
                'results' => $results,
                'note' => 'Could not fetch first result - no URL found',
            ];
        }
        
        $fetchResult = $this->webFetch($firstUrl);
        
        return [
            'success' => true,
            'query' => $query,
            'source' => [
                'title' => $results[0]['title'] ?? 'Unknown',
                'url' => $firstUrl,
            ],
            'content' => $fetchResult['content'] ?? $fetchResult['output'] ?? 'Failed to fetch content',
            'otherResults' => array_slice($results, 1),
        ];
    }
}
