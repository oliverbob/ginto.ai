<?php

declare(strict_types=1);

namespace App\Core\LLM\Providers;

use App\Core\LLM\AbstractLLMProvider;
use App\Core\LLM\LLMResponse;
use GuzzleHttp\Client as HttpClient;

/**
 * Ollama provider supporting both local and cloud deployments.
 * 
 * Local: http://localhost:11434/api/ (no auth required)
 * Cloud: https://ollama.com/api/ (requires OLLAMA_API_KEY)
 * 
 * Uses the native Ollama API format (not OpenAI-compatible).
 */
class OllamaProvider extends AbstractLLMProvider
{
    protected string $mode = 'local'; // 'local' or 'cloud'
    
    protected static array $defaultModels = [
        'local' => 'llama3.2',
        'cloud' => 'llama3.3',
    ];

    protected static array $baseUrls = [
        'local' => 'http://localhost:11434/',
        'cloud' => 'https://ollama.com/',
    ];

    protected static array $availableModels = [
        // Common models - actual availability depends on what's pulled locally or cloud tier
        'llama3.3',
        'llama3.2',
        'llama3.1',
        'llama3',
        'mistral',
        'mixtral',
        'phi3',
        'gemma2',
        'qwen2.5',
        'deepseek-r1',
        'codellama',
        'llama2',
    ];

    public function __construct(array $config = [])
    {
        // Detect mode from config or API key presence
        if (isset($config['mode'])) {
            $this->mode = $config['mode'];
        } elseif (!empty($config['api_key']) || $this->getEnvApiKey()) {
            $this->mode = 'cloud';
        }
        
        parent::__construct($config);
    }

