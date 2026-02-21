<?php

declare(strict_types=1);

namespace App\Core\LLM\Providers;

use App\Core\LLM\AbstractLLMProvider;
use App\Core\LLM\LLMResponse;

/**
 * OpenAI-compatible provider.
 * 
 * Works with OpenAI, Groq, Together, Fireworks, and other OpenAI-compatible APIs.
 * Uses the standard OpenAI chat completions format with tool_calls.
 */
class OpenAICompatibleProvider extends AbstractLLMProvider
{
    protected string $providerName = 'openai';

    // Default models per provider
    protected static array $defaultModels = [
        'openai' => 'gpt-4o',
        'groq' => 'llama-3.3-70b-versatile',
        'cerebras' => 'gpt-oss-120b',
        'together' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
        'fireworks' => 'accounts/fireworks/models/llama-v3p1-70b-instruct',
        'local' => 'ginto-default',
    ];

    // Ginto AI Default model configuration (from installer defaults)
    // Single unified model that uses reasoning or vision server based on request type
    public const GINTO_DEFAULT_CONFIG = [
        'id' => 'ginto-default',
        'name' => 'Ginto AI - Default',
        'reason_hf_model' => 'lm-kit/qwen-3-0.6b-instruct-gguf',
        'reason_port' => 8034,
        'vision_hf_model' => 'ggml-org/SmolVLM2-500M-Video-Instruct-GGUF',
        'vision_port' => 8033,
    ];

    protected static array $baseUrls = [
        'openai' => 'https://api.openai.com/v1/',
        'groq' => 'https://api.groq.com/openai/v1/',
        'cerebras' => 'https://api.cerebras.ai/v1/',
        'together' => 'https://api.together.xyz/v1/',
        'fireworks' => 'https://api.fireworks.ai/inference/v1/',
        'local' => 'http://127.0.0.1:8034/v1/',
    ];

    protected static array $envKeys = [
        'openai' => 'OPENAI_API_KEY',
        'groq' => 'GROQ_API_KEY',
        'cerebras' => 'CEREBRAS_API_KEY',
        'together' => 'TOGETHER_API_KEY',
        'fireworks' => 'FIREWORKS_API_KEY',
        'local' => 'LOCAL_LLM_API_KEY', // Not used, but needed for consistency
    ];

    protected static array $availableModels = [
        'openai' => [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo',
            'o1-preview',
            'o1-mini',
        ],
        'groq' => [
            'llama-3.3-70b-versatile',
            'llama-3.1-70b-versatile',
            'llama-3.1-8b-instant',
            'gemma2-9b-it',
            'mixtral-8x7b-32768',
            'deepseek-r1-distill-llama-70b',
            'meta-llama/llama-4-scout-17b-16e-instruct',
            'meta-llama/llama-4-maverick-17b-128e-instruct',
            'moonshotai/kimi-k2-instruct-0905',
            // Vision models
            'llama-3.2-11b-vision-preview',
            'llama-3.2-90b-vision-preview',
        ],
        'cerebras' => [
            'gpt-oss-120b',
            'llama-3.3-70b',
            'llama-3.1-8b',
            'llama-4-scout-17b-16e',
            'qwen-3-32b',
        ],
        'together' => [
            'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
            'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
            'mistralai/Mixtral-8x7B-Instruct-v0.1',
            'Qwen/Qwen2.5-72B-Instruct-Turbo',
        ],
        'fireworks' => [
            'accounts/fireworks/models/llama-v3p1-70b-instruct',
            'accounts/fireworks/models/llama-v3p1-8b-instruct',
            'accounts/fireworks/models/mixtral-8x7b-instruct',
        ],
        'local' => [
            'ginto-default',  // Ginto AI - Default (auto-selects vision/reason based on request)
        ],
    ];

