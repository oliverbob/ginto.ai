<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * Represents a tool execution result.
 * 
 * Provides a standardized way to format tool results for
 * inclusion in conversation history across different providers.
 */
class ToolResult
{
    private string $toolCallId;
    private mixed $result;
    private bool $isError;

    public function __construct(
        string $toolCallId,
        mixed $result,
        bool $isError = false
    ) {
        $this->toolCallId = $toolCallId;
        $this->result = $result;
        $this->isError = $isError;
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * Get result as string (for message content).
     */
    public function getResultString(): string
    {
        if (is_string($this->result)) {
            return $this->result;
        }
        return json_encode($this->result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert to OpenAI format for conversation history.
     */
    public function toOpenAIMessage(): array
    {
        return [
            'role' => 'tool',
            'tool_call_id' => $this->toolCallId,
            'content' => $this->getResultString(),
        ];
    }

    /**
     * Convert to Anthropic format for conversation history.
     * Note: Anthropic expects tool results as user messages with tool_result blocks.
     */
    public function toAnthropicMessage(): array
    {
        $block = [
            'type' => 'tool_result',
            'tool_use_id' => $this->toolCallId,
            'content' => $this->getResultString(),
        ];

        if ($this->isError) {
            $block['is_error'] = true;
        }

        return [
            'role' => 'user',
            'content' => [$block],
        ];
    }

    /**
     * Convert to message format based on provider style.
     */
    public function toMessage(string $style = 'openai'): array
    {
        return $style === 'anthropic' 
            ? $this->toAnthropicMessage() 
            : $this->toOpenAIMessage();
    }

    /**
     * Convert to assistant message format for the given provider.
     * This method auto-detects the provider type and uses the appropriate format.
     */
    public function toAssistantMessage(LLMProviderInterface $provider): array
    {
        // Check provider class name to determine format
        $className = get_class($provider);
        
        if (str_contains($className, 'Anthropic')) {
            return $this->toAnthropicMessage();
        }
        
        return $this->toOpenAIMessage();
    }

    /**
     * Create from a successful tool execution.
     */
    public static function success(string $toolCallId, mixed $result): self
    {
        return new self($toolCallId, $result, false);
    }

    /**
     * Create from a failed tool execution.
     */
    public static function error(string $toolCallId, string $errorMessage): self
    {
        return new self($toolCallId, ['error' => $errorMessage], true);
    }

    /**
     * Create from tool execution array result.
     */
    public static function fromExecutionResult(string $toolCallId, array $executionResult): self
    {
        if (!empty($executionResult['success'])) {
            return self::success($toolCallId, $executionResult['result'] ?? null);
        }
        return self::error($toolCallId, $executionResult['error'] ?? 'Unknown error');
    }
}
