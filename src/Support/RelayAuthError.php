<?php
namespace Ginto\Support;

/**
 * A relay request that will not be served, carrying the status to answer with.
 *
 * The message is written to be shown to the caller as-is: RelayAuth keeps the
 * revealing detail ("bad signature", "expired", "no such username") in the error
 * log and hands back something deliberately vague, so probing the endpoint does
 * not map out which usernames exist or which half of a stolen token still works.
 */
class RelayAuthError extends \RuntimeException
{
    public function __construct(string $message, private readonly int $status = 401)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
