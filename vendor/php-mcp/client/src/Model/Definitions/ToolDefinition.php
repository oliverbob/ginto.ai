<?php

namespace PhpMcp\Client\Model\Definitions;

use PhpMcp\Client\Exception\ProtocolException;

/**
 * Represents an MCP Tool.
 */
class ToolDefinition
{
    /**
     * Tool name pattern regex - must contain only alphanumeric characters, underscores, and hyphens.
     */
    private const TOOL_NAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * @param  string  $name  The name of the tool.
     * @param  string|null  $description  A human-readable description of the tool.
     * @param  array<string, mixed>  $inputSchema  A JSON Schema object (as a PHP array) defining the expected 'arguments' for the tool.
     *
     * @throws \InvalidArgumentException If the tool name doesn't match the required pattern.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $inputSchema,
    ) {
        $this->validate();
    }

    /**
     * Validates the definition parameters
     *
     * @throws \InvalidArgumentException If the tool name is invalid
     */
    private function validate(): void
    {
        if (! preg_match(self::TOOL_NAME_PATTERN, $this->name)) {
            throw new \InvalidArgumentException(
                "Tool name '{$this->name}' is invalid. Tool names must match the pattern ".self::TOOL_NAME_PATTERN
                .' (alphanumeric characters, underscores, and hyphens only).'
            );
        }
    }

    /**
     * Reconstruct a ToolDefinition from its array representation.
     *
     * @param  array  $data  The array representation of a ToolDefinition
     * @return static The reconstructed ToolDefinition
     */
    public static function fromArray(array $data): static
    {
        if (empty($data['name']) || ! is_string($data['name'])) {
            throw new ProtocolException("Invalid or missing 'name' in ToolDefinition data.");
        }

        // TODO: Validate inputSchema
        $inputSchema = $data['inputSchema'] ?? ['type' => 'object'];
        if (! is_array($inputSchema)) {
            throw new ProtocolException("Invalid 'inputSchema' in ToolDefinition data, must be an array/object.");
        }

        return new self(
            name: $data['name'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            inputSchema: $inputSchema
        );
    }

    /**
     * Convert the tool definition to MCP format.
     */
    public function toArray(): array
    {

        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
