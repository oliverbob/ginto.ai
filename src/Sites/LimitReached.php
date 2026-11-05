<?php

declare(strict_types=1);

namespace Ginto\Sites;

/** An AI request refused by a limit. The message is written for the member; the site itself keeps working. */
final class LimitReached extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
