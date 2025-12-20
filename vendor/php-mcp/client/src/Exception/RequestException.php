<?php

declare(strict_types=1);

namespace PhpMcp\Client\Exception;

use PhpMcp\Client\JsonRpc\Error as JsonRpcError;
use Throwable;

class RequestException extends McpClientException
{
    public function __construct(
        string $message,
        public readonly ?JsonRpcError $rpcError = null,
        int $code = 0, // Or use RPC error code?
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromError(string $prefix, JsonRpcError $error): self
    {
        $message = trim($prefix.': '.$error->message);

        return new self($message, $error, $error->code);
    }

    public function getRpcError(): ?JsonRpcError
    {
        return $this->rpcError;
    }
}
