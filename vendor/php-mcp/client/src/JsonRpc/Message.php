<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc;

use JsonSerializable;

abstract class Message implements JsonSerializable
{
    public string $jsonrpc = '2.0';

    abstract public function toArray(): array;

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
