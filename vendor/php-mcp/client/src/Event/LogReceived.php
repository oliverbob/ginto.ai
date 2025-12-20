<?php

declare(strict_types=1);

namespace PhpMcp\Client\Event;

final class LogReceived extends AbstractNotificationEvent
{
    // Define properties based on MCP LogRecord structure
    public function __construct(string $serverName, public readonly array $logData)
    {
        parent::__construct($serverName);
    }
}
