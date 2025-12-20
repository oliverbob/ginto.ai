<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model\Content;

use PhpMcp\Client\Contracts\ContentInterface;
use PhpMcp\Client\Exception\ProtocolException;

class ResourceContent implements ContentInterface
{
    public function __construct(
        public readonly EmbeddedResource $resource
    ) {}

    public function getType(): string
    {
        return 'resource';
    }

    public function toArray(): array
    {
        return [
            'type' => 'resource',
            'resource' => $this->resource->toArray(),
        ];
    }

    /** @throws ProtocolException */
    public static function fromArray(array $data): self
    {
        if (! isset($data['resource']) || ! is_array($data['resource'])) {
            throw new ProtocolException("Invalid 'resource' field for ResourceContent.");
        }

        return new self(EmbeddedResource::fromArray($data['resource']));
    }
}
