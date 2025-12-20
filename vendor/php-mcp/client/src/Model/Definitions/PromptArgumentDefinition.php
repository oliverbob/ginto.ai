<?php

namespace PhpMcp\Client\Model\Definitions;

/**
 * Represents a defined argument for an MCP Prompt template.
 * Compliant with MCP 'PromptArgument'.
 */
class PromptArgumentDefinition
{
    /**
     * @param  string  $name  The name of the argument.
     * @param  string|null  $description  A human-readable description of the argument.
     * @param  bool  $required  Whether this argument must be provided when getting the prompt. Defaults to false.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $required = false
    ) {}

    /**
     * Reconstruct a PromptArgumentDefinition from its array representation.
     *
     * @param  array  $data  The array representation of a PromptArgumentDefinition
     * @return static The reconstructed PromptArgumentDefinition
     */
    public static function fromArray(array $data): static
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            required: $data['required'] ?? false
        );
    }

    /**
     * Formats the definition into the structure expected by MCP's 'Prompt.arguments'.
     *
     * @return array{name: string, description?: string, required?: bool}
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'required' => $this->required,
        ];

        if ($this->description) {
            $array['description'] = $this->description;
        }

        return $array;
    }
}
