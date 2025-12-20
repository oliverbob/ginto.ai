<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model\Content;

use PhpMcp\Client\Exception\ProtocolException;

class EmbeddedResource
{
    public function __construct(
        public readonly string $uri,
        public readonly string $mimeType,
        public readonly ?string $text = null,
        public readonly ?string $blob = null // Base64 encoded
    ) {
        if ($this->text === null && $this->blob === null) {
            throw new \InvalidArgumentException("EmbeddedResource must have either 'text' or 'blob'.");
        }
        if ($this->text !== null && $this->blob !== null) {
            throw new \InvalidArgumentException("EmbeddedResource cannot have both 'text' and 'blob'.");
        }
    }

    /** @throws ProtocolException */
    public static function fromArray(array $data): self
    {
        if (empty($data['uri']) || ! is_string($data['uri'])) {
            throw new ProtocolException("Missing 'uri'");
        }
        if (empty($data['mimeType']) || ! is_string($data['mimeType'])) {
            throw new ProtocolException("Missing 'mimeType'");
        }
        $text = isset($data['text']) && is_string($data['text']) ? $data['text'] : null;
        $blob = isset($data['blob']) && is_string($data['blob']) ? $data['blob'] : null;

        try {
            return new self($data['uri'], $data['mimeType'], $text, $blob);
        } catch (\InvalidArgumentException $e) {
            throw new ProtocolException($e->getMessage());
        }
    }

    public function toArray(): array
    {
        $arr = ['uri' => $this->uri, 'mimeType' => $this->mimeType];

        if ($this->text !== null) {
            $arr['text'] = $this->text;
        }

        if ($this->blob !== null) {
            $arr['blob'] = $this->blob;
        }

        return $arr;
    }
}
