<?php

declare(strict_types=1);

namespace PhpMcp\Client\JsonRpc;

/** Base class for structured JSON-RPC results */
abstract class Result
{
    // Common methods or properties if any? For now, just acts as a type hint base.
    // We need a static factory method convention.
    abstract public static function fromArray(array $data): static;
}
