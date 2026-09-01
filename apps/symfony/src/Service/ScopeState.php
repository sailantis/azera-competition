<?php

declare(strict_types=1);

/**
 * Request-scoped state — Symfony-side equivalent of Spiral's
 * #[Scope('http')] scoped singletons.
 *
 * Bound as a scoped service (see services.yaml `_defaults` + the
 * `kernel.reset` tag), so a fresh instance is created per request and
 * state cannot leak between requests.
 */

namespace App\Symfony\Service;

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