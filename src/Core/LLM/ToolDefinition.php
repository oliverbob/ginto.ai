<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * Represents a normalized tool definition.
 * 
 * This class provides a provider-agnostic representation of tools
 * that can be converted to any provider's specific format.
 */
class ToolDefinition
{
    private string $name;
    private string $description;
    private array $parameters;
    private ?string $source;
    private ?string $handler;

    public function __construct(
        string $name,
        string $description = '',
        array $parameters = [],
        ?string $source = null,
        ?string $handler = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters ?: [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
        $this->source = $source;
        $this->handler = $handler;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getHandler(): ?string
    {
        return $this->handler;
    }

    /**
     * Convert to OpenAI-compatible format.
     */
    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }

    /**
     * Convert to Anthropic format.
     */
    public function toAnthropicFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->parameters,
        ];
    }

    /**
     * Convert to normalized array format.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'source' => $this->source,
            'handler' => $this->handler,
        ];
    }

    /**
     * Create from array (supports multiple formats).
     */
    public static function fromArray(array $data): self
    {
        // OpenAI format with 'function' wrapper
        if (isset($data['type']) && $data['type'] === 'function' && isset($data['function'])) {
            return new self(
                name: $data['function']['name'] ?? '',
                description: $data['function']['description'] ?? '',
                parameters: $data['function']['parameters'] ?? [],
                source: $data['source'] ?? null,
                handler: $data['handler'] ?? null
            );
        }

        // Anthropic format with 'input_schema'
        if (isset($data['input_schema'])) {
            return new self(
                name: $data['name'] ?? '',
                description: $data['description'] ?? '',
                parameters: $data['input_schema'],
                source: $data['source'] ?? null,
                handler: $data['handler'] ?? null
            );
        }

        // Normalized format
        return new self(
            name: $data['name'] ?? $data['id'] ?? '',
            description: $data['description'] ?? '',
            parameters: $data['parameters'] ?? $data['schema'] ?? [],
            source: $data['source'] ?? null,
            handler: $data['handler'] ?? null
        );
    }

    /**
     * Create multiple ToolDefinitions from an array of tool data.
     * 
     * @param array $tools Array of tool definitions in any format
     * @return ToolDefinition[]
     */
    public static function fromArrayMultiple(array $tools): array
    {
        return array_map(fn($t) => self::fromArray($t), $tools);
    }

    /**
     * Convert multiple ToolDefinitions to OpenAI format.
     * 
     * @param ToolDefinition[] $tools
     * @return array
     */
    public static function toOpenAIFormatMultiple(array $tools): array
    {
        return array_map(fn($t) => $t->toOpenAIFormat(), $tools);
    }

    /**
     * Convert multiple ToolDefinitions to Anthropic format.
     * 
     * @param ToolDefinition[] $tools
     * @return array
     */
    public static function toAnthropicFormatMultiple(array $tools): array
    {
        return array_map(fn($t) => $t->toAnthropicFormat(), $tools);
    }
}
