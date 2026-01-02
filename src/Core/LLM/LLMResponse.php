<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * Represents a normalized response from any LLM provider.
 * 
 * Abstracts the differences between:
 * - OpenAI/Groq: tool_calls array in message
 * - Anthropic: tool_use content blocks
 */
class LLMResponse
{
    public const FINISH_STOP = 'stop';
    public const FINISH_TOOL_CALLS = 'tool_calls';
    public const FINISH_LENGTH = 'length';
    public const FINISH_ERROR = 'error';

    private string $content;
    private array $toolCalls;
    private string $finishReason;
    private ?string $model;
    private array $usage;
    private array $raw;

    public function __construct(
        string $content = '',
        array $toolCalls = [],
        string $finishReason = self::FINISH_STOP,
        ?string $model = null,
        array $usage = [],
        array $raw = []
    ) {
        $this->content = $content;
        $this->toolCalls = $toolCalls;
        $this->finishReason = $finishReason;
        $this->model = $model;
        $this->usage = $usage;
        $this->raw = $raw;
    }

    /**
     * Get the text content of the response.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Check if the response contains tool calls.
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * Get normalized tool calls.
     * 
     * @return array Array of [{id, name, arguments}]
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Get the finish reason.
     */
    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    /**
     * Get the model used.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get token usage information.
     * 
     * @return array {prompt_tokens, completion_tokens, total_tokens}
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    /**
     * Get the raw provider response.
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Check if the response indicates an error.
     */
    public function isError(): bool
    {
        return $this->finishReason === self::FINISH_ERROR;
    }

    /**
     * Convert to assistant message format for conversation history.
     */
    public function toAssistantMessage(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $this->content,
        ];

        if ($this->hasToolCalls()) {
            $message['tool_calls'] = array_map(function ($tc) {
                return [
                    'id' => $tc['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $tc['name'],
                        'arguments' => is_string($tc['arguments']) 
                            ? $tc['arguments'] 
                            : json_encode($tc['arguments']),
                    ],
                ];
            }, $this->toolCalls);
        }

        return $message;
    }

    /**
     * Create a tool result message for conversation history.
     */
    public static function createToolResultMessage(string $toolCallId, mixed $result): array
    {
        return [
            'role' => 'tool',
            'tool_call_id' => $toolCallId,
            'content' => is_string($result) ? $result : json_encode($result),
        ];
    }

    /**
     * Create an error response.
     */
    public static function error(string $message, array $raw = []): self
    {
        return new self(
            content: $message,
            toolCalls: [],
            finishReason: self::FINISH_ERROR,
            raw: $raw
        );
    }
}
