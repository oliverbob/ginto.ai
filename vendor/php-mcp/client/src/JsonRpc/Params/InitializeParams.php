<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc\Params;

use PhpMcp\Client\Model\Capabilities;

class InitializeParams
{
    public function __construct(
        public readonly string $clientName,
        public readonly string $clientVersion,
        public readonly string $protocolVersion,
        public readonly Capabilities $capabilities,

        // Add optional processId, rootUri, trace etc. if client supports them
    ) {}

    public function toArray(): array
    {
        // Convert capabilities/info objects to arrays for JSON
        return [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities->toClientArray(), // Use specific method
            'clientInfo' => [
                'name' => $this->clientName,
                'version' => $this->clientVersion,
            ],
        ];
    }
}
