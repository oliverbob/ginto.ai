<?php

declare(strict_types=1);

namespace PhpMcp\Client\Event;

final class SamplingRequestReceived extends AbstractNotificationEvent
{
    // Define properties based on MCP sampling/createMessage structure
    public function __construct(string $serverName, public readonly array $requestParams)
    {
        parent::__construct($serverName);
    }
}
