<?php

declare(strict_types=1);

namespace PhpMcp\Client\Factory;

/**
 * Generates unique IDs for JSON-RPC requests.
 */
class MessageIdGenerator
{
    private int $counter = 0;

    private string $prefix;

    public function __construct(string $prefix = 'mcp-req-')
    {
        // Add process ID or random element for more uniqueness across restarts/processes
        $this->prefix = $prefix.getmypid().'-'.bin2hex(random_bytes(4)).'-';
    }

    public function generate(): string
    {
        return $this->prefix.(++$this->counter);
    }
}
