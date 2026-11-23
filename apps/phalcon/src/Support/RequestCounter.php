<?php

declare(strict_types=1);

namespace App\Phalcon\Support;

/**
 * Request-scoped counter, registered as a shared service.
 *
 * Phalcon has no lifecycle-scope component, so — like the CI4 and CakePHP
 * entrants — this is a plain object plus an explicit `reset()`. That is the
 * resident-worker contract stated without ceremony: the container survives
 * between requests, so anything a request wrote into it has to be cleared
 * before the next one. Bootstrap::cleanup() does the same for the DI services
 * it can rebuild (`modelsManager`, `modelsMetadata`).
 */
final class RequestCounter
{
    private int $count = 0;

    public function increment(): int
    {
        return ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}
