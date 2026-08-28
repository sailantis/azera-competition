<?php

declare(strict_types=1);

/**
 * Retry interceptor — retries a failing call with a fixed attempt budget
 * (Spiral AOP demo).
 */

namespace App\Spiral\Interceptor;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

final class RetryInterceptor implements InterceptorInterface
{
    /**
     * @param list<string> $entries
     */
    public function __construct(
        private array &$entries,
        private readonly int $maxAttempts = 3,
        private readonly int $backoffMs = 0,
    ) {}

    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                $result = $handler->handle($context);
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