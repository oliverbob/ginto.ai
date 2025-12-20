<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc\Params;

class SubscribeResourceParams
{
    public function __construct(public readonly string $uri) {}

    public function toArray(): array
    {
        return ['uri' => $this->uri];
    }
}
