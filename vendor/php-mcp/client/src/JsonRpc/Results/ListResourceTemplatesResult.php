<?php

namespace PhpMcp\Client\JsonRpc\Results;

use PhpMcp\Client\JsonRpc\Result;
use PhpMcp\Client\Model\Definitions\ResourceTemplateDefinition;

class ListResourceTemplatesResult extends Result
{
    /**
     * @param  array<ResourceTemplateDefinition>  $resourceTemplates  The list of resource template definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public function __construct(
        public readonly array $resourceTemplates,
        public readonly ?string $nextCursor = null
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            resourceTemplates: array_map(fn (array $templateData) => ResourceTemplateDefinition::fromArray($templateData), $data['resourceTemplates']),
            nextCursor: $data['nextCursor'] ?? null
        );
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        $result = [
            'resourceTemplates' => array_map(fn (ResourceTemplateDefinition $t) => $t->toArray(), $this->resourceTemplates),
        ];

        if ($this->nextCursor) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }
}
