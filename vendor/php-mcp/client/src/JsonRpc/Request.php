<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc;

use Psy\Readline\Hoa\ProtocolException;

final class Request extends Message
{
    /**
     * @param  array<string, mixed>|array<int, mixed>  $params  Structure specific to the method
     */
    public function __construct(
        public string|int $id,
        public string $method,
        public array $params = []
    ) {}

    /**
     * Create a Request object from an array representation.
     *
     * @param  array  $data  Raw decoded JSON-RPC data
     *
     * @throws ProtocolException If the data doesn't conform to JSON-RPC 2.0 request structure
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new ProtocolException('Invalid or missing "jsonrpc" version in request. Must be "2.0".');
        }
        if (! isset($data['method']) || ! is_string($data['method']) || $data['method'] === '') {
            throw new ProtocolException('Invalid or missing "method" field in request.');
        }
        if (! isset($data['id']) || ! (is_string($data['id']) || is_int($data['id']))) {
            throw new ProtocolException('Invalid or missing "id" field in request.');
        }

        $params = $data['params'] ?? [];
        if (! is_array($params) && ! $params instanceof \stdClass) {
            throw new ProtocolException('Invalid "params" field in request: must be an array or object.');
        }

        return new self(
            id: $data['id'],
            method: $data['method'],
            params: $params,
        );
    }

    public function toArray(): array
    {
        $result = [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'method' => $this->method,
        ];

        $paramsToSend = $this->params;
        if (is_array($paramsToSend) && empty($paramsToSend)) {
            $paramsToSend = new \stdClass;
        } elseif (! empty($paramsToSend)) {
        } else {
            $paramsToSend = new \stdClass;
        }

        if (! ($paramsToSend instanceof \stdClass && count((array) $paramsToSend) === 0)) {
            $result['params'] = $paramsToSend;
        }

        return $result;
    }
}
