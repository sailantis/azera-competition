<?php

declare(strict_types=1);

/**
 * Logging interceptor — records each call it wraps (Spiral AOP demo).
 */

namespace App\Spiral\Interceptor;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

final class LogInterceptor implements InterceptorInterface
{
    /** @param list<string> $entries */
    public function __construct(
        private array &$entries,
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

        $this->entries[] = 'log:before ' . $label;

        $result = $handler->handle($context);

        $this->entries[] = 'log:after ' . $label;

        return $result;
    }
}