    public function __construct(string $provider = 'openai', array $config = [])
    {
        $this->providerName = $provider;
        parent::__construct($config);
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function getStyle(): string
    {
        return 'openai';
    }

    protected function getEnvApiKey(): ?string
    {
        $envKey = self::$envKeys[$this->providerName] ?? 'OPENAI_API_KEY';
        return getenv($envKey) ?: ($_ENV[$envKey] ?? null);
    }

    protected function getDefaultBaseUrl(): string
    {
        return self::$baseUrls[$this->providerName] ?? self::$baseUrls['openai'];
    }

    public function getDefaultModel(): string
    {
        return self::$defaultModels[$this->providerName] ?? 'gpt-4o';
    }

    public function getModels(): array
    {
        return self::$availableModels[$this->providerName] ?? [];
    }

    /**
     * Get models with display names for UI.
     * Returns array of ['id' => '...', 'name' => '...'] for each model.
     */
    public function getModelsWithNames(): array
    {
        $models = $this->getModels();
        $result = [];
        
        foreach ($models as $modelId) {
            // Check if this is the Ginto default model
            if ($modelId === 'ginto-default') {
                $result[] = [
                    'id' => $modelId,
                    'name' => self::GINTO_DEFAULT_CONFIG['name'],
                ];
            } else {
                $result[] = [
                    'id' => $modelId,
                    'name' => $modelId,
                ];
            }
        }
        
        return $result;
    }

    /**
     * Check if a model ID is the Ginto default model.
     */
    public static function isGintoDefault(string $modelId): bool
    {
        return $modelId === 'ginto-default';
    }

    /**
     * Check if provider is configured.
     * For local provider, check if the local LLM server is running.
     */
    public function isConfigured(): bool
    {
        // Local provider doesn't need an API key - just check if server is running
        if ($this->providerName === 'local') {
            try {
                $localConfig = \App\Core\LLM\LocalLLMConfig::getInstance();
                return $localConfig->isEnabled() && (
                    $localConfig->isReasoningServerHealthy() || 
                    $localConfig->isVisionServerHealthy()
                );
            } catch (\Throwable $e) {
                return false;
            }
        }
        
        // For cloud providers, check API key
        return $this->apiKey !== null && $this->httpClient !== null;
    }

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

    public function formatMessages(array $messages): array
    {
        return array_map(function ($msg) {
            $formatted = [
                'role' => $msg['role'],
            ];

            // Handle content
            if (isset($msg['content'])) {
                $formatted['content'] = $msg['content'];
            }

            // Handle tool calls (assistant message)
            if (isset($msg['tool_calls'])) {
                $formatted['tool_calls'] = $msg['tool_calls'];
            }

            // Handle tool results
            if ($msg['role'] === 'tool' && isset($msg['tool_call_id'])) {
                $formatted['tool_call_id'] = $msg['tool_call_id'];
            }

            return $formatted;
        }, $messages);
    }

    /**
     * Ensure the outgoing payload does not exceed provider limits.
     * Trims message content and prunes old messages when necessary.
     */
    protected function ensurePayloadWithinLimit(array &$payload): void
    {
        // Default payload byte limit (can be overridden via env)
        $defaultLimit = 512000; // 512KB
        $envKey = strtoupper($this->providerName) . '_PAYLOAD_MAX_BYTES';
        $limit = intval(getenv($envKey) ?: ($_ENV[$envKey] ?? 0)) ?: intval(getenv('LLM_PAYLOAD_MAX_BYTES') ?: ($_ENV['LLM_PAYLOAD_MAX_BYTES'] ?? $defaultLimit));

        if ($limit <= 0) {
            $limit = $defaultLimit;
        }

        // Quick check
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false && strlen($json) <= $limit) {
            return;
        }

        // If messages not present, nothing to trim
        if (empty($payload['messages']) || !is_array($payload['messages'])) {
            return;
        }

        // Per-message truncation thresholds
        $maxUserChars = 4000;
        $maxToolChars = 2000;
        $maxAssistantChars = 5000;

        // First pass: truncate large message contents in-place (older messages first)
        foreach ($payload['messages'] as $i => $msg) {
            if (!isset($msg['content']) || !is_string($msg['content'])) continue;
            $len = mb_strlen($msg['content']);
            if ($len > $maxUserChars) {
                $role = $msg['role'] ?? '';
                $limitChars = match ($role) {
                    'tool' => $maxToolChars,
                    'assistant' => $maxAssistantChars,
                    default => $maxUserChars,
                };
                $payload['messages'][$i]['content'] = mb_substr($msg['content'], -$limitChars); // keep tail (recent context)
                $payload['messages'][$i]['__truncated'] = true;
            }
        }

        // Check size again
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false && strlen($json) <= $limit) {
            return;
        }

