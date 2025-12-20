<?php

declare(strict_types=1);

namespace PhpMcp\Client\Enum;

/**
 * Defines the supported transport types for MCP servers.
 */
enum TransportType: string
{
    case Stdio = 'stdio';
    case Http = 'http';
}
