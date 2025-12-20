<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc;

use Psy\Readline\Hoa\ProtocolException;

class Error
{
    /**
     * @param  mixed|null  $data  Optional additional data
     */
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly mixed $data = null,
    ) {}

    /**
     * @throws ProtocolException
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['code']) || ! is_int($data['code'])) {
            throw new ProtocolException('Invalid or missing "code" field in error object.');
        }
        if (! isset($data['message']) || ! is_string($data['message'])) {
            throw new ProtocolException('Invalid or missing "message" field in error object.');
        }

        return new self(
            code: $data['code'],
            message: $data['message'],
            data: $data['data'] ?? null // Data is optional
        );
    }

    public function toArray(): array // Primarily for internal logging/debugging
    {
        $payload = [
            'code' => $this->code,
            'message' => $this->message,
        ];
        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        return $payload;
    }
}
