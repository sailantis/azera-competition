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
        $this->entries[] = 'log:before ' . $context->getTarget()->getPath('.');

        $result = $handler->handle($context);

        $this->entries[] = 'log:after ' . $context->getTarget()->getPath('.');

        return $result;
    }
}