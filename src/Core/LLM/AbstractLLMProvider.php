<?php

declare(strict_types=1);

namespace App\Core\LLM;

use GuzzleHttp\Client as HttpClient;

/**
 * Abstract base class for LLM providers.
 * 
 * Provides common functionality for HTTP-based LLM APIs.
 */
abstract class AbstractLLMProvider implements LLMProviderInterface
{
    protected ?HttpClient $httpClient = null;
    protected ?string $apiKey = null;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout = 60;

    public function __construct(array $config = [])
    {
        $this->configure($config);
    }

    /**
     * Configure the provider with the given options.
     */
    protected function configure(array $config): void
    {
        $this->apiKey = $config['api_key'] ?? $this->getEnvApiKey();
        $this->model = $config['model'] ?? $this->getDefaultModel();
        $this->baseUrl = $config['base_url'] ?? $this->getDefaultBaseUrl();
        $this->timeout = $config['timeout'] ?? 60;

        if ($this->apiKey) {
            $this->httpClient = new HttpClient([
                'base_uri' => $this->baseUrl,
                'headers' => $this->getDefaultHeaders(),
                'timeout' => $this->timeout,
            ]);
        }
    }

    /**
     * Get the API key from environment variables.
     */
    abstract protected function getEnvApiKey(): ?string;

    /**
     * Get the default base URL for this provider.
     */
    abstract protected function getDefaultBaseUrl(): string;

    /**
     * Get the default headers for requests.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->httpClient !== null;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    /**
     * Make an HTTP POST request.
     */
    protected function post(string $endpoint, array $payload): array
    {
        if (!$this->httpClient) {
            throw new \RuntimeException('Provider not configured: missing API key');
        }

        try {
            $response = $this->httpClient->post($endpoint, ['json' => $payload]);
            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            
            if (!is_array($data)) {
                throw new \RuntimeException('Invalid JSON response from API');
            }
            
            return $data;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() 
                ? (string) $e->getResponse()->getBody() 
                : $e->getMessage();
            
            $errorData = json_decode($errorBody, true) ?? [];
            $errorMessage = $errorData['error']['message'] 
                ?? $errorData['message'] 
                ?? $e->getMessage();
            
            throw new \RuntimeException("API error: $errorMessage", 0, $e);
        }
    }

    /**
     * Make a streaming HTTP POST request.
     */
    protected function postStream(string $endpoint, array $payload, callable $onChunk): void
    {
        if (!$this->httpClient) {
            throw new \RuntimeException('Provider not configured: missing API key');
        }

        $payload['stream'] = true;
        error_log("[postStream] Starting stream to $endpoint");

        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $payload,
                'stream' => true,
            ]);

            error_log("[postStream] Got response, status=" . $response->getStatusCode());
            $body = $response->getBody();
            $buffer = '';
            $chunkCount = 0;

            // Read smaller blocks to avoid coalescing many small SSE events
            while (!$body->eof()) {
                $buffer .= $body->read(256);
                $chunkCount++;

                // Process complete SSE lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $line = trim($line);
                    if ($line === '' || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (str_starts_with($line, 'data: ')) {
                        // Forward raw JSON payload string to caller for minimal work
                        $json = substr($line, 6);
                        try {
                            $onChunk($json);
                        } catch (\Throwable $_) {
                            // If caller expects decoded arrays, they will handle json_decode themselves
                        }
                    }
                }
            }
            error_log("[postStream] Stream complete, chunks=$chunkCount");
        } catch (\Throwable $e) {
            $errorBody = '';
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $errorBody = (string) $e->getResponse()->getBody();
            }
            error_log("[postStream] Streaming error: " . $e->getMessage() . " | Response: " . $errorBody);
            throw new \RuntimeException("Streaming error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Normalize tool definitions to internal format.
     * 
     * Accepts:
     * - ToolDefinition objects
     * - Internal format arrays
     * - OpenAI format arrays (with 'function' wrapper)
     * 
     * Internal format:
     * [
     *   'name' => 'tool_name',
     *   'description' => 'Tool description',
     *   'parameters' => [
     *     'type' => 'object',
     *     'properties' => [...],
     *     'required' => [...]
     *   ]
     * ]
     */
    protected function normalizeTools(array $tools): array
    {
        return array_map(function ($tool) {
            // ToolDefinition object
            if ($tool instanceof ToolDefinition) {
                return $tool->toArray();
            }
            
            // Already in internal format
            if (isset($tool['name']) && !isset($tool['type'])) {
                return $tool;
            }
            
            // OpenAI format with 'function' wrapper
            if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                return [
                    'name' => $tool['function']['name'],
                    'description' => $tool['function']['description'] ?? '',
                    'parameters' => $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ];
            }
            
            return $tool;
        }, $tools);
    }
}
