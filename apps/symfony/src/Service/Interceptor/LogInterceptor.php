<?php

declare(strict_types=1);

/**
 * Logging interceptor — records each call it wraps (Symfony pipeline demo).
 *
 * Implemented as an invokable pipeline middleware (Symfony's interception
 * idiom): receives the wrapped callable as `$next`.
 */

namespace App\Symfony\Service\Interceptor;

final class LogInterceptor
{
    /** @param list<string> $entries */
    public function __construct(
        private array &$entries,
    )
    {
    }

    public function __invoke(callable $next, mixed $payload = null): mixed
    {
        $label = 'closure';

        $this->entries[] = 'log:before ' . $label;

        $result = $next($payload);

        $this->entries[] = 'log:after ' . $label;

        return $result;
    }
}