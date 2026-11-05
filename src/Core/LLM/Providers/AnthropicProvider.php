<?php

declare(strict_types=1);

namespace App\Core\LLM\Providers;

use App\Core\LLM\AbstractLLMProvider;
use App\Core\LLM\LLMResponse;

/**
 * Anthropic Claude provider.
 * 
 * Implements the Anthropic Messages API which uses a different format:
 * - Content blocks instead of simple content strings
 * - tool_use blocks for tool calls (not tool_calls)
 * - tool_result blocks for tool responses
 */
class AnthropicProvider extends AbstractLLMProvider
{
    protected static array $availableModels = [
        'claude-sonnet-4-20250514',
        'claude-3-5-sonnet-20241022',
        'claude-3-5-haiku-20241022',
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
    ];

    public function getName(): string
    {
        return 'anthropic';
    }

    public function getStyle(): string
    {
        return 'anthropic';
    }

    protected function getEnvApiKey(): ?string
    {
        return getenv('ANTHROPIC_API_KEY') ?: ($_ENV['ANTHROPIC_API_KEY'] ?? null);
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'https://api.anthropic.com/v1/';
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];
    }

    public function getDefaultModel(): string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function getModels(): array
    {
        return self::$availableModels;
    }

    public function formatTools(array $tools): array
    {
        $normalized = $this->normalizeTools($tools);
        
        return array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'input_schema' => $tool['parameters'] ?? [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ];
        }, $normalized);
    }

    public function formatMessages(array $messages): array
    {
        $formatted = [];
        $systemMessage = null;

        foreach ($messages as $msg) {
            $role = $msg['role'];

            // Extract system message (Anthropic uses separate 'system' parameter)
            if ($role === 'system') {
                $systemMessage = ($systemMessage ? $systemMessage . "\n\n" : '') . ($msg['content'] ?? '');
                continue;
            }

            // Convert tool results to Anthropic format
            if ($role === 'tool') {
                // Anthropic expects tool results as user messages with tool_result content blocks
                $formatted[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $msg['tool_call_id'] ?? '',
                            'content' => $msg['content'] ?? '',
                        ],
                    ],
                ];
                continue;
            }

            // Handle assistant messages with tool calls
            if ($role === 'assistant' && isset($msg['tool_calls'])) {
                $content = [];
                
                if (!empty($msg['content'])) {
                    $content[] = [
                        'type' => 'text',
                        'text' => $msg['content'],
                    ];
                }

                foreach ($msg['tool_calls'] as $tc) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $tc['id'] ?? uniqid('tu_'),
                        'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                        'input' => is_string($tc['function']['arguments'] ?? '')
                            ? (json_decode($tc['function']['arguments'], true) ?? [])
                            : ($tc['function']['arguments'] ?? $tc['arguments'] ?? []),
                    ];
                }

                $formatted[] = [
                    'role' => 'assistant',
                    'content' => $content,
                ];
                continue;
            }

            // Regular messages
            $formatted[] = [
                'role' => $role,
                'content' => $msg['content'] ?? '',
            ];
        }

        return [
            'messages' => $formatted,
            'system' => $systemMessage,
        ];
    }

    public function chat(array $messages, array $tools = [], array $options = []): LLMResponse
    {
        $formattedMessages = $this->formatMessages($messages);
        
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $formattedMessages['messages'],
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ];

        if ($formattedMessages['system']) {
            $payload['system'] = $formattedMessages['system'];
        }

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
            if (isset($options['tool_choice'])) {
                $payload['tool_choice'] = $this->formatToolChoice($options['tool_choice']);
            }
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        try {
            $response = $this->post('messages', $payload);
            return $this->parseResponse($response);
        } catch (\Throwable $e) {
            return LLMResponse::error($e->getMessage());
        }
    }

    public function chatStream(array $messages, array $tools = [], array $options = [], callable $onChunk = null): LLMResponse
    {
        $formattedMessages = $this->formatMessages($messages);
        
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $formattedMessages['messages'],
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'stream' => true,
        ];

        if ($formattedMessages['system']) {
            $payload['system'] = $formattedMessages['system'];
        }

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $content = '';
        $toolCalls = [];
        $currentToolCall = null;
        $finishReason = LLMResponse::FINISH_STOP;
        $model = null;

        try {
            $this->postStream('messages', $payload, function ($event) use (&$content, &$toolCalls, &$currentToolCall, &$finishReason, &$model, $onChunk) {
                // Accept raw JSON string or decoded array
                if (is_string($event)) {
                    $decoded = json_decode($event, true);
                    if (is_array($decoded)) {
                        $event = $decoded;
                    } else {
                        return; // ignore non-JSON fragments
                    }
                }
                $type = $event['type'] ?? '';

                switch ($type) {
                    case 'message_start':
                        $model = $event['message']['model'] ?? null;
                        break;

                    case 'content_block_start':
                        $block = $event['content_block'] ?? [];
                        if (($block['type'] ?? '') === 'tool_use') {
                            $currentToolCall = [
                                'id' => $block['id'] ?? uniqid('tu_'),
                                'name' => $block['name'] ?? '',
                                'arguments' => '',
                            ];
                        }
                        break;

                    case 'content_block_delta':
                        $delta = $event['delta'] ?? [];
                        if (($delta['type'] ?? '') === 'text_delta') {
                            $content .= $delta['text'] ?? '';
                            if ($onChunk) {
                                $onChunk($delta['text'] ?? '', null);
                            }
                        } elseif (($delta['type'] ?? '') === 'input_json_delta' && $currentToolCall) {
                            $currentToolCall['arguments'] .= $delta['partial_json'] ?? '';
                        }
                        break;

                    case 'content_block_stop':
                        if ($currentToolCall) {
                            $currentToolCall['arguments'] = json_decode($currentToolCall['arguments'], true) ?? [];
                            $toolCalls[] = $currentToolCall;
                            $currentToolCall = null;
                        }
                        break;

                    case 'message_delta':
                        $stopReason = $event['delta']['stop_reason'] ?? null;
                        $finishReason = match ($stopReason) {
                            'tool_use' => LLMResponse::FINISH_TOOL_CALLS,
                            'max_tokens' => LLMResponse::FINISH_LENGTH,
                            default => LLMResponse::FINISH_STOP,
                        };
                        break;
                }
            });

            return new LLMResponse(
                content: $content,
                toolCalls: $toolCalls,
                finishReason: $finishReason,
                model: $model
            );
        } catch (\Throwable $e) {
            return LLMResponse::error($e->getMessage());
        }
    }

    protected function parseResponse(array $response): LLMResponse
    {
        $content = '';
        $toolCalls = [];

        foreach ($response['content'] ?? [] as $block) {
            $type = $block['type'] ?? '';
            
            if ($type === 'text') {
                $content .= $block['text'] ?? '';
            } elseif ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'] ?? uniqid('tu_'),
                    'name' => $block['name'] ?? '',
                    'arguments' => $block['input'] ?? [],
                ];
            }
        }

        $finishReason = match ($response['stop_reason'] ?? 'end_turn') {
            'tool_use' => LLMResponse::FINISH_TOOL_CALLS,
            'max_tokens' => LLMResponse::FINISH_LENGTH,
            default => LLMResponse::FINISH_STOP,
        };

        return new LLMResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            model: $response['model'] ?? null,
            usage: [
                'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
            ],
            raw: $response
        );
    }

    protected function formatToolChoice($choice): array
    {
        if ($choice === 'auto') {
            return ['type' => 'auto'];
        }
        if ($choice === 'any') {
            return ['type' => 'any'];
        }
        if (is_string($choice)) {
            return ['type' => 'tool', 'name' => $choice];
        }
        return $choice;
    }
}
