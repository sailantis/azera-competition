<?php

declare(strict_types=1);

/**
 * Logging interceptor — writes entry/exit records through a PSR-3 logger
 * (Symfony pipeline demo).
 *
 * Implemented as an invokable pipeline middleware (Symfony's interception idiom):
 * receives the wrapped callable as `$next`.
 *
 * WHY IT TAKES A LOGGER RATHER THAN AN ARRAY. It used to append strings to an
 * `array &$entries` the application owned, so `/features/log` demonstrated a
 * pipeline appending to a PHP array — it never touched a logger, and the report's
 * "PSR-3 everywhere" claim was false for this entrant. Symfony's container now
 * supplies `Psr\Log\LoggerInterface`, which is where the sink is decided.
 *
 * `elapsed_ms` is measured across the intercepted call and travels in the exit
 * record, so the number describes the work the record wraps.
 */

namespace App\Symfony\Service\Interceptor;

use Psr\Log\LoggerInterface;

final class LogInterceptor
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(callable $next, mixed $payload = null): mixed
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