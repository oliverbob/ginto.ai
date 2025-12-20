<?php

namespace PhpMcp\Client\JsonRpc\Results;

use PhpMcp\Client\JsonRpc\Result;
use PhpMcp\Client\Model\Definitions\ResourceDefinition;

class ListResourcesResult extends Result
{
    /**
     * @param  array<ResourceDefinition>  $resources  The list of resource definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public function __construct(
        public readonly array $resources,
        public readonly ?string $nextCursor = null
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            resources: array_map(fn (array $resourceData) => ResourceDefinition::fromArray($resourceData), $data['resources']),
            nextCursor: $data['nextCursor'] ?? null
        );
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        $result = [
            'resources' => array_map(fn (ResourceDefinition $r) => $r->toArray(), $this->resources),
        ];

        if ($this->nextCursor !== null) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }
}
