<?php

namespace PhpMcp\Client\Model\Definitions;

use PhpMcp\Client\Exception\ProtocolException;

/**
 * Represents an MCP Prompt or Prompt Template.
 */
class PromptDefinition
{
    /**
     * Prompt name pattern regex - must contain only alphanumeric characters, underscores, and hyphens.
     * This matches the same pattern as used for tool names.
     */
    private const PROMPT_NAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * @param  string  $name  The name of the MCP prompt.
     * @param  string|null  $description  A description of what this prompt provides.
     * @param  PromptArgumentDefinition[]  $arguments  Definitions of arguments used for templating. Empty if not a template.
     *
     * @throws \InvalidArgumentException If the prompt name doesn't match the required pattern.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $arguments = []
    ) {
        $this->validate();
    }

    /**
     * Validates the definition parameters
     *
     * @throws \InvalidArgumentException If the prompt name is invalid
     */
    private function validate(): void
    {
        if (! preg_match(self::PROMPT_NAME_PATTERN, $this->name)) {
            throw new \InvalidArgumentException(
                "Prompt name '{$this->name}' is invalid. Prompt names must match the pattern ".self::PROMPT_NAME_PATTERN
                .' (alphanumeric characters, underscores, and hyphens only).'
            );
        }
    }

    public function isTemplate(): bool
    {
        return ! empty($this->arguments);
    }

    /**
     * Reconstruct a PromptDefinition from its array representation.
     *
     * @param  array  $data  The array representation of a PromptDefinition
     * @return static The reconstructed PromptDefinition
     */
    public static function fromArray(array $data): static
    {

        if (empty($data['name']) || ! is_string($data['name'])) {
            throw new ProtocolException("Invalid or missing 'name' in PromptDefinition data.");
        }

        $args = [];
        if (isset($data['arguments'])) {
            if (! is_array($data['arguments'])) {
                throw new ProtocolException("Invalid 'arguments' format in PromptDefinition.");
            }
            foreach ($data['arguments'] as $argData) {
                if (! is_array($argData)) {
                    throw new ProtocolException('Invalid argument item format in PromptDefinition.');
                }
                $args[] = PromptArgumentDefinition::fromArray($argData);
            }
        }

        return new self(
            name: $data['name'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            arguments: $args
        );
    }

    /**
     * Formats the definition into the structure expected by MCP's 'prompts/list'.
     *
     * @return array{name: string, description?: string, arguments?: list<array{name: string, description?: string, required?: bool}>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'arguments' => array_map(fn ($arg) => $arg->toArray(), $this->arguments),
        ];
    }
}
