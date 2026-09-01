<?php

/**
 * Tracks how the current RequestCounter instance came to life — mirrors
 * Spiral's ScopeState: records that per-request services are resolved
 * fresh inside the current request scope.
 */

namespace App\Laravel\Service;

final class ScopeState
{
    /** @var list<string> */
    private array $trace = [];

    public function touch(string $entry): void
    {
        $this->trace[] = $entry;
    }

    /** @return list<string> */
    public function trace(): array
    {
        return $this->trace;
    }
}