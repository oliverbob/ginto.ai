<?php

declare(strict_types=1);

namespace PhpMcp\Client\Exception;

use Throwable;

class TimeoutException extends McpClientException
{
    public function __construct(
        string $message,
        private float $timeout,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct((string) $message, (int) $code, $previous);

        $this->timeout = (float) $timeout;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }
}
