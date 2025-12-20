<?php

namespace PhpMcp\Client\Model\Definitions;

use PhpMcp\Client\Exception\ProtocolException;

/**
 * Represents an MCP Resource Template.
 */
class ResourceTemplateDefinition
{
    /**
     * Resource name pattern regex - must contain only alphanumeric characters, underscores, and hyphens.
     */
    private const RESOURCE_NAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * URI Template pattern regex - requires a valid scheme, followed by colon and path with at least one placeholder.
     * Example patterns: config://{key}, file://{path}/contents.txt, db://{table}/{id}, etc.
     */
    private const URI_TEMPLATE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/.*{[^{}]+}.*/';

    /**
     * @param  string  $uriTemplate  A URI template (RFC 6570).
     * @param  string  $name  A human-readable name for the template type.
     * @param  string|null  $description  A description of what this template is for.
     * @param  string|null  $mimeType  Optional default MIME type for resources matching this template.
     * @param  array<string, mixed>  $annotations  Optional annotations (audience, priority).
     *
     * @throws \InvalidArgumentException If the URI template doesn't match the required pattern.
     */
    public function __construct(
        public readonly string $uriTemplate,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $mimeType,
        public readonly array $annotations = []
    ) {
        $this->validate();
    }

    /**
     * Validates the definition parameters
     *
     * @throws \InvalidArgumentException If the URI template is invalid
     */
    private function validate(): void
    {
        if (! preg_match(self::URI_TEMPLATE_PATTERN, $this->uriTemplate)) {
            throw new \InvalidArgumentException(
                "Resource URI template '{$this->uriTemplate}' is invalid. URI templates must match the pattern "
                .self::URI_TEMPLATE_PATTERN.' (valid scheme followed by :// and path with placeholder(s) in curly braces).'
            );
        }

        if (! preg_match(self::RESOURCE_NAME_PATTERN, $this->name)) {
            throw new \InvalidArgumentException(
                "Resource name '{$this->name}' is invalid. Resource names must match the pattern ".self::RESOURCE_NAME_PATTERN
                .' (alphanumeric characters, underscores, and hyphens only).'
            );
        }
    }

    /**
     * Reconstruct a ResourceTemplateDefinition from its array representation.
     *
     * @param  array  $data  The array representation of a ResourceTemplateDefinition
     * @return static The reconstructed ResourceTemplateDefinition
     */
    public static function fromArray(array $data): static
    {
        if (empty($data['uriTemplate']) || ! is_string($data['uriTemplate'])) {
            throw new ProtocolException("Invalid or missing 'uriTemplate' in ResourceTemplateDefinition data.");
        }
        if (empty($data['name']) || ! is_string($data['name'])) {
            throw new ProtocolException("Invalid or missing 'name' in ResourceTemplateDefinition data.");
        }

        return new self(
            uriTemplate: $data['uriTemplate'],
            name: $data['name'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            mimeType: isset($data['mimeType']) && is_string($data['mimeType']) ? $data['mimeType'] : null,
            annotations: isset($data['annotations']) && is_array($data['annotations']) ? $data['annotations'] : []
        );
    }

    /**
     * Formats the definition into the structure expected by MCP's 'resources/templates/list'.
     *
     * @return array{uriTemplate: string, name: string, description?: string, mimeType?: string, annotations?: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'uriTemplate' => $this->uriTemplate,
            'name' => $this->name,
            'description' => $this->description,
            'mimeType' => $this->mimeType,
            'annotations' => $this->annotations,
        ];
    }
}
