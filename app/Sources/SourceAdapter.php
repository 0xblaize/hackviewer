<?php

declare(strict_types=1);

namespace App\Sources;

interface SourceAdapter
{
    public function key(): string;

    /** @return iterable<array<string, mixed>> */
    public function fetch(): iterable;
}
