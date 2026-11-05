<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * Represents a normalized tool call from an LLM response.
 * 
 * Abstracts the differences between:
 * - OpenAI/Groq: tool_calls array with function.name and function.arguments
 * - Anthropic: tool_use content blocks with name and input
 */
class ToolCall
{
    private string $id;
    private string $name;
    private array $arguments;

    public function __construct(
        string $id,
        string $name,
        array $arguments = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get a specific argument by name.
     */
    public function getArgument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }

    /**
     * Convert to normalized array format.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    /**
     * Convert to OpenAI format for conversation history.
     */
    public function toOpenAIFormat(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments),
            ],
        ];
    }

    /**
     * Convert to Anthropic format for conversation history.
     */
    public function toAnthropicFormat(): array
    {
        return [
            'type' => 'tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->arguments,
        ];
    }

    /**
     * Create from OpenAI format.
     */
    public static function fromOpenAI(array $data): self
    {
        $id = $data['id'] ?? uniqid('tc_');
        $name = $data['function']['name'] ?? $data['name'] ?? '';
        $argsRaw = $data['function']['arguments'] ?? $data['arguments'] ?? '{}';
        
        $arguments = is_string($argsRaw) 
            ? (json_decode($argsRaw, true) ?? [])
            : (is_array($argsRaw) ? $argsRaw : []);

        return new self($id, $name, $arguments);
    }

    /**
     * Create from Anthropic format.
     */
    public static function fromAnthropic(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('tu_'),
            name: $data['name'] ?? '',
            arguments: $data['input'] ?? []
        );
    }

    /**
     * Create from any format (auto-detect).
     */
    public static function fromArray(array $data): self
    {
        // OpenAI format detection
        if (isset($data['function']) || isset($data['type']) && $data['type'] === 'function') {
            return self::fromOpenAI($data);
        }

        // Anthropic format detection
        if (isset($data['input']) || (isset($data['type']) && $data['type'] === 'tool_use')) {
            return self::fromAnthropic($data);
        }

        // Normalized format
        return new self(
            id: $data['id'] ?? uniqid('tc_'),
            name: $data['name'] ?? '',
            arguments: $data['arguments'] ?? []
        );
    }

    /**
     * Create from provider-agnostic normalized data.
     * This is a convenience factory for already-normalized arrays.
     */
    public static function fromProvider(string $id, string $name, array $arguments): self
    {
        // Normalize common aliases so downstream handlers can rely on canonical keys
        $args = $arguments ?? [];
        if (is_array($args)) {
            if (array_key_exists('path', $args) && !array_key_exists('file_path', $args)) {
                $args['file_path'] = $args['path'];
            }
            if (array_key_exists('filepath', $args) && !array_key_exists('file_path', $args)) {
                $args['file_path'] = $args['filepath'];
            }
            if (array_key_exists('contents', $args) && !array_key_exists('content', $args)) {
                $args['content'] = $args['contents'];
            }
            // Common plural/singular normalizations
            if (array_key_exists('files', $args) && !is_array($args['files'])) {
                // If files provided as JSON string, try decode
                if (is_string($args['files'])) {
                    $decoded = json_decode($args['files'], true);
                    if (is_array($decoded)) $args['files'] = $decoded;
                }
            }
        }

        return new self($id, $name, $args);
    }

    /**
     * Parse multiple tool calls from response data.
     * 
     * @param array $data Response data (OpenAI or Anthropic format)
     * @param string $style 'openai' or 'anthropic'
     * @return ToolCall[]
     */
    public static function parseFromResponse(array $data, string $style = 'openai'): array
    {
        $toolCalls = [];

        if ($style === 'anthropic') {
            // Anthropic: look for tool_use blocks in content
            foreach ($data['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = self::fromAnthropic($block);
                }
            }
        } else {
            // OpenAI-compatible: look for tool_calls in message
            $message = $data['choices'][0]['message'] ?? $data;
            foreach ($message['tool_calls'] ?? [] as $tc) {
                $toolCalls[] = self::fromOpenAI($tc);
            }
        }

        return $toolCalls;
    }
}
