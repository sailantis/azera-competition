<?php

declare(strict_types=1);

namespace App\Spiral\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lifecycle-hook middleware — the Spiral arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that wraps the handler and rewrites what comes
 * back: it stamps a marker header and overrides the status to 203. That is the smallest
 * honest shape of request lifecycle interception — the hook has to SEE the response,
 * which a before-only filter could not do.
 *
 * WHY PSR-15 AND NOT AN INTERCEPTOR. Spiral ships both, but this row is about the
 * request lifecycle, and PSR-15 middleware is Spiral's own mechanism for wrapping a
 * handler. The interceptor layer is a different facility (what `aop-tx-explicit`
 * exercises), so using it here would fold two abstractions into one measurement.
 *
 * SCOPED TO ONE ROUTE. Attached with `Route::withMiddleware()` in
 * AppRoutesBootloader rather than to `globalMiddleware()`, because no other entrant runs
 * a middleware stack on the benchmark rows and charging one would measure the stack
 * instead of the framework.
 */
final class LifecycleHookMiddleware implements MiddlewareInterface
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';

    public const VALUE = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader(self::HEADER, self::VALUE)
            ->withStatus(self::STATUS);
    }
}
