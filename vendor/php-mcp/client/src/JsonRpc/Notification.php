<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc;

use Psy\Readline\Hoa\ProtocolException;

final class Notification extends Message
{
    /**
     * @param  array<string, mixed>|array<int, mixed>  $params
     */
    public function __construct(
        public string $method,
        public array $params = []
    ) {}

    /**
     * @throws ProtocolException
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new ProtocolException('Invalid or missing "jsonrpc" version in notification. Must be "2.0".');
        }
        if (! isset($data['method']) || ! is_string($data['method']) || $data['method'] === '') {
            throw new ProtocolException('Invalid or missing "method" field in notification.');
        }
        // ID MUST NOT exist for notifications
        if (isset($data['id'])) {
            throw new ProtocolException('Invalid notification: must not contain "id" field.');
        }

        $params = $data['params'] ?? [];
        if (! is_array($params)) {
            throw new ProtocolException('Invalid "params" field in notification: must be an array or object.');
        }

        return new self(
            method: $data['method'],
            params: $params
        );
    }

    public function toArray(): array
    {
        $result = [
            'jsonrpc' => $this->jsonrpc,
            'method' => $this->method,
        ];

        // $paramsToSend = $this->params;
        // if (is_array($paramsToSend) && empty($paramsToSend)) {
        //     $paramsToSend = new \stdClass;
        // } elseif (! empty($paramsToSend)) {
        //     // No change needed
        // } else {
        //     $paramsToSend = new \stdClass;
        // }
        $params = (is_array($this->params) && empty($this->params)) ? new \stdClass : $this->params;

        if (! ($params instanceof \stdClass && count((array) $params) === 0)) {
            $result['params'] = $params;
        }

        return $result;
    }
}
