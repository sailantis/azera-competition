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

    /**
     * kernel.reset hook (services_resetter, fired by Kernel::terminate).
     *
     * The service is bound for the process lifetime in the warm-mode
     * worker, and touch() appends per-request entries — without the reset
     * the trace (and the response payload) would grow every request.
     */
    public function resetState(): void
    {
        $this->trace = [];
    }
}