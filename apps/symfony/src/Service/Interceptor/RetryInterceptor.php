<?php

declare(strict_types=1);

/**
 * Retry interceptor — retries a failing call with a fixed attempt budget
 * (Symfony pipeline demo).
 */

namespace App\Symfony\Service\Interceptor;

final class RetryInterceptor
{
    /**
     * @param list<string> $entries
     */
    public function __construct(
        private array &$entries,
        private readonly int $maxAttempts = 3,
        private readonly int $backoffMs = 0,
    )
    {
    }

    public function __invoke(callable $next, mixed $payload = null): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                $result = $next($payload);
                $this->entries[] = "retry:attempt {$attempt} succeeded";
                return $result;
            } catch (\Throwable $e) {
                $this->entries[] = "retry:attempt {$attempt} failed ({$e->getMessage()})";
                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }
                if ($this->backoffMs > 0) {
                    \usleep($this->backoffMs * 1000);
                }
            }
        }
    }
}