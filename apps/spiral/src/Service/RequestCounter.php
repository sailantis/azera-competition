<?php

declare(strict_types=1);

/**
 * Request-scoped counter service.
 *
 * Spiral's equivalent of azera's RequestScoped lifecycle is the container
 * scope system: services bound with the {@see \Spiral\Core\Attribute\Scope}
 * attribute are resolved fresh inside a named scope (`http`) — each request
 * runs in its own `runScope(new Scope('http'), ...)` so per-request state
 * cannot leak between requests, while the container guarantees a single
 * instance within the scope (scoped singleton).
 *
 * The demo controller increments the counter and reads it back; the shared
 * {@see \App\Spiral\Service\ScopeState} proves the instance was resolved
 * inside the request's scope rather than the global container.
 */

namespace App\Spiral\Service;

final class RequestCounter
{
    private int $count = 0;

    public function __construct(
        private readonly ScopeState $state,
    ) {}

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