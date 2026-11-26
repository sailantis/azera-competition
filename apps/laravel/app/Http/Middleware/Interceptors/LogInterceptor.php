<?php

/**
 * Logging interceptor — writes entry/exit records through a PSR-3 logger
 * (Laravel Pipeline demo).
 *
 * Implemented as an invokable Pipeline middleware (Laravel's interception
 * idiom): receives the wrapped callable as `$next`.
 *
 * WHY IT TAKES A LOGGER RATHER THAN AN ARRAY. It used to append strings to an
 * `array &$entries` the application owned, so `/features/log` demonstrated a
 * pipeline appending to a PHP array — it never touched Laravel's logger, and the
 * report's "PSR-3 everywhere" claim was false for this entrant. Laravel ships
 * `Log` → `LogManager` → Monolog; passing `LoggerInterface` in makes the row
 * measure that stack.
 *
 * `elapsed_ms` is measured across the intercepted call and travels in the exit
 * record, so the number describes the work the record wraps.
 */

namespace App\Laravel\Http\Middleware\Interceptors;

use Closure;
use Psr\Log\LoggerInterface;

final class LogInterceptor
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param  Closure(mixed...): mixed  $next
     */
    public function handle(mixed $payload, Closure $next): mixed
    {
        $label = 'Pipeline callable';

        $this->logger->info("Entering {$label}", ['target' => $label]);

        $start = hrtime(true);

        try {
            $result = $next($payload);
        } catch (\Throwable $e) {
            $this->logger->error("Failed {$label}", [
                'target'     => $label,
                'elapsed_ms' => round((hrtime(true) - $start) / 1e6, 4),
                'exception'  => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->info("Completed {$label}", [
            'target'     => $label,
            'elapsed_ms' => round((hrtime(true) - $start) / 1e6, 4),
        ]);

        return $result;
    }
}