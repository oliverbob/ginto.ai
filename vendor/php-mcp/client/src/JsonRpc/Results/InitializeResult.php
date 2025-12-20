<?php

namespace PhpMcp\Client\JsonRpc\Results;

use PhpMcp\Client\JsonRpc\Result;
use PhpMcp\Client\Model\Capabilities;

class InitializeResult extends Result
{
    /**
     * Create a new InitializeResult.
     *
     * @param  string  $serverName  Server name
     * @param  string  $serverVersion  Server version
     * @param  string  $protocolVersion  Protocol version
     * @param  Capabilities  $capabilities  Server capabilities
     * @param  string|null  $instructions  Optional instructions text
     */
    public function __construct(
        public readonly string $serverName,
        public readonly string $serverVersion,
        public readonly string $protocolVersion,
        public readonly Capabilities $capabilities,
        public readonly ?string $instructions = null
    ) {}

    public static function fromArray(array $data): static
    {
        $serverInfo = $data['serverInfo'] ?? [];
        $capabilities = Capabilities::fromServerResponse($data['capabilities']);

        return new static(
            serverName: $serverInfo['name'] ?? 'Unknown Server',
            serverVersion: $serverInfo['version'] ?? 'Unknown Version',
            protocolVersion: $data['protocolVersion'],
            capabilities: $capabilities,
            instructions: $data['instructions'] ?? null
        );
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        $result = [
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => $this->serverVersion,
            ],
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities,
        ];

        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
        }

        return $result;
    }
}
