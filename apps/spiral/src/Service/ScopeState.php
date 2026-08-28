<?php

declare(strict_types=1);

/**
 * Tracks how the current RequestCounter instance came to life.
 *
 * Because Spiral resolves #[Scope('http')] services per named scope, a
 * RequestCounter pulled from the container inside the `http` scope is a
 * fresh instance per request (scoped singleton semantics). This helper
 * records the scope name it was created in so the demo endpoint can show
 * the reset-between-requests guarantee.
 */

namespace App\Spiral\Service;

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