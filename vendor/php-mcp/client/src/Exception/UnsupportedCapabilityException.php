<?php

declare(strict_types=1);

namespace PhpMcp\Client\Exception;

final class UnsupportedCapabilityException extends \Exception
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
