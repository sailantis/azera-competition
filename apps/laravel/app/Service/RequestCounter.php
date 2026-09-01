<?php

/**
 * Request-scoped counter service — Laravel-side equivalent of Spiral's
 * #[Scope('http')] scoped singletons.
 *
 * Bound via `$app->scoped()` (see AppServiceProvider): a fresh instance per
 * request, single instance within the request. The shared ScopeState proves
 * the instance was resolved inside the current request rather than the
 * global container.
 */

namespace App\Laravel\Service;

final class RequestCounter
{
    private int $count = 0;

    public function __construct(
        private readonly ScopeState $state,
    )
    {
    }

    public function increment(): int
    {
        $this->state->touch('RequestCounter instantiated');

        return ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function resetState(): void
    {
        $this->count = 0;
    }
}