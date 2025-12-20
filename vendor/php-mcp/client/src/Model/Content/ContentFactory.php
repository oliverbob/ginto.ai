<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model\Content;

use PhpMcp\Client\Contracts\ContentInterface;
use PhpMcp\Client\Exception\ProtocolException;

class ContentFactory
{
    /** @throws ProtocolException */
    public static function createFromArray(array $data): ContentInterface
    {
        if (empty($data['type']) || ! is_string($data['type'])) {
            throw new ProtocolException("Missing or invalid 'type' field in content data.");
        }

        return match ($data['type']) {
            'text' => TextContent::fromArray($data),
            'image' => ImageContent::fromArray($data),
            'audio' => AudioContent::fromArray($data),
            'resource' => ResourceContent::fromArray($data),
            default => throw new ProtocolException("Unsupported content type '{$data['type']}'.")
        };
    }
}
