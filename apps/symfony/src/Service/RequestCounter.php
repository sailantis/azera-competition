<?php

declare(strict_types=1);

/**
 * Request-scoped counter service — Symfony-side equivalent of Spiral's
 * #[Scope('http')] scoped singletons.
 *
 * Bound as a scoped service (see services.yaml), so a fresh instance is
 * created per request. The shared ScopeState proves the instance was
 * resolved inside the current request rather than the global container.
 */

namespace App\Symfony\Service;

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