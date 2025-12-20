<?php

namespace PhpMcp\Client\JsonRpc\Results;

use PhpMcp\Client\JsonRpc\Result;

/**
 * A generic empty result for methods that return an empty object
 */
class EmptyResult extends Result
{
    /**
     * Create a new EmptyResult.
     */
    public function __construct()
    {
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        return []; // Empty result object
    }
}
