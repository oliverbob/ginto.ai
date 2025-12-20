<?php

declare(strict_types=1);

namespace PhpMcp\Client\Event;

final class ResourceChanged extends AbstractNotificationEvent
{
    public function __construct(string $serverName, public readonly string $uri)
    {
        parent::__construct($serverName);
    }
}
