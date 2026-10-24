<?php

declare(strict_types=1);

namespace Ginto\Sites\Vision;

/** Something that can look at up to three product photos and answer in the one schema (see Schema). */
interface Provider
{
    public function name(): string;

    /** Is it set up at all? An empty slot is skipped, not failed. */
    public function ready(): bool;

    /**
     * @param list<string> $shas at most Config::VISION_MAX_IMAGES
     * @return list<mixed> one raw answer per photo, in order (validated by the caller)
     */
    public function analyze(array $shas, array $ctx): array;
}
