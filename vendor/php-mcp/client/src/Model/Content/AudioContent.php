<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model\Content;

use PhpMcp\Client\Contracts\ContentInterface;
use PhpMcp\Client\Exception\ProtocolException;

class AudioContent implements ContentInterface
{
    public function __construct(
        public readonly string $data,
        public readonly string $mimeType
    ) {}

    public function getType(): string
    {
        return 'audio';
    }

    public function toArray(): array
    {
        return [
            'type' => 'audio',
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ];
    }

    /** @throws ProtocolException */
    public static function fromArray(array $data): self
    {
        if (! isset($data['data']) || ! is_string($data['data'])) {
            throw new ProtocolException("Invalid 'data' field for AudioContent.");
        }

        if (! isset($data['mimeType']) || ! is_string($data['mimeType'])) {
            throw new ProtocolException("Invalid 'mimeType' field for AudioContent.");
        }

        return new self($data['data'], $data['mimeType']);
    }
}
