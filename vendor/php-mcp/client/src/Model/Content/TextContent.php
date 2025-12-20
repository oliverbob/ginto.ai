<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model\Content;

use PhpMcp\Client\Contracts\ContentInterface;
use PhpMcp\Client\Exception\ProtocolException;

class TextContent implements ContentInterface
{
    public function __construct(public readonly string $text) {}

    public function getType(): string
    {
        return 'text';
    }

    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }

    /** @throws ProtocolException */
    public static function fromArray(array $data): self
    {
        if (! isset($data['text']) || ! is_string($data['text'])) {
            throw new ProtocolException("Invalid 'text' field for TextContent.");
        }

        return new self($data['text']);
    }
}
