<?php

declare(strict_types=1);

namespace App\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lifecycle-hook middleware — the Laravel arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that wraps the handler and rewrites what comes
 * back: it stamps a marker header on the response and overrides the status to 203.
 * That is the smallest honest shape of request lifecycle interception — the hook has
 * to SEE the response and act on it, which a before-only filter could not do.
 *
 * WHY THE ROW IS NAMED FOR THE LIFECYCLE AND NOT FOR MIDDLEWARE. The seven frameworks
 * do not agree on a mechanism: Laravel, Azera, Spiral and CakePHP have middleware
 * pipelines, CodeIgniter has filters, Symfony has `kernel.request` / `kernel.response`
 * event listeners, and Phalcon's full-MVC application only has events-manager hooks.
 * Each entrant uses its own facility and the comparison is of the intercepted request,
 * not of the machinery. See BenchmarkConfig::featureMap().
 *
 * SEPARATE FROM THE GLOBAL STACK. Registered on the one route rather than appended to
 * the kernel's global middleware, because no other entrant runs a middleware stack on
 * the benchmark rows and charging one would measure the stack instead of the framework.
 */
final class LifecycleHookMiddleware
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';

    public const VALUE = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(self::HEADER, self::VALUE);
        $response->setStatusCode(self::STATUS);

        return $response;
    }
}
