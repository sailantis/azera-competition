<?php

declare(strict_types=1);

/**
 * Logging interceptor — writes entry/exit records through a PSR-3 logger
 * (Spiral AOP demo).
 *
 * WHY IT TAKES A LOGGER RATHER THAN AN ARRAY. It used to append strings to an
 * `array &$entries` the application owned, so `/features/log` demonstrated a
 * pipeline appending to a PHP array — it never touched Spiral's logger, and the
 * report's "PSR-3 everywhere" claim was false for this entrant. Spiral ships
 * `LogsInterface::getLogger($channel)`, which returns a PSR-3 logger feeding the
 * framework's `ListenerRegistry`; passing `LoggerInterface` in makes the row
 * measure that path.
 *
 * `elapsed_ms` is measured across the intercepted call and travels in the exit
 * record, so the number describes the work the record wraps.
 */

namespace App\Spiral\Interceptor;

use Psr\Log\LoggerInterface;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

final class LogInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $target = $context->getTarget();
        // getPath() returns an array and takes no delimiter argument; closure targets
        // created via Target::fromClosure() have an empty path, so fall back to the
        // callable's reflection name for a readable log entry.
        $path  = $target->getPath();
        $label = $path === []
            ? ($target->getReflection()?->getName() ?? 'closure')
            : implode('.', $path);

        $this->logger->info("Entering {$label}", ['target' => $label]);

        $start = hrtime(true);

        try {
            $result = $handler->handle($context);
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