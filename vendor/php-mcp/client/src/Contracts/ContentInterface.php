<?php

declare(strict_types=1);

namespace PhpMcp\Client\Contracts;

interface ContentInterface
{
    public function getType(): string;

    public function toArray(): array;
}
