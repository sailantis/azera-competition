<?php

/**
 * Logging interceptor — records each call it wraps (Laravel Pipeline demo).
 *
 * Implemented as an invokable Pipeline middleware (Laravel's interception
 * idiom): receives the wrapped callable as `$next`.
 */

namespace App\Laravel\Http\Middleware\Interceptors;

use Closure;

final class LogInterceptor
{
    /** @param list<string> $entries */
    public function __construct(
        private array &$entries,
    )
    {
    }

    /**
     * @param  Closure(mixed...): mixed  $next
     */
    public function handle(mixed $payload, Closure $next): mixed
    {
        $label = 'closure';

        $this->entries[] = 'log:before ' . $label;

        $result = $next($payload);

        $this->entries[] = 'log:after ' . $label;

        return $result;
    }
}