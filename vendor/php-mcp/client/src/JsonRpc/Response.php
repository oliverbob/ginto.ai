<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc;

use Psy\Readline\Hoa\ProtocolException;

final class Response extends Message
{
    /**
     * @param  string|int|null  $id  ID matching the request, or null for certain errors.
     * @param  mixed|null  $result  The result data (structure depends on method). Null if error is present.
     * @param  Error|null  $error  The error object. Null if result is present.
     */
    public function __construct(
        public string|int|null $id,
        public mixed $result = null,
        public ?Error $error = null,
    ) {
        // Basic validation: Must have result OR error, but not both (unless id is null?)
        if ($this->result !== null && $this->error !== null) {
            // Throw internal error? Log? Spec allows this flexibility.
        }
        if ($this->result === null && $this->error === null && $this->id !== null) {
            // Invalid according to spec for non-notification responses
        }
    }

    public function isSuccess(): bool
    {
        return $this->error === null && $this->result !== null;
    }

    public function isError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @throws ProtocolException
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new ProtocolException('Invalid or missing "jsonrpc" version in response. Must be "2.0".');
        }

        // ID can be string, int, or null (for parse/invalid request errors)
        $id = $data['id'] ?? null;
        if (! (is_string($id) || is_int($id) || $id === null)) {
            throw new ProtocolException('Invalid "id" field in response.');
        }

        $hasResult = array_key_exists('result', $data);
        $hasError = array_key_exists('error', $data);

        if ($hasResult && $hasError) {
            throw new ProtocolException('Invalid response: contains both "result" and "error".');
        }
        if (! $hasResult && ! $hasError) {
            throw new ProtocolException('Invalid response: must contain either "result" or "error".');
        }

        $error = null;
        $result = null;

        if ($hasError) {
            if (! is_array($data['error'])) { // Error MUST be an object
                throw new ProtocolException('Invalid "error" field in response: must be an object.');
            }
            $error = Error::fromArray($data['error']);
        } else {
            // Result exists, can be anything (null, scalar, array, object)
            $result = $data['result'];
        }

        return new self($id, $result, $error);
    }

    public function toArray(): array // Primarily for internal logging/debugging
    {
        $payload = ['jsonrpc' => $this->jsonrpc, 'id' => $this->id];
        if ($this->error !== null) {
            $payload['error'] = $this->error->toArray();
        } else {
            // Result can be anything (null, scalar, array, object)
            $payload['result'] = $this->result;
        }

        return $payload;
    }
}