    /**
     * Override configure to handle local mode (no API key needed).
     */
    protected function configure(array $config): void
    {
        $this->apiKey = $config['api_key'] ?? $this->getEnvApiKey();
        $this->model = $config['model'] ?? $this->getDefaultModel();
        $this->baseUrl = $config['base_url'] ?? $this->getDefaultBaseUrl();
        $this->timeout = $config['timeout'] ?? 120; // Ollama can be slow for large models

        // For local mode, we don't need an API key
        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'headers' => $this->getDefaultHeaders(),
            'timeout' => $this->timeout,
        ]);
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function getStyle(): string
    {
        return 'ollama';
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    protected function getEnvApiKey(): ?string
    {
        return getenv('OLLAMA_API_KEY') ?: ($_ENV['OLLAMA_API_KEY'] ?? null);
    }

    protected function getDefaultBaseUrl(): string
    {
        return self::$baseUrls[$this->mode] ?? self::$baseUrls['local'];
    }

    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        // Only add auth header for cloud mode
        if ($this->mode === 'cloud' && $this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        
        return $headers;
    }

    public function isConfigured(): bool
    {
        // Local mode: check if Ollama is actually reachable
        if ($this->mode === 'local') {
            if ($this->httpClient === null) {
                return false;
            }
            // Quick health check - try to reach the API
            try {
                $response = $this->httpClient->get('api/tags', ['timeout' => 2, 'connect_timeout' => 1]);
                return $response->getStatusCode() === 200;
            } catch (\Throwable $e) {
                return false;
            }
        }
        // Cloud mode requires API key
        return $this->apiKey !== null && $this->httpClient !== null;
    }

    public function getDefaultModel(): string
    {
        return self::$defaultModels[$this->mode] ?? 'llama3.2';
    }

    public function getModels(): array
    {
        // Try to fetch actual available models from the API
        try {
            $response = $this->httpClient->get('api/tags');
            $data = json_decode((string) $response->getBody(), true);
            
            if (isset($data['models']) && is_array($data['models'])) {
                return array_map(fn($m) => $m['name'] ?? $m['model'] ?? '', $data['models']);
            }
        } catch (\Throwable $e) {
            // Ollama not reachable - return empty list, not static fallback
        }
        
        // Return empty array if we can't reach Ollama - don't show fake models
        return [];
    }

    /**
     * Ollama uses a simpler tool format than OpenAI.
     */
    public function formatTools(array $tools): array
    {
        $normalized = $this->normalizeTools($tools);
        
        return array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ];
        }, $normalized);
    }

    /**
     * Convert messages to Ollama format.
     * 
     * Ollama uses a simple {role, content} format similar to OpenAI,
     * but tool calls are handled differently.
     */
    public function formatMessages(array $messages): array
    {
        return array_map(function ($msg) {
            $formatted = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? '',
            ];

            // Handle tool calls in assistant message
            if (isset($msg['tool_calls']) && !empty($msg['tool_calls'])) {
                $formatted['tool_calls'] = array_map(function ($tc) {
                    return [
                        'function' => [
                            'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                            'arguments' => $tc['function']['arguments'] ?? $tc['arguments'] ?? '',
                        ],
                    ];
                }, $msg['tool_calls']);
            }

            return $formatted;
        }, $messages);
    }

    public function chat(array $messages, array $tools = [], array $options = []): LLMResponse
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->formatMessages($messages),
            'stream' => false,
        ];

        // Add options
        $ollamaOptions = [];
        if (isset($options['temperature'])) {
            $ollamaOptions['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $ollamaOptions['num_predict'] = $options['max_tokens'];
        }
        if (!empty($ollamaOptions)) {
            $payload['options'] = $ollamaOptions;
        }

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        try {
            $response = $this->post('api/chat', $payload);
            return $this->parseResponse($response);
        } catch (\Throwable $e) {
            return LLMResponse::error($e->getMessage());
        }
    }

    public function chatStream(array $messages, array $tools = [], array $options = [], callable $onChunk = null): LLMResponse
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->formatMessages($messages),
            'stream' => true,
        ];

        $ollamaOptions = [];
        if (isset($options['temperature'])) {
            $ollamaOptions['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $ollamaOptions['num_predict'] = $options['max_tokens'];
        }
        if (!empty($ollamaOptions)) {
            $payload['options'] = $ollamaOptions;
        }

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $content = '';
        $toolCalls = [];
        $model = $payload['model'];

        try {
            $this->postStreamOllama('api/chat', $payload, function ($chunk) use (&$content, &$toolCalls, $onChunk) {
                // Handle thinking content (qwen3 and other reasoning models)
                // Send as a special reasoning event so frontend can display separately
                if (isset($chunk['message']['thinking'])) {
                    $text = $chunk['message']['thinking'];
                    // Don't add thinking to main content - keep it separate
                    if ($onChunk) {
                        // Pass thinking as a toolCall-like object with 'reasoning' flag
                        $onChunk($text, ['reasoning' => true, 'text' => $text]);
                    }
                }
                
                // Handle regular content
                if (isset($chunk['message']['content'])) {
                    $text = $chunk['message']['content'];
                    $content .= $text;
                    if ($onChunk) {
                        $onChunk($text, null);
                    }
                }
                
                if (isset($chunk['message']['tool_calls'])) {
                    foreach ($chunk['message']['tool_calls'] as $tc) {
                        $toolCall = [
                            'id' => 'call_' . uniqid(),
                            'name' => $tc['function']['name'] ?? '',
                            'arguments' => $tc['function']['arguments'] ?? [],
                        ];
                        $toolCalls[] = $toolCall;
                        if ($onChunk) {
                            $onChunk('', $toolCall);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            return LLMResponse::error($e->getMessage());
        }

        $finishReason = !empty($toolCalls) 
            ? LLMResponse::FINISH_TOOL_CALLS 
            : LLMResponse::FINISH_STOP;

        return new LLMResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            model: $model
        );
    }

    /**
     * Ollama streaming uses newline-delimited JSON (not SSE).
     */
    protected function postStreamOllama(string $endpoint, array $payload, callable $onChunk): void
    {
        if (!$this->httpClient) {
            throw new \RuntimeException('Provider not configured');
        }

        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $buffer .= $body->read(1024);

                // Process complete JSON lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $chunk = json_decode($line, true);
                    if (is_array($chunk)) {
                        $onChunk($chunk);
                        
                        // Check for done signal
                        if (!empty($chunk['done'])) {
                            return;
                        }
                    }
                }
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();

            $errorData = json_decode($errorBody, true) ?? [];
            $errorMessage = $errorData['error'] ?? $e->getMessage();

            throw new \RuntimeException("Ollama API error: $errorMessage", 0, $e);
        }
    }

    /**
     * Parse Ollama response format.
     */
    protected function parseResponse(array $response): LLMResponse
    {
        $content = $response['message']['content'] ?? '';
        $toolCalls = [];
        
        if (isset($response['message']['tool_calls'])) {
            foreach ($response['message']['tool_calls'] as $tc) {
                $toolCalls[] = [
                    'id' => 'call_' . uniqid(),
                    'name' => $tc['function']['name'] ?? '',
                    'arguments' => $tc['function']['arguments'] ?? [],
                ];
            }
        }

        $finishReason = !empty($toolCalls)
            ? LLMResponse::FINISH_TOOL_CALLS
            : ($response['done'] ?? false ? LLMResponse::FINISH_STOP : LLMResponse::FINISH_LENGTH);

        $usage = [];
        if (isset($response['prompt_eval_count'])) {
            $usage['prompt_tokens'] = $response['prompt_eval_count'];
        }
        if (isset($response['eval_count'])) {
            $usage['completion_tokens'] = $response['eval_count'];
        }
        if (!empty($usage)) {
            $usage['total_tokens'] = ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0);
        }

        return new LLMResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            model: $response['model'] ?? $this->model,
            usage: $usage,
            raw: $response
        );
    }
}
