<?php

declare(strict_types=1);

namespace PhpMcp\Client\Event;

abstract class AbstractNotificationEvent
{
    public function __construct(public readonly string $serverName)
    {
    }
}