        // Second pass: prune older non-system messages until under limit
        // Keep all system messages and the last N messages
        $keepLast = 20;
        $messages = $payload['messages'];
        // Preserve system messages at the front
        $systemMessages = array_values(array_filter($messages, fn($m) => ($m['role'] ?? '') === 'system'));
        $otherMessages = array_values(array_filter($messages, fn($m) => ($m['role'] ?? '') !== 'system'));

        // Truncate otherMessages to keep only the last $keepLast
        if (count($otherMessages) > $keepLast) {
            $otherMessages = array_slice($otherMessages, -$keepLast);
        }

        $payload['messages'] = array_merge($systemMessages, $otherMessages);

        // Add a short system note that history was truncated (prepend to system messages)
        if (!empty($payload['messages'])) {
            array_unshift($payload['messages'], [
                'role' => 'system',
                'content' => "[System] Conversation history truncated to reduce request size. Please re-run if more context is needed."
            ]);
        }

        // Final check - if still too big, aggressively truncate content to minimal lengths
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false && strlen($json) > $limit) {
            foreach ($payload['messages'] as $i => $msg) {
                if (isset($msg['content']) && is_string($msg['content'])) {
                    $payload['messages'][$i]['content'] = mb_substr($msg['content'], -500); // keep last 500 chars
                }
            }
        }
    }

    private function logActualOutgoingRequest(string $endpoint, array $payload, bool $stream): void
    {
        if ($this->providerName !== 'groq') {
            return;
        }

        try {
            $repoRoot = dirname(__DIR__, 4);
            $logsDir = $repoRoot . '/../storage/logs';
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0755, true);
            }

            $json = @json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return;
            }

            $ts = date('Ymd_His') . '_' . str_pad((string)(round(microtime(true) * 1000) % 1000), 3, '0', STR_PAD_LEFT);
            $mode = $stream ? 'stream' : 'chat';
            $fileName = "groq_actual_request_{$mode}_{$ts}.json";
            $filePath = $logsDir . '/' . $fileName;

            // Cap file size to avoid runaway logs while still keeping useful evidence.
            if (strlen($json) > 5 * 1024 * 1024) {
                $json = substr($json, 0, 5 * 1024 * 1024) . "\n...[TRUNCATED: payload exceeded 5MB]";
            }

            @file_put_contents($filePath, $json, LOCK_EX);

            $summary = [
                'timestamp' => date('c'),
                'provider' => $this->providerName,
                'model' => $payload['model'] ?? null,
                'endpoint' => $endpoint,
                'stream' => $stream,
                'bytes' => strlen($json),
                'file' => $fileName,
            ];
            @file_put_contents(
                $logsDir . '/groq_actual_request_summary.log',
                json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $_) {
            // Debug logging should never break requests.
        }
    }

    public function chat(array $messages, array $tools = [], array $options = []): LLMResponse
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->formatMessages($messages),
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 8192,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }
        
        // Add Groq's built-in browser_search tool for GPT-OSS models
        // These models support server-side web search - use auto so model decides when to search
        $modelName = $payload['model'] ?? '';
        $isGptOss = $this->providerName === 'groq' && (
            str_contains($modelName, 'gpt-oss') || 
            str_contains($modelName, 'openai/gpt-oss')
        );
        if ($isGptOss) {
            $payload['tools'] = $payload['tools'] ?? [];
            $payload['tools'][] = ['type' => 'browser_search'];
            // Use 'auto' so model can respond normally without forcing browser_search
            $payload['tool_choice'] = 'auto';
            // GPT-OSS with browser_search needs significantly more tokens - it consumes tokens for
            // search queries, page scraping, reasoning, and then the actual answer.
            // Groq limits max_completion_tokens to 65536 even though context_window is 131072.
            $payload['max_completion_tokens'] = $options['max_completion_tokens'] ?? 65536;
            unset($payload['max_tokens']); // Use max_completion_tokens instead
        }

        // Ensure payload is within acceptable size limits for provider API
        $this->ensurePayloadWithinLimit($payload);
        $this->logActualOutgoingRequest('chat/completions', $payload, false);

        try {
            $response = $this->post('chat/completions', $payload);
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
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 8192,
            'stream' => true,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }
        
        // Add Groq's built-in browser_search tool for GPT-OSS models
        // These models support server-side web search - use auto so model decides when to search
        $modelName = $payload['model'] ?? '';
        $isGptOss = $this->providerName === 'groq' && (
            str_contains($modelName, 'gpt-oss') || 
            str_contains($modelName, 'openai/gpt-oss')
        );
        if ($isGptOss) {
            $payload['tools'] = $payload['tools'] ?? [];
            $payload['tools'][] = ['type' => 'browser_search'];
            // Use 'auto' so model can respond normally without forcing browser_search
            $payload['tool_choice'] = 'auto';
            // GPT-OSS with browser_search needs significantly more tokens - it consumes tokens for
            // search queries, page scraping, reasoning, and then the actual answer.
            // Groq limits max_completion_tokens to 65536 even though context_window is 131072.
            $payload['max_completion_tokens'] = $options['max_completion_tokens'] ?? 65536;
            unset($payload['max_tokens']); // Use max_completion_tokens instead
        }

        $content = '';
        $toolCalls = [];
        $finishReason = LLMResponse::FINISH_STOP;
        $model = null;

        // Ensure streaming payload is within provider limits before opening a stream
        $this->ensurePayloadWithinLimit($payload);
        $this->logActualOutgoingRequest('chat/completions', $payload, true);

        try {
            $this->postStream('chat/completions', $payload, function ($chunk) use (&$content, &$toolCalls, &$finishReason, &$model, $onChunk) {
                // The Abstract postStream now forwards raw JSON strings. Accept
                // either a decoded array or a raw JSON string.
                if (is_string($chunk)) {
                    $decoded = json_decode($chunk, true);
                    if (is_array($decoded)) {
                        $chunk = $decoded;
                    } else {
                        // Non-JSON payload — ignore
                        return;
                    }
                }
                
                // Log the raw chunk for debugging Groq's streaming format
                // error_log('[Groq Stream] ' . json_encode($chunk));
                
                $delta = $chunk['choices'][0]['delta'] ?? [];
                $model = $chunk['model'] ?? $model;
                
                // Handle Groq's executed_tools (browser_search results) - inside delta
                // Send structured activity events for the frontend to display
                if (isset($delta['executed_tools']) && is_array($delta['executed_tools'])) {
                    if ($onChunk) {
                        foreach ($delta['executed_tools'] as $et) {
                            $toolName = $et['name'] ?? $et['type'] ?? '';
                            $toolType = $et['type'] ?? '';
                            
                            // Only notify on the second occurrence (when output is available)
                            if (!isset($et['output'])) {
                                continue;
                            }
                            
                            if ($toolName === 'browser.search' || $toolType === 'browser_search') {
                                $args = json_decode($et['arguments'] ?? '{}', true);
                                $query = $args['query'] ?? 'web';
                                // Send as structured activity event
                                $onChunk(null, [
                                    'activity' => 'websearch',
                                    'type' => 'search',
                                    'query' => $query,
                                    'status' => 'completed'
                                ]);
                            } elseif ($toolName === 'browser.open' || $toolType === 'browser.open') {
                                // Extract URL from search_results if available
                                $url = $et['search_results']['results'][0]['url'] ?? null;
                                if ($url) {
                                    $domain = parse_url($url, PHP_URL_HOST) ?: $url;
                                    $onChunk(null, [
                                        'activity' => 'websearch',
                                        'type' => 'read',
                                        'url' => $url,
                                        'domain' => $domain,
                                        'status' => 'completed'
                                    ]);
                                }
                            }
                        }
                    }
                }
                
                // Handle Groq's x_groq field (contains tool execution details)
                if (isset($chunk['x_groq'])) {
                    // Suppress x_groq logging unless debugging
                    // error_log('[Groq x_groq] ' . json_encode($chunk['x_groq']));
                }

                // Handle content delta (the actual answer)
                if (isset($delta['content'])) {
                    $content .= $delta['content'];
                    if ($onChunk) {
                        $onChunk($delta['content'], null);
                    }
                }
                
                // Handle reasoning/thinking tokens (Groq's reasoning models)
                // These come with channel: "analysis" and should be displayed separately
                if (isset($delta['reasoning']) || isset($delta['reasoning_content'])) {
                    $reasoning = $delta['reasoning'] ?? $delta['reasoning_content'] ?? '';
                    if ($reasoning && $onChunk) {
                        // Send reasoning as a separate event type, NOT mixed with content
                        $onChunk(null, [
                            'reasoning' => true,
                            'text' => $reasoning,
                            'channel' => $delta['channel'] ?? 'analysis'
                        ]);
                    }
                }

                // Handle tool call deltas
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tc) {
                        $index = $tc['index'] ?? 0;
                        if (!isset($toolCalls[$index])) {
                            $toolCalls[$index] = [
                                'id' => $tc['id'] ?? '',
                                'name' => '',
                                'arguments' => '',
                            ];
                        }
                        if (isset($tc['id'])) {
                            $toolCalls[$index]['id'] = $tc['id'];
                        }
                        if (isset($tc['function']['name'])) {
                            $toolCalls[$index]['name'] .= $tc['function']['name'];
                        }
                        if (isset($tc['function']['arguments'])) {
                            $toolCalls[$index]['arguments'] .= $tc['function']['arguments'];
                        }
                    }
                }

                // Handle finish reason
                if (isset($chunk['choices'][0]['finish_reason'])) {
                    $fr = $chunk['choices'][0]['finish_reason'];
                    $finishReason = match ($fr) {
                        'tool_calls' => LLMResponse::FINISH_TOOL_CALLS,
                        'length' => LLMResponse::FINISH_LENGTH,
                        default => LLMResponse::FINISH_STOP,
                    };
                }
            });

            // Parse accumulated tool call arguments
            $parsedToolCalls = array_values(array_map(function ($tc) {
                $tc['arguments'] = json_decode($tc['arguments'], true) ?? [];
                return $tc;
            }, $toolCalls));

            return new LLMResponse(
                content: $content,
                toolCalls: $parsedToolCalls,
                finishReason: $finishReason,
                model: $model
            );
        } catch (\Throwable $e) {
            return LLMResponse::error($e->getMessage());
        }
    }

    protected function parseResponse(array $response): LLMResponse
    {
        $choice = $response['choices'][0] ?? null;
        if (!$choice) {
            return LLMResponse::error('No choices in response', $response);
        }

        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? '';
        $toolCalls = [];
        
        // Handle Groq's executed_tools (browser_search results) for non-streaming
        // Prepend source info to content if web search was performed
        if (!empty($response['executed_tools'])) {
            $sources = [];
            foreach ($response['executed_tools'] as $et) {
                $toolType = $et['type'] ?? '';
                if ($toolType === 'browser_search' || $toolType === 'browser.search') {
                    $query = $et['query'] ?? null;
                    if ($query) $sources[] = "search: $query";
                } elseif ($toolType === 'browser_open' || $toolType === 'browser.open') {
                    $url = $et['url'] ?? null;
                    if ($url) {
                        // Extract domain for cleaner display
                        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
                        $sources[] = $domain;
                    }
                }
            }
            if (!empty($sources)) {
                $sourceList = implode(', ', array_slice($sources, 0, 5));
                $content = "🔍 *Searched the web* ($sourceList)\n\n" . $content;
            }
        }
        
        // Handle Groq's reasoning field (separate from content in GPT-OSS models)
        if (!empty($response['reasoning'])) {
            // Optionally prepend reasoning in a collapsible format
            // For now, we just include it cleanly
            $reasoning = trim($response['reasoning']);
            if ($reasoning) {
                $content = "<details><summary>💭 Reasoning</summary>\n\n$reasoning\n\n</details>\n\n" . $content;
            }
        }

        // Parse tool calls
        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $toolCalls[] = [
                    'id' => $tc['id'] ?? uniqid('tc_'),
                    'name' => $tc['function']['name'] ?? '',
                    'arguments' => is_string($tc['function']['arguments'] ?? '')
                        ? (json_decode($tc['function']['arguments'], true) ?? [])
                        : ($tc['function']['arguments'] ?? []),
                ];
            }
        }

        $finishReason = match ($choice['finish_reason'] ?? 'stop') {
            'tool_calls' => LLMResponse::FINISH_TOOL_CALLS,
            'length' => LLMResponse::FINISH_LENGTH,
            default => LLMResponse::FINISH_STOP,
        };

        return new LLMResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            model: $response['model'] ?? null,
            usage: $response['usage'] ?? [],
            raw: $response
        );
    }
}
